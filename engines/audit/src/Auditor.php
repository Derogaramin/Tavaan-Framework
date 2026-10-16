<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Audit
 * Auditor — ثبت غیرقابل‌انکار با **زنجیرهٔ هش** HMAC (DOC-058 §3).
 *
 * ```
 * hash[i] = HMAC-SHA256(key, hash[i-1] | payload[i])
 * ```
 * 🔒 تغییر/حذف هر رکورد → همهٔ hashهای بعدی بی‌اعتبار → `verify()` تشخیص می‌دهد.
 * 🔒 auditKey جدا از APP_KEY (rotation متفاوت).
 * 🔒 exclude_fields پیش از hash ماسک می‌شوند (PII در diff نشت نکند).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class Auditor
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    private readonly string $auditKey;

    /**
     * @param array<string, mixed> $config کلید audit از config
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
        string $appKey = '',
    ) {
        $configured = (string) ($config['key'] ?? '');
        $this->auditKey = $configured !== ''
            ? $configured
            : $this->deriveKey($appKey);   // fallback: HKDF از APP_KEY
    }

    private function deriveKey(string $appKey): string
    {
        if ($appKey === '') {
            throw new \LogicException('Audit key missing: set TVN_AUDIT_KEY or APP_KEY.');
        }

        // HKDF (RFC 5869) با info اختصاصی — کلید audit از APP_KEY مشتق ولی مستقل است
        $key = \str_starts_with($appKey, 'base64:') ? (string) \base64_decode(\substr($appKey, 7), true) : $appKey;

        return \hash_hkdf('sha256', $key, 32, 'tavaan-audit-chain-v1');
    }

    private function table(): string
    {
        return 'tvn_audit_logs';
    }

    /**
     * ثبت یک رویداد ممیزی در زنجیره.
     */
    public function record(AuditEvent $event): string
    {
        $config = $this->configRef();
        if (!(bool) ($config['enabled'] ?? true)) {
            return '';
        }

        $allowed = (array) ($config['events'] ?? []);
        if ($allowed !== [] && !\in_array($event->name, $allowed, true)) {
            return '';
        }

        // 🔒 ماسک فیلدهای حساس پیش از hash/ذخیره
        $exclude = (array) ($config['exclude_fields'] ?? ['password', 'remember_token', 'secret', 'token']);
        $old = $this->maskExcluded($event->oldValues, $exclude);
        $new = $this->maskExcluded($event->newValues, $exclude);

        $createdAt = ($event->createdAt ?? Clock::utcNow())->format('Y-m-d H:i:s.u');
        $userId = $event->userId;

        // payload کانونیکال (همان چیزی که verify بازسازی می‌کند)
        $payload = $this->canonicalPayload(
            $event->name,
            $event->auditableType,
            $event->auditableId,
            $userId,
            $old,
            $new,
            $createdAt,
        );

        $prevHash = $this->lastHash();
        $hash = \hash_hmac('sha256', $prevHash . '|' . $payload, $this->auditKey);
        $auditId = Ulid::generate()->toString();

        $this->db->table($this->table())->insert([
            'audit_id' => $auditId,
            'event' => $event->name,
            'auditable_type' => $event->auditableType,
            'auditable_id' => $event->auditableId,
            'user_id' => $userId,
            'tenant_id' => $event->tenantId,
            'old_values' => $old === [] ? null : (string) \json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'new_values' => $new === [] ? null : (string) \json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'changed_fields' => (string) \json_encode(\array_keys($new), JSON_THROW_ON_ERROR),
            'ip' => $event->ip,
            'user_agent' => $event->userAgent === null ? null : \substr($event->userAgent, 0, 255),
            'trace_id' => $event->traceId,
            'prev_hash' => $prevHash,
            'hash' => $hash,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch('audit.recorded', [
            'audit_id' => $auditId,
            'event' => $event->name,
            'type' => $event->auditableType,
        ]);

        return $auditId;
    }

    /**
     * بازبینی یکپارچگی کل زنجیره (tvn audit:verify).
     */
    public function verify(): VerifyResult
    {
        $prev = self::GENESIS_HASH;
        $checked = 0;

        // پیمایش ترتیبی بدون بارگیری همه در حافظه (cursor)
        foreach ($this->db->cursor('select * from ' . $this->table() . ' order by id asc') as $row) {
            $checked++;

            if ((string) $row['prev_hash'] !== $prev) {
                return VerifyResult::tampered(
                    (string) $row['audit_id'],
                    'prev_hash mismatch (رکورد حذف/جابه‌جا شده است)',
                    $checked,
                );
            }

            $payload = $this->canonicalPayload(
                (string) $row['event'],
                (string) $row['auditable_type'],
                (string) $row['auditable_id'],
                $row['user_id'] === null ? null : (int) $row['user_id'],
                $this->decodeJson($row['old_values'] ?? null),
                $this->decodeJson($row['new_values'] ?? null),
                (string) $row['created_at'],
            );

            $expected = \hash_hmac('sha256', $prev . '|' . $payload, $this->auditKey);
            if (!\hash_equals($expected, (string) $row['hash'])) {
                return VerifyResult::tampered(
                    (string) $row['audit_id'],
                    'hash mismatch (محتوای رکورد دستکاری شده است)',
                    $checked,
                );
            }

            $prev = (string) $row['hash'];
        }

        return VerifyResult::intact($checked);
    }

    public function lastHash(): string
    {
        $row = $this->db->selectOne('select hash from ' . $this->table() . ' order by id desc limit 1');

        return $row === null ? self::GENESIS_HASH : (string) $row['hash'];
    }

    /**
     * خروجی‌گیری بازه (JSON lines).
     *
     * @return list<array<string, mixed>>
     */
    public function export(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $limit = 10000): array
    {
        $query = $this->db->table($this->table())->orderBy('id');

        if ($from !== null) {
            $query->where('created_at', '>=', $from->format('Y-m-d H:i:s'));
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to->format('Y-m-d H:i:s'));
        }

        $rows = $query->limit($limit)->get();

        // 🔒 خودِ export هم ممیزی می‌شود (نشت داده)
        $this->record(new AuditEvent('export', 'audit_logs', (string) \count($rows)));

        return $rows;
    }

    /** حذف رکوردهای قدیمی‌تر از retention. */
    public function prune(int $olderThanDays): int
    {
        $cutoff = Clock::utcNow()->sub(new \DateInterval('P' . \max(1, $olderThanDays) . 'D'))->format('Y-m-d H:i:s');

        return $this->db->table($this->table())->where('created_at', '<', $cutoff)->delete();
    }

    /** @return array<string, mixed>|null */
    public function find(string $auditId): ?array
    {
        return $this->db->table($this->table())->where('audit_id', $auditId)->first();
    }

    // ─── درونی ────────────────────────────────────────────────────────────

    /**
     * payload کانونیکال — در record و verify **دقیقاً** یکسان ساخته می‌شود.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function canonicalPayload(
        string $event,
        string $type,
        string $id,
        ?int $userId,
        array $old,
        array $new,
        string $createdAt,
    ): string {
        return (string) \json_encode([
            'event' => $event,
            'type' => $type,
            'id' => $id,
            'user' => $userId,
            'old' => $old === [] ? null : $old,
            'new' => $new === [] ? null : $new,
            'ts' => $createdAt,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $exclude
     *
     * @return array<string, mixed>
     */
    private function maskExcluded(array $values, array $exclude): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $keyName = \strtolower((string) $key);
            $leaf = \substr($keyName, (int) \strrpos($keyName, '.') + 1);

            $out[$key] = (\in_array($leaf, $exclude, true) || \in_array($keyName, $exclude, true))
                ? '[REDACTED]'
                : $value;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        try {
            $decoded = \json_decode((string) $raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function configRef(): array
    {
        return $this->config;
    }
}
