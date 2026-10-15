<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt;

use Tavaan\Engine\Crypt\Exceptions\DecryptException;
use Tavaan\Engine\Crypt\Exceptions\EncryptionException;
use Tavaan\Engine\Crypt\Exceptions\KeyMissingException;

/**
 * تَوان | Tavaan — Crypt
 * Encrypter — رمزنگاری AES-256-GCM با قالب نسخه‌دار (DOC-054 §3).
 *
 * قالب: `v2:base64(iv):base64(ciphertext):base64(tag)`
 * 🔒 IV = ۱۲ بایت تصادفی برای هر رمزنگاری؛ tag = ۱۶ بایت (tamper-proof).
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class Crypt
{
    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public function __construct(private readonly KeyRing $keys)
    {
    }

    /** رمزنگاری رشته (با AAD اختیاری). */
    public function encrypt(#[\SensitiveParameter] string $plaintext, string $aad = ''): string
    {
        $version = $this->keys->currentVersion();
        $key = $this->keys->currentKey();

        $iv = \random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = \openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, self::TAG_LENGTH);

        if ($ciphertext === false) {
            throw EncryptionException::encryptFailed(\openssl_error_string() ?: 'unknown');
        }

        return \sprintf(
            'v%d:%s:%s:%s',
            $version,
            \base64_encode($iv),
            \base64_encode($ciphertext),
            \base64_encode($tag),
        );
    }

    /**
     * رمزگشایی؛ در صورت tamper → DecryptException.
     *
     * @throws DecryptException
     * @throws KeyMissingException
     */
    public function decrypt(#[\SensitiveParameter] string $payload, string $aad = ''): string
    {
        $parts = \explode(':', $payload);
        if (\count($parts) !== 4 || !\str_starts_with($parts[0], 'v')) {
            throw DecryptException::invalidFormat();
        }

        $version = (int) \substr($parts[0], 1);
        $key = $this->keys->key($version);
        if ($key === null) {
            throw KeyMissingException::forVersion($version);
        }

        $iv = \base64_decode($parts[1], true);
        $ct = \base64_decode($parts[2], true);
        $tag = \base64_decode($parts[3], true);

        if ($iv === false || $ct === false || $tag === false
            || \strlen($iv) !== self::IV_LENGTH || \strlen($tag) !== self::TAG_LENGTH) {
            throw DecryptException::invalidPayload();
        }

        $plain = \openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($plain === false) {
            throw DecryptException::decryptFailed();   // 🔒 tamper detected
        }

        return $plain;
    }

    /** رمزنگاری آرایه → JSON. @param array<string, mixed> $data */
    public function encryptJson(array $data, string $aad = ''): string
    {
        return $this->encrypt((string) \json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $aad);
    }

    /** @return array<string, mixed> */
    public function decryptJson(string $payload, string $aad = ''): array
    {
        $decoded = \json_decode($this->decrypt($payload, $aad), true, 32, JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    /** HMAC-SHA256 با derive از کلید جاری (برای signed URL/کوکی). */
    public function hmac(string $data, string $context = ''): string
    {
        $key = self::hkdf($this->keys->currentKey(), 'tavaan.hmac.' . $context, 32);

        return \hash_hmac('sha256', $data, $key);
    }

    /** بررسی MAC ثابت‌زمان (timing-safe). */
    public function verifyHmac(string $data, string $mac, string $context = ''): bool
    {
        return \hash_equals($this->hmac($data, $context), $mac);
    }

    /** HKDF-SHA256 (با fallback دستی RFC 5869 — DOC-054 §4.1). */
    public static function hkdf(#[\SensitiveParameter] string $ikm, string $info, int $length = 32, string $salt = ''): string
    {
        if (\function_exists('hash_hkdf')) {
            return \hash_hkdf('sha256', $ikm, $length, $info, $salt);
        }

        $salt = $salt !== '' ? $salt : \str_repeat("\0", 32);
        $prk = \hash_hmac('sha256', $ikm, $salt, true);
        $okm = '';
        $t = '';
        $i = 1;
        while (\strlen($okm) < $length) {
            $t = \hash_hmac('sha256', $t . $info . \chr($i), $prk, true);
            $okm .= $t;
            $i++;
        }

        return \substr($okm, 0, $length);
    }
}
