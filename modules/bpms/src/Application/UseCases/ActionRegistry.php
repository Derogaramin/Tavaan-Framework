<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ActionRegistry — ثبت/اجرای اکشن‌های service task و timer (DOC-098 §6).
 */

namespace Modules\Bpms\Application\UseCases;

use Modules\Bpms\Domain\Exceptions\BpmsException;

/**
 * ActionRegistry — اکشن‌ها **از پیش ثبت می‌شوند** (هرگز از ورودی کاربر —
 * 🔒 §6). نام اکشن در تعریف فرایند (serviceTask.action / timer.action)
 * فقط به handler ثبت‌شده نگاشت می‌شود.
 *
 * اکشن داخلی `escalate` در TimerService تفسیر می‌شود (reassign + رویداد).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ActionRegistry
{
    /** @var array<string, callable(ProcessContext): void> */
    private array $actions = [];

    /**
     * ثبت اکشن.
     *
     * @param callable(ProcessContext): void $handler
     */
    public function register(string $name, callable $handler): void
    {
        if (\preg_match('/^[A-Za-z0-9_.\-:]+$/', $name) !== 1) {
            throw BpmsException::unknownAction($name);
        }
        $this->actions[$name] = $handler;
    }

    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    /** @return list<string> نام اکشن‌های ثبت‌شده */
    public function names(): array
    {
        $names = \array_keys($this->actions);
        \sort($names);

        return $names;
    }

    /**
     * اجرای اکشن.
     *
     * @throws BpmsException unknown_action — نام ثبت‌نشده (هرگز fallback ناامن)
     */
    public function execute(string $name, ProcessContext $context): void
    {
        $handler = $this->actions[$name] ?? throw BpmsException::unknownAction($name);
        $handler($context);
    }
}
