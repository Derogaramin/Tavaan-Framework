<?php

declare(strict_types=1);

namespace Tavaan\Engine\Hash\Tests\Unit;

use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Hash\Argon2idHasher;
use Tavaan\Engine\Hash\BcryptHasher;
use Tavaan\Engine\Hash\HashManager;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Hash — DOC-055 §7 (T-01..T-05) + AC-HASH-06.
 */
final class HashTest extends TestCase
{
    public function testMakeCheckRoundtrip(): void
    {
        // T-01
        $hasher = new Argon2idHasher(memoryCost: 1024, timeCost: 2);   // پارامتر سبک برای سرعت تست

        foreach (['password123', 'رمز-فارسی-۱۲۳', \str_repeat('x', 200)] as $plain) {
            $hash = $hasher->make($plain);
            $this->assertTrue($hasher->check($plain, $hash));
        }
    }

    public function testArgon2idPrefix(): void
    {
        // AC-HASH-06
        if (!\defined('PASSWORD_ARGON2ID')) {
            $this->assertTrue(true);   // محیط بدون argon2 → fallback

            return;
        }

        $hash = (new Argon2idHasher(memoryCost: 1024, timeCost: 2))->make('test');
        $this->assertTrue(\str_starts_with($hash, '$argon2id$'), 'hash must start with $argon2id$');
    }

    public function testWrongPasswordFails(): void
    {
        // T-02
        $hasher = new Argon2idHasher(memoryCost: 1024, timeCost: 2);
        $hash = $hasher->make('correct-horse');

        $this->assertFalse($hasher->check('wrong-horse', $hash));
        $this->assertFalse($hasher->check('', $hash));
        $this->assertFalse($hasher->check('correct-horse', ''));
    }

    public function testNeedsRehashOnWeakParams(): void
    {
        // T-03
        $weak = new Argon2idHasher(memoryCost: 1024, timeCost: 2);
        $strong = new Argon2idHasher(memoryCost: 65536, timeCost: 4);

        $hash = $weak->make('pw');
        $this->assertTrue($strong->needsRehash($hash), 'hash with weaker params must need rehash');
        $this->assertFalse($weak->needsRehash($hash));
    }

    public function testBcryptFallback(): void
    {
        // T-04
        $bcrypt = new BcryptHasher(cost: 10);
        $hash = $bcrypt->make('fallback-pw');

        $this->assertTrue(\str_starts_with($hash, '$2y$'));
        $this->assertTrue($bcrypt->check('fallback-pw', $hash));
        $this->assertFalse($bcrypt->check('nope', $hash));

        // cost قدیمی‌تر → needsRehash
        $stronger = new BcryptHasher(cost: 12);
        $this->assertTrue($stronger->needsRehash($hash));
    }

    public function testBcryptRejectsWeakCost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BcryptHasher(cost: 4);
    }

    public function testSaltRandomness(): void
    {
        // T-05: دو hash از یک رمز متفاوت (salt)
        $hasher = new Argon2idHasher(memoryCost: 1024, timeCost: 2);
        $a = $hasher->make('same-password');
        $b = $hasher->make('same-password');

        $this->assertNotSame($a, $b);
        $this->assertTrue($hasher->check('same-password', $a));
        $this->assertTrue($hasher->check('same-password', $b));
    }

    public function testInfo(): void
    {
        $hasher = new Argon2idHasher(memoryCost: 1024, timeCost: 2);
        $info = $hasher->info($hasher->make('x'));

        $this->assertArrayHasKey('algoName', $info);
        $this->assertTrue(\in_array($info['algoName'], ['argon2id', 'bcrypt'], true));
    }

    public function testManagerDriverSelection(): void
    {
        $config = new Repository(['hash' => [
            'driver' => 'bcrypt',
            'bcrypt' => ['cost' => 10],
            'argon2id' => ['memory_cost' => 1024, 'time_cost' => 2, 'threads' => 1],
        ]]);

        $manager = new HashManager($config);
        $hash = $manager->make('managed');
        $this->assertTrue(\str_starts_with($hash, '$2y$'));
        $this->assertTrue($manager->check('managed', $hash));

        $argon = new HashManager(new Repository(['hash' => [
            'driver' => 'argon2id',
            'argon2id' => ['memory_cost' => 1024, 'time_cost' => 2, 'threads' => 1],
        ]]));
        $hash2 = $argon->make('managed');
        if (\defined('PASSWORD_ARGON2ID')) {
            $this->assertTrue(\str_starts_with($hash2, '$argon2id$'));
        }
        $this->assertTrue($argon->check('managed', $hash2));
    }

    public function testUnknownDriverRejected(): void
    {
        $manager = new HashManager(new Repository(['hash' => ['driver' => 'md5']]));

        $this->expectException(\InvalidArgumentException::class);
        $manager->driver();
    }
}
