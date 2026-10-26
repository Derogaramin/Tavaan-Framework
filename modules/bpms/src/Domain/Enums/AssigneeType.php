<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * AssigneeType — نوع تخصیص کار (DOC-098 §2).
 */

namespace Modules\Bpms\Domain\Enums;

/**
 * AssigneeType — قالب assignee در تعریف فرایند:
 * `user:<id>` / `role:<name>` / `group:<name>` / `initiator`.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
enum AssigneeType: string
{
    case User = 'user';
    case Role = 'role';
    case Group = 'group';
    case Initiator = 'initiator';
    /** بدون assignee — در صف عمومی (هر دارندهٔ مجوز task.claim). */
    case Anyone = 'anyone';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Anyone;
    }

    /**
     * پارس قالب assignee تعریف فرایند → [type, id].
     *
     * @return array{0: self, 1: string|null}
     */
    public static function parse(?string $assignee): array
    {
        $assignee = \trim((string) $assignee);
        if ($assignee === '' || $assignee === 'initiator') {
            return [$assignee === 'initiator' ? self::Initiator : self::Anyone, null];
        }
        foreach ([self::User, self::Role, self::Group] as $type) {
            $prefix = $type->value . ':';
            if (\str_starts_with($assignee, $prefix)) {
                return [$type, \substr($assignee, \strlen($prefix))];
            }
        }

        // شناسهٔ عددی بدون prefix → کاربر
        if (\ctype_digit($assignee)) {
            return [self::User, $assignee];
        }

        // نام خالص → نقش (الگوی مستند §3: `role:manager` صریح؛ خالص برای سهولت)
        return [self::Role, $assignee];
    }
}
