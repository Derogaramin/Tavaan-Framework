<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt\Tests\Unit;

use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Crypt\Exceptions\DecryptException;
use Tavaan\Engine\Crypt\Exceptions\KeyMissingException;
use Tavaan\Engine\Crypt\KeyRing;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Crypt — DOC-054.
 */
final class CryptTest extends TestCase
{
    private function crypt(string $key = ''): Crypt
    {
        return new Crypt(new KeyRing($key !== '' ? $key : KeyRing::generate()));
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $c = $this->crypt();
        foreach (['', 'سلام تَوان 🎉', \str_repeat('long-', 2000), "\x00\x01binary"] as $plain) {
            $cipher = $c->encrypt($plain);
            $this->assertNotSame($plain, $cipher);
            $this->assertStringContainsString('v1:', $cipher);
            $this->assertSame($plain, $c->decrypt($cipher));
        }
    }

    public function testIvIsRandomPerEncryption(): void
    {
        $c = $this->crypt();
        $a = $c->encrypt('same');
        $b = $c->encrypt('same');
        $this->assertNotSame($a, $b, 'IV must be random per encryption');
        $this->assertSame('same', $c->decrypt($a));
        $this->assertSame('same', $c->decrypt($b));
    }

    public function testTamperDetected(): void
    {
        $c = $this->crypt();
        $cipher = $c->encrypt('secret message');

        $parts = \explode(':', $cipher);
        $parts[2] = \strrev($parts[2]);
        $this->expectException(DecryptException::class);
        $c->decrypt(\implode(':', $parts));
    }

    public function testInvalidFormatRejected(): void
    {
        $c = $this->crypt();
        foreach (['garbage', 'v1:onlytwo', 'x:a:b:c'] as $bad) {
            try {
                $c->decrypt($bad);
                self::fail('expected DecryptException for: ' . $bad);
            } catch (DecryptException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testAadBinding(): void
    {
        $c = $this->crypt();
        $cipher = $c->encrypt('data', 'context-A');
        $this->assertSame('data', $c->decrypt($cipher, 'context-A'));

        $this->expectException(DecryptException::class);
        $c->decrypt($cipher, 'context-B');
    }

    public function testKeyRotation(): void
    {
        $k1 = KeyRing::generate();
        $k2 = KeyRing::generate();

        $old = new Crypt(new KeyRing($k1));
        $oldCipher = $old->encrypt('old data');

        $rotated = new Crypt(new KeyRing($k1 . ',' . $k2));
        $this->assertSame('old data', $rotated->decrypt($oldCipher));
        $this->assertStringContainsString('v2:', $rotated->encrypt('new data'));
    }

    public function testMissingKeyVersion(): void
    {
        // سناریوی واقعی rotation: حلقهٔ قدیمی فقط v1 دارد؛ payload با v2 رمز شده
        $k1 = KeyRing::generate();
        $k2 = KeyRing::generate();

        $oldRing = new Crypt(new KeyRing($k1));
        $newRing = new Crypt(new KeyRing($k1 . ',' . $k2));

        $cipher = $newRing->encrypt('x');
        $this->assertStringContainsString('v2:', $cipher);

        $this->expectException(KeyMissingException::class);
        $oldRing->decrypt($cipher);
    }

    public function testWrongKeySameVersionFails(): void
    {
        $a = new Crypt(new KeyRing(KeyRing::generate()));
        $b = new Crypt(new KeyRing(KeyRing::generate()));

        $this->expectException(DecryptException::class);
        $b->decrypt($a->encrypt('secret'));
    }

    public function testNoKeyConfigured(): void
    {
        $c = new Crypt(new KeyRing(''));
        $this->expectException(KeyMissingException::class);
        $c->encrypt('x');
    }

    public function testHmacConstantTime(): void
    {
        $c = $this->crypt();
        $mac = $c->hmac('payload', 'ctx');
        $this->assertSame(64, \strlen($mac));
        $this->assertTrue($c->verifyHmac('payload', $mac, 'ctx'));
        $this->assertFalse($c->verifyHmac('payload2', $mac, 'ctx'));
        $this->assertFalse($c->verifyHmac('payload', $mac, 'other-ctx'));
    }

    public function testJsonEncryption(): void
    {
        $c = $this->crypt();
        $data = ['user' => 'امین', 'roles' => ['admin'], 'n' => 42];
        $cipher = $c->encryptJson($data);
        $this->assertSame($data, $c->decryptJson($cipher));
    }

    public function testHkdfMatchesHashHkdf(): void
    {
        $ikm = 'input-key-material';
        $derived = Crypt::hkdf($ikm, 'info-x', 32, 'salt-y');
        $this->assertSame(32, \strlen($derived));
        if (\function_exists('hash_hkdf')) {
            $this->assertSame(\hash_hkdf('sha256', $ikm, 32, 'info-x', 'salt-y'), $derived);
        }
        // determinism
        $this->assertSame($derived, Crypt::hkdf($ikm, 'info-x', 32, 'salt-y'));
    }

    public function testKeyRingBase64Format(): void
    {
        $ring = new KeyRing('base64:' . \base64_encode(\str_repeat('k', 32)));
        $this->assertTrue($ring->hasKeys());
        $this->assertSame(1, $ring->currentVersion());
        $this->assertSame(32, \strlen($ring->currentKey()));
    }

    public function testGeneratedKeyFormat(): void
    {
        $key = KeyRing::generate();
        $this->assertMatchesRegex('/^base64:[A-Za-z0-9+\/=]{44}$/', $key);
    }
}
