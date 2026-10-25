<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateStatus — وضعیت گواهی (issued|revoked).
 */

namespace Modules\Lms\Domain\Enums;

/**
 * افزودنی مستند نسبت به DOC-095 §2 — ابطال مدیرتی گواهی
 * (verify عمومی باید ابطال را نشان دهد).
 *
 * @api 1.0
 */
enum CertificateStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';

    public function isValid(): bool
    {
        return $this === self::Issued;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Issued => 'معتبر',
            self::Revoked => 'باطل‌شده',
        };
    }
}
