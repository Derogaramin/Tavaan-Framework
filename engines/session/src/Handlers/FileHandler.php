<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session\Handlers;

use Tavaan\Engine\Session\SessionHandlerInterface;

/**
 * درایور فایل (تک‌سرور) با flock و GC lottery.
 *
 * 🔒 فایل‌ها ۰۶۰۰ و خارج از وب (storage/framework/sessions).
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class FileHandler implements SessionHandlerInterface
{
    /**
     * @param array{0: int, 1: int} $lottery [شانس، مخرج]
     */
    public function __construct(
        private readonly string $path,
        private readonly array $lottery = [2, 100],
    ) {
        if (!\is_dir($this->path)) {
            \mkdir($this->path, 0775, true);
        }
    }

    public function read(string $id): array
    {
        $file = $this->filePath($id);
        if (!\is_file($file)) {
            return [];
        }

        $handle = \fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }
        \flock($handle, LOCK_SH);
        $raw = (string) \stream_get_contents($handle);
        \flock($handle, LOCK_UN);
        \fclose($handle);

        $data = \json_decode($raw, true);

        return \is_array($data) ? $data : [];   // 🔒 خراب/دستکاری → خالی
    }

    public function write(string $id, array $data): bool
    {
        $this->gcLottery();

        $file = $this->filePath($id);
        $json = (string) \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $tmp = $file . '.' . \bin2hex(\random_bytes(3)) . '.tmp';
        if (\file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }
        \chmod($tmp, 0600);

        return \rename($tmp, $file);
    }

    public function destroy(string $id): bool
    {
        $file = $this->filePath($id);

        return !\is_file($file) || @\unlink($file);
    }

    public function gc(int $maxLifetimeSeconds): int
    {
        $expire = \time() - $maxLifetimeSeconds;
        $removed = 0;
        foreach (\glob($this->path . '/sess_*') ?: [] as $file) {
            if (\is_file($file) && (\filemtime($file) ?: 0) < $expire) {
                @\unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    /** 🔒 sandbox: فقط hex معتبر (جلوگیری از path traversal از طریق session id). */
    private function filePath(string $id): string
    {
        if (\preg_match('/^[a-f0-9]{1,64}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Invalid session id for file driver.');
        }

        return $this->path . '/sess_' . $id;
    }

    private function gcLottery(): void
    {
        [$chance, $outOf] = $this->lottery;
        if (\random_int(1, $outOf) <= $chance) {
            $this->gc(7200);
        }
    }
}
