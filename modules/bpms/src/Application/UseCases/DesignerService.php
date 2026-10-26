<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DesignerService — چرخهٔ عمر تعریف فرایند (DOC-098 §1.1/§3 — AC-BPMS-11).
 */

namespace Modules\Bpms\Application\UseCases;

use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Enums\DefinitionStatus;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Services\ModelValidator;
use Modules\Bpms\Domain\ValueObjects\FormSchema;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;
use Modules\Bpms\Infrastructure\Workflow\WorkflowBridge;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * DesignerService — draft → publish → activate/retire با نسخه‌بندی
 * (uk_key_version). خروجی Designer (JSON) پیش از publish با
 * ModelValidator راستی‌آزمایی می‌شود تا «JSON معتبر که Engine اجرا کند»
 * تضمین شود (AC-BPMS-11). انتشار = ثبت mirror در موتور workflow.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class DesignerService
{
    public function __construct(
        private readonly DefinitionRepositoryInterface $definitions,
        private readonly ModelValidator $validator,
        private readonly WorkflowBridge $bridge,
        private readonly BpmsAuditRecorder $audit,
    ) {
    }

    /**
     * ساخت پیش‌نویس جدید.
     *
     * @param array<string, mixed> $input key/name/description/definition/form_schema
     *
     * @throws ValidationException
     */
    public function createDraft(array $input, ?int $actorId = null): ProcessDefinition
    {
        $key = $this->validateKey((string) ($input['key'] ?? ''));
        $name = \trim((string) ($input['name'] ?? ''));
        if ($name === '' || \mb_strlen($name) > 190) {
            throw new ValidationException(messageId: 'bpms::messages.definition_name_invalid', errors: ['name' => ['name_invalid']]);
        }

        $definitionJson = $this->decodeDefinition($input['definition'] ?? null);
        $formsJson = $this->decodeForms($input['form_schema'] ?? null);

        // 🔒 حتی پیش‌نویس باید ساختار معتبر داشته باشد — موجودیت همیشه
        // با ProcessModel قابل hydrate ذخیره می‌شود (AC-BPMS-11)
        $problems = $this->validator->validate($definitionJson, $formsJson);
        if ($problems !== []) {
            throw BpmsException::invalidDefinition($problems);
        }

        $now = Clock::now();
        $version = ($this->definitions->latestVersionOf($key)?->version() ?? 0) + 1;

        $definition = new ProcessDefinition(
            id: Ulid::generate($now)->toString(),
            key: $key,
            name: $name,
            version: $version,
            model: ProcessModel::fromArray($definitionJson),
            formSchemas: $this->buildForms($formsJson),
            status: DefinitionStatus::Draft,
            isActive: false,
            description: $this->optionalString($input['description'] ?? null, 500),
            createdBy: $actorId,
            createdAt: $now->format('Y-m-d H:i:s.v'),
            updatedAt: $now->format('Y-m-d H:i:s.v'),
        );

        $this->definitions->save($definition);
        $this->audit->record('definition.created', 'bpms_definition', $definition->id(), [
            'key' => $key, 'version' => $version,
        ], $actorId);

        return $definition;
    }

    /**
     * ویرایش پیش‌نویس (published/retired تغییرناپذیر‌اند).
     *
     * @param array<string, mixed> $input
     *
     * @throws BpmsException not_editable
     */
    public function updateDraft(string $id, array $input, ?int $actorId = null): ProcessDefinition
    {
        $definition = $this->definitions->findById($id) ?? throw BpmsException::definitionNotFound($id);

        $definitionJson = \array_key_exists('definition', $input)
            ? $this->decodeDefinition($input['definition'])
            : $definition->model()->toArray();
        $formsJson = \array_key_exists('form_schema', $input)
            ? $this->decodeForms($input['form_schema'])
            : $this->formsToArray($definition->formSchemas());

        $problems = $this->validator->validate($definitionJson, $formsJson);
        if ($problems !== []) {
            throw BpmsException::invalidDefinition($problems);
        }

        $name = \trim((string) ($input['name'] ?? ''));

        $definition->updateDraft(
            name: $name !== '' ? $name : $definition->name(),
            model: ProcessModel::fromArray($definitionJson),
            formSchemas: $this->buildForms($formsJson),
            description: \array_key_exists('description', $input)
                ? $this->optionalString($input['description'], 500)
                : $definition->description(),
            updatedAt: Clock::now()->format('Y-m-d H:i:s.v'),
        );

        $this->definitions->save($definition);
        $this->audit->record('definition.updated', 'bpms_definition', $definition->id(), [
            'key' => $definition->key(), 'version' => $definition->version(),
        ], $actorId);

        return $definition;
    }

    /**
     * انتشار پیش‌نویس: validate کامل → published + active (نسخه‌های قبلی
     * غیرفعال) → ثبت mirror در موتور workflow (AC-BPMS-11).
     *
     * @throws BpmsException invalid_definition / not_editable
     */
    public function publish(string $id, ?int $actorId = null): ProcessDefinition
    {
        $definition = $this->definitions->findById($id) ?? throw BpmsException::definitionNotFound($id);

        $problems = $this->validator->validate(
            $definition->model()->toArray(),
            $this->formsToArray($definition->formSchemas()),
        );
        if ($problems !== []) {
            throw BpmsException::invalidDefinition($problems);
        }

        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        DB::transaction(function () use ($definition, $nowIso): void {
            $definition->publish($nowIso);
            $this->definitions->deactivateOtherVersions($definition->key(), $definition->id(), $nowIso);
            $this->definitions->save($definition);
        });

        // mirror موتور workflow (best-effort — تاریخچه/visualization)
        $this->bridge->registerDefinition($definition);

        $this->audit->record('definition.published', 'bpms_definition', $definition->id(), [
            'key' => $definition->key(), 'version' => $definition->version(),
        ], $actorId);

        return $definition;
    }

    /** فعال‌سازی یک نسخهٔ published (سوئیچ نسخهٔ جاری). */
    public function activate(string $id, ?int $actorId = null): ProcessDefinition
    {
        $definition = $this->definitions->findById($id) ?? throw BpmsException::definitionNotFound($id);
        if ($definition->status() !== DefinitionStatus::Published) {
            throw BpmsException::notEditable($id, $definition->status()->value);
        }

        $nowIso = Clock::now()->format('Y-m-d H:i:s.v');
        DB::transaction(function () use ($definition, $nowIso): void {
            $this->definitions->deactivateOtherVersions($definition->key(), $definition->id(), $nowIso);
            $definition->activate($nowIso);
            $this->definitions->save($definition);
        });

        $this->bridge->registerDefinition($definition);
        $this->audit->record('definition.activated', 'bpms_definition', $definition->id(), [
            'key' => $definition->key(), 'version' => $definition->version(),
        ], $actorId);

        return $definition;
    }

    /** بازنشستگی نسخهٔ جاری. */
    public function retire(string $id, ?int $actorId = null): ProcessDefinition
    {
        $definition = $this->definitions->findById($id) ?? throw BpmsException::definitionNotFound($id);
        $definition->retire(Clock::now()->format('Y-m-d H:i:s.v'));
        $this->definitions->save($definition);
        $this->audit->record('definition.retired', 'bpms_definition', $definition->id(), [
            'key' => $definition->key(), 'version' => $definition->version(),
        ], $actorId);

        return $definition;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @throws ValidationException */
    private function validateKey(string $key): string
    {
        $key = \trim($key);
        if (\preg_match('/^[a-z0-9][a-z0-9_\-]{1,98}[a-z0-9]$/', $key) !== 1) {
            throw new ValidationException(messageId: 'bpms::messages.definition_key_invalid', errors: ['key' => ['key_invalid']]);
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function decodeDefinition(mixed $raw): array
    {
        if (\is_array($raw)) {
            return $raw;
        }
        if (\is_string($raw) && \trim($raw) !== '') {
            try {
                $decoded = \json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ValidationException(messageId: 'bpms::messages.definition_json_invalid', errors: ['definition' => ['json: ' . $e->getMessage()]]);
            }
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        throw new ValidationException(messageId: 'bpms::messages.definition_json_invalid', errors: ['definition' => ['required']]);
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function decodeForms(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (\is_string($raw)) {
            try {
                $raw = \json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new ValidationException(messageId: 'bpms::messages.form_json_invalid', errors: ['form_schema' => ['json: ' . $e->getMessage()]]);
            }
        }
        if (!\is_array($raw)) {
            throw new ValidationException(messageId: 'bpms::messages.form_json_invalid', errors: ['form_schema' => ['array_required']]);
        }

        // دو قالب: {formId: schema} یا [schema, …] (id از خود schema)
        $forms = [];
        foreach ($raw as $key => $schema) {
            if (!\is_array($schema)) {
                continue;
            }
            $id = \is_string($key) && $key !== '' ? $key : (string) ($schema['id'] ?? '');
            if ($id === '') {
                throw new ValidationException(messageId: 'bpms::messages.form_json_invalid', errors: ['form_schema' => ['id_required']]);
            }
            $schema['id'] = $id;
            $forms[$id] = $schema;
        }

        return $forms;
    }

    /**
     * @param array<string, array<string, mixed>> $formsJson
     *
     * @return array<string, FormSchema>
     *
     * @throws ValidationException
     */
    private function buildForms(array $formsJson): array
    {
        $forms = [];
        foreach ($formsJson as $id => $raw) {
            try {
                $forms[$id] = FormSchema::fromArray($raw);
            } catch (\InvalidArgumentException $e) {
                throw new ValidationException(messageId: 'bpms::messages.form_invalid', errors: [$id => [$e->getMessage()]]);
            }
        }

        return $forms;
    }

    /**
     * @param array<string, FormSchema> $forms
     *
     * @return array<string, array<string, mixed>>
     */
    private function formsToArray(array $forms): array
    {
        $out = [];
        foreach ($forms as $id => $schema) {
            $out[$id] = $schema->toArray();
        }

        return $out;
    }

    private function optionalString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = \trim((string) $value);

        return $text === '' ? null : \mb_substr($text, 0, $max);
    }
}
