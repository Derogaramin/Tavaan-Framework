<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * AccountType — نوع حساب (DOC-097 §2.1: chart of accounts).
 */

namespace Modules\Erp\Domain\Enums;

/**
 * @api 1.0
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public static function tryFromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Asset;
    }

    /** ماندهٔ طبیعی (normal balance) — قانون حسابداری. */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Revenue => 'credit',
        };
    }

    /** حساب موقت (بستن دوره — §4 گام ۴). */
    public function isTemporary(): bool
    {
        return $this === self::Revenue || $this === self::Expense;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Asset => 'دارایی',
            self::Liability => 'بدهی',
            self::Equity => 'حقوق صاحبان سهام',
            self::Revenue => 'درآمد',
            self::Expense => 'هزینه',
        };
    }
}
