<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — Tenancy
 * Tenant — موجودِ اجاره‌دار (رکورد tvn_tenants — DOC-028 §2.1).
 *
 * به‌عنوان مدل پیش‌فرض؛ اپلیکیشن می‌تواند کلاس خودش را در
 * `config('tenancy.tenant_model')` معرفی کند (باید از این کلاس ارث ببرد
 * یا TenantInterface را پیاده کند).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
class Tenant extends Model implements TenantInterface
{
    protected string $table = 'tvn_tenants';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['identifier', 'name', 'domain', 'plan', 'settings', 'is_active', 'quota'];

    /** @var array<string, string> */
    protected array $casts = [
        'is_active' => 'bool',
    ];

    public function id(): int|string|null
    {
        return $this->getKey();
    }

    public function identifier(): string
    {
        return (string) ($this->getAttribute('identifier') ?? '');
    }

    public function name(): string
    {
        return (string) ($this->getAttribute('name') ?? $this->identifier());
    }

    public function domain(): ?string
    {
        $domain = $this->getAttribute('domain');

        return $domain === null || $domain === '' ? null : (string) $domain;
    }

    public function plan(): string
    {
        return (string) ($this->getAttribute('plan') ?? 'free');
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute('is_active');
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return $this->decodedJson('settings');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings();

        // کلید عیناً موجود (مثلاً «mail.from» به‌عنوان یک کلید واحد)
        if (\array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        // پشتیبانی از کلید نقطه‌ای (a.b.c)
        $value = $settings;
        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function quotas(): array
    {
        return $this->decodedJson('quota');
    }

    /** @return array{limit: int, period: string|null}|null */
    public function quota(string $resource): ?array
    {
        $quota = $this->quotas()[$resource] ?? null;
        if (!\is_array($quota) || !isset($quota['limit'])) {
            return null;
        }

        return [
            'limit' => (int) $quota['limit'],
            'period' => isset($quota['period']) ? (string) $quota['period'] : null,
        ];
    }

    /** نام پایگاه‌دادهٔ اختصاصی (استراتژی B — DOC-028 §3). */
    public function databaseName(): string
    {
        $explicit = $this->setting('db_database');
        if (\is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        return 'tenant_' . \preg_replace('/[^A-Za-z0-9_]/', '', $this->identifier());
    }

    /** @return array<string, mixed> */
    private function decodedJson(string $attribute): array
    {
        $raw = $this->getAttribute($attribute);
        if (\is_array($raw)) {
            return $raw;
        }
        if (!\is_string($raw) || $raw === '') {
            return [];
        }
        try {
            $decoded = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }
}
