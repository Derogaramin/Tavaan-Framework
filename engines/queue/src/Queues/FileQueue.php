<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Queues;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Queue\Exceptions\QueueException;
use Tavaan\Engine\Queue\Jobs\FileJob;
use Tavaan\Engine\Queue\Serializer;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Queue
 * FileQueue — درایور file (ساده، بدون DB/Redis؛ DOC-061 §2).
 *
 * چیدمان: `{root}/{queue}/pending/{availableAt}_{ulid}.json` و `.../reserved/`.
 * 🔒 رزرو اتمیک با `rename()` — اگر worker دیگری زودتر برداشته باشد،
 * rename شکست می‌خورد و فایل بعدی بررسی می‌شود.
 *
 * هر فایل یک envelope است: `{"attempts":N,"payload":"<json job>"}` —
 * attempts با release/retry حفظ می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class FileQueue extends Queue
{
    private readonly string $root;

    /** @param array<string, mixed> $config */
    public function __construct(
        string $connectionName,
        array $config,
        Serializer $serializer,
        ?ContainerInterface $container = null,
    ) {
        parent::__construct($connectionName, $config, $serializer, $container);

        $this->root = \rtrim((string) ($config['path'] ?? \sys_get_temp_dir() . '/tavaan-queue'), '/\\');
    }

    public function pushRaw(string $payload, ?string $queue = null): int|string|null
    {
        return $this->writePending($queue ?? $this->queueFromPayload($payload), Clock::timestamp(), $payload, 0);
    }

    public function laterRaw(int $availableAt, string $payload, ?string $queue = null): int|string|null
    {
        return $this->writePending($queue ?? $this->queueFromPayload($payload), $availableAt, $payload, 0);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        $queue ??= $this->defaultQueue();
        $now = Clock::timestamp();

        foreach ($this->pendingFiles($queue) as $file) {
            $base = \basename($file);
            if ((int) \substr($base, 0, 10) > $now) {
                continue;   // فایل‌ها مرتب‌شده‌اند — بقیه دیرترند
            }

            $reservedPath = $this->reservedDir($queue) . '/' . $base;
            // 🔒 رزرو اتمیک: اگر مقصد وجود داشته باشد یا منبع پریده باشد → رد
            if (\file_exists($reservedPath) || !@\rename($file, $reservedPath)) {
                continue;
            }

            [$rawPayload, $attempts] = $this->readEnvelope($reservedPath);
            $attempts++;

            // envelope به‌روز (attempts) روی فایل reserved بازنویسی می‌شود
            \file_put_contents($reservedPath, $this->envelope($rawPayload, $attempts), \LOCK_EX);

            return new FileJob($this->container, $this->connectionName, $queue, $rawPayload, $reservedPath, $attempts, $this);
        }

        return null;
    }

    public function size(?string $queue = null): int
    {
        return \count($this->pendingFiles($queue ?? $this->defaultQueue()));
    }

    /** حذف فایل reserved پس از پردازش. */
    public function deleteReserved(string $reservedPath): void
    {
        @\unlink($reservedPath);
    }

    /** برگرداندن job به pending با تأخیر (attempts حفظ می‌شود). */
    public function releaseReserved(string $reservedPath, string $queue, string $payload, int $attempts, int $delay): void
    {
        $this->writePending($queue, Clock::timestamp() + \max(0, $delay), $payload, $attempts);
        @\unlink($reservedPath);
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function writePending(string $queue, int $availableAt, string $payload, int $attempts): string
    {
        $dir = $this->pendingDir($queue);
        $this->ensureDir($dir);

        $name = \sprintf('%010d_%s.json', \max(0, $availableAt), (string) Ulid::generate());
        $full = $dir . '/' . $name;

        // 🔒 نوشتن اتمیک: tmp + rename
        $tmp = $full . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (\file_put_contents($tmp, $this->envelope($payload, $attempts), \LOCK_EX) === false) {
            throw QueueException::for('queue.file_write_failed', ['dir' => $dir]);
        }
        @\chmod($tmp, 0664);
        if (!\rename($tmp, $full)) {
            @\unlink($tmp);

            throw QueueException::for('queue.file_write_failed', ['dir' => $dir]);
        }

        return $name;
    }

    private function envelope(string $payload, int $attempts): string
    {
        return \json_encode(['attempts' => $attempts, 'payload' => $payload], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /** @return array{0: string, 1: int} [payload, attempts] */
    private function readEnvelope(string $path): array
    {
        $content = (string) @\file_get_contents($path);
        $decoded = \json_decode($content, true);

        if (\is_array($decoded) && \is_string($decoded['payload'] ?? null)) {
            return [$decoded['payload'], (int) ($decoded['attempts'] ?? 0)];
        }

        return [$content, 0];   // سازگاری با فایل قدیمی بدون envelope
    }

    /** @return list<string> فایل‌های pending مرتب‌شده (نام = زمان) */
    private function pendingFiles(string $queue): array
    {
        $dir = $this->pendingDir($queue);
        if (!\is_dir($dir)) {
            return [];
        }

        $files = \glob($dir . '/*.json') ?: [];
        \sort($files);

        return $files;
    }

    private function pendingDir(string $queue): string
    {
        return $this->root . '/' . $this->safeQueueName($queue) . '/pending';
    }

    private function reservedDir(string $queue): string
    {
        return $this->root . '/' . $this->safeQueueName($queue) . '/reserved';
    }

    /** 🔒 نام صف هرگز نباید path traversal بسازد. */
    private function safeQueueName(string $queue): string
    {
        $safe = \preg_replace('/[^A-Za-z0-9._-]/', '_', $queue) ?? 'default';

        return $safe === '' ? 'default' : $safe;
    }

    private function ensureDir(string $dir): void
    {
        if (!\is_dir($dir) && (!\mkdir($dir, 0775, true) && !\is_dir($dir))) {
            throw QueueException::for('queue.file_write_failed', ['dir' => $dir], 'پوشهٔ صف ساخته نشد؛ مجوزها را بررسی کنید.');
        }
        // پوشهٔ reserved نیز آماده باشد
        $reserved = \dirname($dir) . '/reserved';
        if (!\is_dir($reserved)) {
            \mkdir($reserved, 0775, true);
        }
    }

    private function queueFromPayload(string $payload): string
    {
        $decoded = \json_decode($payload, true);

        return \is_array($decoded) && \is_string($decoded['queue'] ?? null) && $decoded['queue'] !== ''
            ? $decoded['queue']
            : $this->defaultQueue();
    }
}
