<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Engine\Backup\Exceptions\BackupException;

/**
 * تَوان | Tavaan — Backup
 * BackupEncrypter — رمزنگاری AES-256-GCM با **کلید جدا از APP_KEY** (DOC-068 §5).
 *
 * 🔒 اگر APP_KEY لو برود، backup با کلید اختصاصی امن می‌ماند.
 * قالب: `TVNBKP1\n` + IV(12B) + Tag(16B) + ciphertext — با AAD ثابت `tavaan-backup`.
 *
 * وقتی کلید اختصاصی تنظیم نشده → fallback به موتور crypt (APP_KEY) با
 * AAD یکسان (مستند: امنیت ضعیف‌تر، ولی هرگز plaintext روی دیسک).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupEncrypter
{
    private const MAGIC = "TVNBKP1\n";

    private const AAD = 'tavaan-backup';

    /** کلید ۳۲ بایتی مشتق‌شده (یا null → fallback به crypt) */
    private readonly ?string $key;

    public function __construct(
        #[\SensitiveParameter] private readonly string $configuredKey = '',
        private readonly ?EncrypterInterface $crypt = null,
    ) {
        $this->key = $configuredKey !== '' ? \hash('sha256', $configuredKey, true) : null;
    }

    public function usesDedicatedKey(): bool
    {
        return $this->key !== null;
    }

    public function encrypt(#[\SensitiveParameter] string $content): string
    {
        if ($this->key === null) {
            return $this->encryptViaCrypt($content);
        }

        $iv = \random_bytes(12);
        $tag = '';
        $cipher = \openssl_encrypt($content, 'aes-256-gcm', $this->key, \OPENSSL_RAW_DATA, $iv, $tag, self::AAD, 16);
        if ($cipher === false) {
            throw BackupException::storeFailed('openssl_encrypt failed');
        }

        return self::MAGIC . $iv . $tag . $cipher;
    }

    public function decrypt(#[\SensitiveParameter] string $payload): string
    {
        if (!\str_starts_with($payload, self::MAGIC)) {
            return $this->decryptViaCrypt($payload);
        }

        if ($this->key === null) {
            throw BackupException::keyMissing();
        }

        $body = \substr($payload, \strlen(self::MAGIC));
        if (\strlen($body) < 28) {
            throw BackupException::decryptFailed('', 'payload too short');
        }

        $iv = \substr($body, 0, 12);
        $tag = \substr($body, 12, 16);
        $cipher = \substr($body, 28);

        $plain = \openssl_decrypt($cipher, 'aes-256-gcm', $this->key, \OPENSSL_RAW_DATA, $iv, $tag, self::AAD);
        if ($plain === false) {
            throw BackupException::decryptFailed('', 'GCM authentication failed');
        }

        return $plain;
    }

    private function encryptViaCrypt(string $content): string
    {
        if ($this->crypt === null) {
            throw BackupException::keyMissing();
        }

        return $this->crypt->encrypt($content, self::AAD);
    }

    private function decryptViaCrypt(string $payload): string
    {
        if ($this->crypt === null) {
            throw BackupException::keyMissing();
        }

        try {
            return $this->crypt->decrypt($payload, self::AAD);
        } catch (\Throwable $e) {
            throw BackupException::decryptFailed('', $e->getMessage());
        }
    }
}
