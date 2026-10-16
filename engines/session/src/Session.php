<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session;

use Tavaan\Support\Arr;

/**
 * تَوان | Tavaan — Session
 * Session — نشست با Flash Data، CSRF token، Regeneration و Timeout.
 *
 * 🔒 امنیت (DOC-041 §7): ID با CSPRNG، رد ID نامعتبر (fixation)،
 * JSON-only serialization، idle/absolute timeout، fingerprint.
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class Session
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    private bool $started = false;

    public function __construct(
        private string $id,
        private readonly string $name,
        private readonly SessionHandlerInterface $handler,
        private readonly int $idleTimeout = 1800,
        private readonly int $absoluteTimeout = 43200,
        private readonly bool $fingerprintEnabled = true,
        private string $fingerprint = '',
    ) {
    }

    // ─── چرخهٔ حیات ───────────────────────────────────────────────────────

    /** بارگذاری داده‌ها + بررسی انقضا. */
    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;

        $this->attributes = $this->handler->read($this->id);

        $now = \time();
        $lastActivity = (int) ($this->attributes['_last_activity'] ?? $now);
        $createdAt = (int) ($this->attributes['_created_at'] ?? $now);

        // idle / absolute timeout
        if (($now - $lastActivity) > $this->idleTimeout || ($now - $createdAt) > $this->absoluteTimeout) {
            $this->flush();
        }

        // 🔒 fingerprint (IP+UA hash) — تغییر → invalidate
        if ($this->fingerprintEnabled && $this->fingerprint !== '') {
            $stored = (string) ($this->attributes['_fingerprint'] ?? '');
            if ($stored !== '' && !\hash_equals($stored, $this->fingerprint)) {
                $this->flush();
            }
        }

        $this->attributes['_last_activity'] = $now;
        $this->attributes['_created_at'] ??= $now;
        if ($this->fingerprintEnabled && $this->fingerprint !== '') {
            $this->attributes['_fingerprint'] = $this->fingerprint;
        }

        // age flash data: new → old
        $this->attributes['_flash']['old'] = $this->attributes['_flash']['new'] ?? [];
        $this->attributes['_flash']['new'] = [];
    }

    /** نوشتن داده‌ها (فقط یک‌بار در terminate — AC-LC-05). */
    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        // حذف flash قدیمی
        foreach ((array) ($this->attributes['_flash']['old'] ?? []) as $key) {
            unset($this->attributes[$key]);
        }

        $this->attributes['_last_activity'] = \time();
        $this->handler->write($this->id, $this->attributes);
    }

    // ─── داده ─────────────────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        Arr::set($this->attributes, $key, $value);
    }

    public function has(string $key): bool
    {
        $value = $this->get($key);

        return $value !== null && $value !== '';
    }

    public function exists(string $key): bool
    {
        return Arr::has($this->attributes, $key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function forget(string $key): void
    {
        Arr::forget($this->attributes, $key);
    }

    public function flush(): void
    {
        $this->attributes = [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->attributes;
    }

    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array = \is_array($array) ? $array : [];
        $array[] = $value;
        $this->put($key, $array);
    }

    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0);
        $value += $amount;
        $this->put($key, $value);

        return $value;
    }

    // ─── Flash ────────────────────────────────────────────────────────────

    public function flash(string $key, mixed $value): void
    {
        $this->put($key, $value);
        $new = (array) ($this->attributes['_flash']['new'] ?? []);
        $new[] = $key;
        $this->attributes['_flash']['new'] = \array_values(\array_unique($new));
    }

    public function reflash(): void
    {
        $old = (array) ($this->attributes['_flash']['old'] ?? []);
        $this->attributes['_flash']['new'] = \array_values(\array_unique([
            ...(array) ($this->attributes['_flash']['new'] ?? []),
            ...$old,
        ]));
        $this->attributes['_flash']['old'] = [];   // 🔒 promoted — save دیگر حذفشان نکند
    }

    /** @param list<string> $keys */
    public function keep(array $keys): void
    {
        foreach ($keys as $key) {
            $old = (array) ($this->attributes['_flash']['old'] ?? []);
            if (($found = \array_search($key, $old, true)) !== false) {
                unset($old[$found]);
                $this->attributes['_flash']['old'] = \array_values($old);
                $new = (array) ($this->attributes['_flash']['new'] ?? []);
                $new[] = $key;
                $this->attributes['_flash']['new'] = \array_values(\array_unique($new));
            }
        }
    }

    /** @param array<string, mixed> $input */
    public function flashInput(array $input): void
    {
        $this->flash('_old_input', $input);
    }

    public function getOldInput(string $key, mixed $default = null): mixed
    {
        return Arr::get((array) $this->get('_old_input', []), $key, $default);
    }

    public function hasOldInput(?string $key = null): bool
    {
        $old = (array) $this->get('_old_input', []);

        return $key === null ? $old !== [] : Arr::has($old, $key);
    }

    // ─── شناسه/امنیت ──────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** 🔒 Regeneration — پس از login الزامی (Session Fixation). */
    public function regenerate(bool $destroy = false): string
    {
        $oldId = $this->id;
        if ($destroy) {
            $this->handler->destroy($oldId);
        }

        $this->id = self::generateId();

        return $this->id;
    }

    public function invalidate(): void
    {
        $this->flush();
        $this->regenerate(destroy: true);
    }

    public function migrate(bool $destroy = false): string
    {
        return $this->regenerate($destroy);
    }

    /** CSPRNG — ۴۰ کاراکتر hex (DOC-041 §5). */
    public static function generateId(): string
    {
        return \bin2hex(\random_bytes(20));
    }

    /** @param string $fingerprint hash بخش‌های منبع (IP|UA) */
    public function setFingerprint(string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public static function isValidId(string $id): bool
    {
        return \preg_match('/^[a-f0-9]{40}$/', $id) === 1;
    }

    // ─── CSRF ─────────────────────────────────────────────────────────────

    public function token(): string
    {
        $token = $this->get('_token');
        if (!\is_string($token) || $token === '') {
            $token = \bin2hex(\random_bytes(20));
            $this->put('_token', $token);
        }

        return $token;
    }

    public function regenerateToken(): string
    {
        $token = \bin2hex(\random_bytes(20));
        $this->put('_token', $token);

        return $token;
    }

    // ─── previous URL ─────────────────────────────────────────────────────

    public function previousUrl(): ?string
    {
        $url = $this->get('_previous.url');

        return \is_string($url) ? $url : null;
    }

    public function setPreviousUrl(string $url): void
    {
        $this->put('_previous.url', $url);
    }
}
