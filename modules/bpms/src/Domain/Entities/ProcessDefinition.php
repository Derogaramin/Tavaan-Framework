<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessDefinition — تعریف فرایند (طراحی‌شده) — موجودیت ریشه (DOC-098 §2/§3).
 */

namespace Modules\Bpms\Domain\Entities;

use Modules\Bpms\Domain\Enums\DefinitionStatus;
use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\ValueObjects\FormSchema;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;

/**
 * ProcessDefinition — یک نسخهٔ طراحی‌شده از فرایند:
 * key + version (uk_key_version) + model (گراف عناصر) + formSchemas
 * (کلید فرم → schema — §4) + status/is_active.
 *
 * 🔒 پس از publish، model/formها تغییرناپذیر‌اند (نمونه‌های در جریان به
 * نسخهٔ خود قفل می‌مانند — versioning).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ProcessDefinition
{
    /**
     * @param array<string, FormSchema> $formSchemas کلید = شناسهٔ فرم
     */
    public function __construct(
        private readonly string $id,
        private readonly string $key,
        private string $name,
        private int $version,
        private ProcessModel $model,
        private array $formSchemas,
        private DefinitionStatus $status,
        private bool $isActive,
        private ?string $description = null,
        private readonly ?int $createdBy = null,
        private readonly ?string $createdAt = null,
        private ?string $updatedAt = null,
    ) {
    }

    /**
     * ساخت از ردیف دیتابیس (hydrate).
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $definition = (array) ($row['definition'] ?? []);
        $forms = [];
        foreach ((array) ($row['form_schema'] ?? []) as $formId => $rawForm) {
            if (!\is_array($rawForm)) {
                continue;
            }
            try {
                $schema = FormSchema::fromArray($rawForm);
            } catch (\InvalidArgumentException) {
                continue;   // فرم نامعتبر در hydrate — نادیده (validator هنگام publish سخت‌گیر است)
            }
            $forms[\is_string($formId) && $formId !== '' ? $formId : $schema->id] = $schema;
        }

        return new self(
            id: (string) ($row['id'] ?? ''),
            key: (string) ($row['key'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            version: (int) ($row['version'] ?? 1),
            model: ProcessModel::fromArray($definition),
            formSchemas: $forms,
            status: DefinitionStatus::fromString((string) ($row['status'] ?? 'draft')),
            isActive: (bool) ($row['is_active'] ?? false),
            description: isset($row['description']) ? (string) $row['description'] : null,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function model(): ProcessModel
    {
        return $this->model;
    }

    /** @return array<string, FormSchema> */
    public function formSchemas(): array
    {
        return $this->formSchemas;
    }

    public function formSchema(string $formId): ?FormSchema
    {
        return $this->formSchemas[$formId] ?? null;
    }

    public function status(): DefinitionStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    /** آیا قابل start از کاتالوگ عمومی است (meta.startable در تعریف). */
    public function isStartable(): bool
    {
        return $this->isActive && $this->status === DefinitionStatus::Published;
    }

    /**
     * به‌روزرسانی محتوای پیش‌نویس (فقط draft — 🔒 published تغییرناپذیر است).
     *
     * @param array<string, FormSchema> $formSchemas
     *
     * @throws BpmsException not_editable
     */
    public function updateDraft(string $name, ProcessModel $model, array $formSchemas, ?string $description, string $updatedAt): void
    {
        if (!$this->status->isEditable()) {
            throw BpmsException::notEditable($this->id, $this->status->value);
        }
        $this->name = $name;
        $this->model = $model;
        $this->formSchemas = $formSchemas;
        $this->description = $description;
        $this->updatedAt = $updatedAt;
    }

    /**
     * انتشار: draft → published (نسخهٔ جاری می‌شود).
     *
     * @throws BpmsException not_editable
     */
    public function publish(string $updatedAt): void
    {
        if ($this->status !== DefinitionStatus::Draft) {
            throw BpmsException::notEditable($this->id, $this->status->value);
        }
        $this->status = DefinitionStatus::Published;
        $this->isActive = true;
        $this->updatedAt = $updatedAt;
    }

    /** فعال‌سازی این نسخه (غيرفعال‌سازی سایر نسخه‌ها در repository انجام می‌شود). */
    public function activate(string $updatedAt): void
    {
        $this->isActive = true;
        $this->updatedAt = $updatedAt;
    }

    public function deactivate(string $updatedAt): void
    {
        $this->isActive = false;
        $this->updatedAt = $updatedAt;
    }

    /** بازنشستگی (دیگر start نمی‌شود؛ نمونه‌های جاری ادامه می‌دهند). */
    public function retire(string $updatedAt): void
    {
        $this->status = DefinitionStatus::Retired;
        $this->isActive = false;
        $this->updatedAt = $updatedAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }
}
