<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Tenancy;

/**
 * تَوان | Tavaan — Contracts
 * TenantInterface — قرارداد موجودِ اجاره‌دار (DOC-082E/DOC-028).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
interface TenantInterface
{
    public function id(): int|string|null;

    public function identifier(): string;

    public function name(): string;

    public function domain(): ?string;

    public function plan(): string;

    public function isActive(): bool;

    /** @return array<string, mixed> */
    public function settings(): array;

    public function setting(string $key, mixed $default = null): mixed;

    /**
     * سهمیهٔ یک منبع.
     *
     * @return array{limit: int, period: string|null}|null
     */
    public function quota(string $resource): ?array;

    /** نام پایگاه‌دادهٔ اختصاصی (استراتژی B). */
    public function databaseName(): string;
}
