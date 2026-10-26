<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ElementType — نوع عناصر گراف فرایند (DOC-098 §3).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * ElementType — انواع عنصر در تعریف فرایند JSON:
 * startEvent/userTask/serviceTask/exclusiveGateway/parallelGateway/endEvent.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum ElementType: string
{
    case StartEvent = 'startEvent';
    case UserTask = 'userTask';
    case ServiceTask = 'serviceTask';
    case ExclusiveGateway = 'exclusiveGateway';
    case ParallelGateway = 'parallelGateway';
    case EndEvent = 'endEvent';

    /** آیا عنصر یک دروازه است (پردازش آنی توسط موتور)؟ */
    public function isGateway(): bool
    {
        return $this === self::ExclusiveGateway || $this === self::ParallelGateway;
    }

    /** آیا توکن در این عنصر باید منتظر کار انسانی بماند؟ */
    public function isWaitState(): bool
    {
        return $this === self::UserTask;
    }

    /** ساخت از رشته — ناشناخته → null (اعتبارسنجی در ModelValidator). */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
