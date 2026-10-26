<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessInstance — یک اجرای فرایند (DOC-098 §2/§5) — موجودیت ریشه.
 */

namespace Modules\Bpms\Domain\Entities;

use Modules\Bpms\Domain\Enums\InstanceStatus;
use Modules\Bpms\Domain\Exceptions\BpmsException;

/**
 * ProcessInstance — وضعیت اجرا: توکن‌ها (پشتیبانی parallel fork/join)،
 * متغیرها، وضعیت و پیوند به تعریف/نمونهٔ mirror موتور workflow.
 *
 * 🔒 کلیدهای رزرو `_tokens/_joins/_ended` درون همان ستون variables
 * (قرارداد همسان با موتور workflow — DOC-071 §4). متغیرهای کاربر با
 * `_` شروع نمی‌شوند و mergeVariables کلید رزرو را رد می‌کند.
 * 🔒 تغییر state فقط از راه ProcessEngine (moveTo داخلی است).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ProcessInstance
{
    /** کلیدهای رزرو موتور در variables. */
    private const RESERVED_KEYS = ['_tokens', '_joins', '_ended'];

    /** @var list<string> توکن‌های فعال (element id) */
    private array $tokens = [];

    /** @var array<string, int> gatewayId → تعداد توکن رسیده (join) */
    private array $joins = [];

    private int $ended = 0;

    /** @var array<string, mixed> متغیرهای کاربر (بدون کلیدهای رزرو) */
    private array $variables = [];

    private InstanceStatus $status;

    private ?string $completedAt = null;

    private ?string $workflowInstanceId = null;

    public function __construct(
        private readonly string $id,
        private readonly string $definitionId,
        private readonly string $definitionKey,
        private ?string $businessKey,
        private readonly ?int $initiatorId,
        private string $currentState,
        array $variables,
        private readonly ?string $startedAt = null,
        InstanceStatus $status = InstanceStatus::Running,
        ?string $completedAt = null,
        ?string $workflowInstanceId = null,
    ) {
        $this->status = $status;
        $this->completedAt = $completedAt;
        $this->workflowInstanceId = $workflowInstanceId;
        $this->loadState($variables);
    }

    /**
     * ساخت از ردیف دیتابیس.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            definitionId: (string) ($row['definition_id'] ?? ''),
            definitionKey: (string) ($row['definition_key'] ?? ''),
            businessKey: isset($row['business_key']) ? (string) $row['business_key'] : null,
            initiatorId: isset($row['initiator_id']) ? (int) $row['initiator_id'] : null,
            currentState: (string) ($row['current_state'] ?? ''),
            variables: (array) ($row['variables'] ?? []),
            startedAt: isset($row['started_at']) ? (string) $row['started_at'] : null,
            status: InstanceStatus::fromString((string) ($row['status'] ?? 'running')),
            completedAt: isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            workflowInstanceId: isset($row['workflow_instance_id']) ? (string) $row['workflow_instance_id'] : null,
        );
    }

    // ─── خوانندگان ─────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function definitionId(): string
    {
        return $this->definitionId;
    }

    public function definitionKey(): string
    {
        return $this->definitionKey;
    }

    public function businessKey(): ?string
    {
        return $this->businessKey;
    }

    public function initiatorId(): ?int
    {
        return $this->initiatorId;
    }

    public function currentState(): string
    {
        return $this->currentState;
    }

    public function status(): InstanceStatus
    {
        return $this->status;
    }

    public function startedAt(): ?string
    {
        return $this->startedAt;
    }

    public function completedAt(): ?string
    {
        return $this->completedAt;
    }

    public function workflowInstanceId(): ?string
    {
        return $this->workflowInstanceId;
    }

    /** @return list<string> همهٔ stateهای فعال (parallel) */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /** @return array<string, int> */
    public function joins(): array
    {
        return $this->joins;
    }

    public function endedCount(): int
    {
        return $this->ended;
    }

    /** @return array<string, mixed> متغیرهای کاربر (بدون رزروها) */
    public function variables(): array
    {
        return $this->variables;
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    /** متغیرها + کلیدهای رزرو (برای persist). @return array<string, mixed> */
    public function serializeVariables(): array
    {
        $reserved = ['_tokens' => $this->tokens, '_ended' => $this->ended];
        if ($this->joins !== []) {
            $reserved['_joins'] = $this->joins;
        }

        return [...$this->variables, ...$reserved];
    }

    // ─── رفتار (توسط ProcessEngine فراخوانی می‌شود) ────────────────────────

    /**
     * جابجایی یک توکن از وضعیت فعلی به مقصد.
     *
     * @throws BpmsException not_running / توکن در مبدأ نبود
     */
    public function moveTo(string $from, string $to): void
    {
        $this->assertActive();
        $index = \array_search($from, $this->tokens, true);
        if ($index === false) {
            throw BpmsException::invalidDefinition(['توکن در عنصر ' . $from . ' نیست']);
        }
        \array_splice($this->tokens, (int) $index, 1);
        $this->tokens[] = $to;
        $this->currentState = $to;
    }

    /** ورود توکن جدید (fork — بدون حذف توکن موجود). */
    public function addToken(string $stateId): void
    {
        $this->assertActive();
        $this->tokens[] = $stateId;
        $this->currentState = $stateId;
    }

    /** حذف یک توکن بدون شمارش پایان (join — توکن‌های پارک‌شده). */
    public function consumeToken(string $stateId): bool
    {
        $index = \array_search($stateId, $this->tokens, true);
        if ($index === false) {
            return false;
        }
        \array_splice($this->tokens, (int) $index, 1);
        if ($this->tokens !== []) {
            $this->currentState = $this->tokens[0];
        }

        return true;
    }

    /** حذف یک توکن (رسیدن به endEvent). */
    public function removeToken(string $stateId): bool
    {
        $index = \array_search($stateId, $this->tokens, true);
        if ($index === false) {
            return false;
        }
        \array_splice($this->tokens, (int) $index, 1);
        $this->ended++;
        if ($this->tokens !== []) {
            $this->currentState = $this->tokens[0];
        }

        return true;
    }

    /** افزایش شمارندهٔ join — آیا همهٔ توکن‌ها رسیده‌اند؟ */
    public function arriveAtJoin(string $gatewayId, int $expected): bool
    {
        $arrived = ($this->joins[$gatewayId] ?? 0) + 1;
        if ($arrived < $expected) {
            $this->joins[$gatewayId] = $arrived;

            return false;
        }
        unset($this->joins[$gatewayId]);

        return true;
    }

    /**
     * ادغام دادهٔ فرم/ورودی در متغیرها (§5 completeTask).
     *
     * @param array<string, mixed> $data
     */
    public function mergeVariables(array $data): void
    {
        foreach ($data as $key => $value) {
            if (!\is_string($key) || $key === '' || \in_array($key, self::RESERVED_KEYS, true) || $key[0] === '_') {
                continue;   // 🔒 کلیدهای رزرو هرگز از ورودی نوشته نمی‌شوند
            }
            $this->variables[$key] = $value;
        }
    }

    public function setVariable(string $key, mixed $value): void
    {
        if ($key === '' || $key[0] === '_') {
            throw BpmsException::invalidDefinition(['کلید متغیر رزرو است: ' . $key]);
        }
        $this->variables[$key] = $value;
    }

    public function setWorkflowInstanceId(?string $id): void
    {
        $this->workflowInstanceId = $id === '' ? null : $id;
    }

    public function setBusinessKey(?string $businessKey): void
    {
        $this->businessKey = $businessKey;
    }

    /** تکمیل موفق (همهٔ توکن‌ها به پایان رسیده‌اند). */
    public function complete(string $completedAt): void
    {
        if ($this->status->isTerminal()) {
            return;
        }
        $this->status = InstanceStatus::Completed;
        $this->completedAt = $completedAt;
        $this->tokens = [];
        $this->joins = [];
    }

    /** پایان مدیریتی (بدون موفقیت منطقی). */
    public function terminate(string $completedAt): void
    {
        $this->assertActive();
        $this->status = InstanceStatus::Terminated;
        $this->completedAt = $completedAt;
        $this->tokens = [];
        $this->joins = [];
    }

    public function suspend(): void
    {
        $this->assertActive();
        $this->status = InstanceStatus::Suspended;
    }

    public function resume(): void
    {
        if ($this->status !== InstanceStatus::Suspended) {
            throw BpmsException::notRunning($this->id, $this->status->value);
        }
        $this->status = InstanceStatus::Running;
    }

    /** آیا قابل پیش‌برد است؟ */
    public function isRunning(): bool
    {
        return $this->status === InstanceStatus::Running;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /** همهٔ توکن‌ها پایان یافته‌اند؟ (شرط complete — §5 endEvent). */
    public function allTokensEnded(): bool
    {
        return $this->tokens === [] && $this->ended > 0;
    }

    /** @throws BpmsException */
    private function assertActive(): void
    {
        if (!$this->status->isActive()) {
            throw BpmsException::notRunning($this->id, $this->status->value);
        }
    }

    /** @param array<string, mixed> $variables */
    private function loadState(array $variables): void
    {
        $tokens = $variables['_tokens'] ?? null;
        $this->tokens = \is_array($tokens) ? \array_values(\array_map('strval', $tokens)) : [];

        $this->joins = [];
        foreach ((array) ($variables['_joins'] ?? []) as $gateway => $count) {
            $this->joins[(string) $gateway] = (int) $count;
        }
        $this->ended = (int) ($variables['_ended'] ?? 0);

        foreach ($variables as $key => $value) {
            if (\is_string($key) && $key !== '' && $key[0] === '_') {
                continue;
            }
            $this->variables[(string) $key] = $value;
        }
    }
}
