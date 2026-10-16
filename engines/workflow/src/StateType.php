<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * StateType — نوع گره‌های فرایند (DOC-071 §2).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
enum StateType: string
{
    /** گره شروع (یک فرایند دقیقاً یک start دارد) */
    case Start = 'start';

    /** وضعیت عادی (بدون رفتار خاص) */
    case Normal = 'normal';

    /** کار انسانی — نیازمند assignee (Task ساخته می‌شود) */
    case UserTask = 'user_task';

    /** دروازهٔ انحصاری — اولین گذار با guard مطابق انتخاب می‌شود */
    case ExclusiveGateway = 'exclusive_gateway';

    /** دروازهٔ موازی — fork (چند خروجی) / join (چند ورودی) */
    case ParallelGateway = 'parallel_gateway';

    /** رویداد زمان‌دار */
    case TimerEvent = 'timer_event';

    /** زیرفرایند — ارجاع به یک ProcessDefinition دیگر */
    case SubProcess = 'sub_process';

    /** وضعیت پایانی */
    case End = 'end';

    /** آیا این نوع یک دروازه است (ورود = پردازش آنی توسط موتور)؟ */
    public function isGateway(): bool
    {
        return $this === self::ExclusiveGateway || $this === self::ParallelGateway;
    }

    /** آیا توکن در این نوع باید منتظر کار انسانی بماند؟ */
    public function isUserTask(): bool
    {
        return $this === self::UserTask;
    }

    /**
     * ساخت از رشته (انعطاف برای JSON/DB — ناشناخته → Normal).
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Normal;
    }
}
