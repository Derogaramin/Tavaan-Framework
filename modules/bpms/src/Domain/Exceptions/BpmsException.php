<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * BpmsException — خطاهای دامنه/اجرای فرایند (کنترل‌شده، ۴xx).
 */

namespace Modules\Bpms\Domain\Exceptions;

/**
 * BpmsException — کنترلرها این را می‌گیرند و به flash-message تبدیل
 * می‌کنند؛ `reason` کلید ماشین‌خوان برای API/تست است (الگوی ForumException).
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class BpmsException extends \RuntimeException
{
    /** @param array<string, mixed> $context */
    private function __construct(string $message, private readonly string $reason, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    // ─── تعریف فرایند ──────────────────────────────────────────────────────

    public static function definitionNotFound(string $id): self
    {
        return new self('تعریف فرایند یافت نشد.', 'definition_not_found', ['id' => $id]);
    }

    public static function noActiveDefinition(string $key): self
    {
        return new self('نسخهٔ فعالی برای این فرایند وجود ندارد.', 'no_active_definition', ['key' => $key]);
    }

    public static function notEditable(string $id, string $status): self
    {
        return new self('فقط پیش‌نویس قابل ویرایش است.', 'not_editable', ['id' => $id, 'status' => $status]);
    }

    /** @param list<string> $problems */
    public static function invalidDefinition(array $problems): self
    {
        return new self(
            'تعریف فرایند نامعتبر است: ' . \implode(' | ', $problems),
            'invalid_definition',
            ['problems' => $problems],
        );
    }

    // ─── مدل/عنصر ──────────────────────────────────────────────────────────

    public static function unknownElement(string $elementId): self
    {
        return new self('عنصر ناشناخته در گراف فرایند.', 'unknown_element', ['element' => $elementId]);
    }

    public static function gatewayStuck(string $gatewayId): self
    {
        return new self('دروازه بدون مسیر قابل‌انتخاب (هیچ شرطی مطابق نیست و default تعریف نشده).', 'gateway_stuck', ['gateway' => $gatewayId]);
    }

    public static function unsupportedElement(string $elementId, string $type): self
    {
        return new self('نوع عنصر پشتیبانی نمی‌شود.', 'unsupported_element', ['element' => $elementId, 'type' => $type]);
    }

    // ─── نمونه ─────────────────────────────────────────────────────────────

    public static function instanceNotFound(string $id): self
    {
        return new self('نمونهٔ فرایند یافت نشد.', 'instance_not_found', ['id' => $id]);
    }

    public static function notRunning(string $id, string $status): self
    {
        return new self('نمونهٔ فرایند در حال اجرا نیست.', 'not_running', ['id' => $id, 'status' => $status]);
    }

    public static function tooManyInstances(string $key, int $limit): self
    {
        return new self('سقف نمونه‌های فعال این فرایند پر است.', 'too_many_instances', ['key' => $key, 'limit' => $limit]);
    }

    public static function advanceLimit(string $id, int $limit): self
    {
        return new self('پیش‌برد فرایند از سقف گام‌ها گذشت (حلقهٔ service task؟).', 'advance_limit', ['id' => $id, 'limit' => $limit]);
    }

    // ─── کار ───────────────────────────────────────────────────────────────

    public static function taskNotFound(string $id): self
    {
        return new self('کار یافت نشد.', 'task_not_found', ['id' => $id]);
    }

    public static function taskNotOpen(string $id, string $status): self
    {
        return new self('کار باز نیست.', 'task_not_open', ['id' => $id, 'status' => $status]);
    }

    public static function alreadyClaimed(string $id): self
    {
        return new self('کار پیش‌تر توسط کاربر دیگری تصاحب شده است.', 'already_claimed', ['id' => $id]);
    }

    public static function notAssignee(string $id): self
    {
        return new self('شما assignee این کار نیستید.', 'not_assignee', ['id' => $id]);
    }

    // ─── اکشن/عبارت ────────────────────────────────────────────────────────

    public static function unknownAction(string $action): self
    {
        return new self('اکشن ثبت‌نشده برای service task / timer.', 'unknown_action', ['action' => $action]);
    }

    public static function invalidExpression(string $expr, string $detail): self
    {
        return new self('عبارت شرط نامعتبر است.', 'invalid_expression', ['expr' => $expr, 'detail' => $detail]);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
