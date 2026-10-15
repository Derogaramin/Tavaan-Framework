<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error\Tests\Unit;

use Tavaan\Core\Error\LogMasker;
use Tavaan\Testing\TestCase;

/**
 * تست LogMasker — AC-ERR-05.
 */
final class LogMaskerTest extends TestCase
{
    public function testSensitiveKeysMasked(): void
    {
        $masked = LogMasker::mask([
            'password' => 'p@ss', 'access_token' => 'abc', 'card_number' => '123',
            'user' => 'amin', 'national_id' => '0084575089',
        ]);
        $this->assertSame('********', $masked['password']);
        $this->assertSame('********', $masked['access_token']);
        $this->assertSame('********', $masked['card_number']);
        $this->assertSame('********', $masked['national_id']);
        $this->assertSame('amin', $masked['user']);
    }

    public function testNestedArraysMasked(): void
    {
        $masked = LogMasker::mask(['user' => ['profile' => ['password' => 'x', 'name' => 'n']]]);
        $this->assertSame('********', $masked['user']['profile']['password']);
        $this->assertSame('n', $masked['user']['profile']['name']);
    }

    public function testCardNumberPatternPartial(): void
    {
        $masked = LogMasker::maskString('payment with 6219861034567890 done');
        $this->assertStringContainsString('62************90', $masked);
    }

    public function testMobilePattern(): void
    {
        $masked = LogMasker::maskString('contact 09121234567 now');
        $this->assertStringContainsString('09*******67', $masked);
    }

    public function testEmailPattern(): void
    {
        $masked = LogMasker::maskString('mail to amin.derogar@example.com');
        $this->assertStringNotContainsString('amin.derogar@example.com', $masked);
        $this->assertMatchesRegex('/am\*+om/', $masked);
    }

    public function testJwtPattern(): void
    {
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U';
        $masked = LogMasker::maskString('token: ' . $jwt);
        $this->assertStringNotContainsString($jwt, $masked);
    }

    public function testDepthTruncation(): void
    {
        $deep = ['l1' => ['l2' => ['l3' => ['l4' => ['l5' => ['l6' => ['l7' => ['l8' => ['l9' => ['x' => 1]]]]]]]]]];
        $masked = LogMasker::mask($deep);
        $json = (string) \json_encode($masked);
        $this->assertStringContainsString('truncated', $json);
    }

    public function testCustomKeysAndPatterns(): void
    {
        $masker = new LogMasker(['custom_secret_field'], ['custom' => '/\bTVN-[0-9]{6}\b/']);
        $masked = $masker->maskData(['custom_secret_field' => 'v', 'code' => 'TVN-123456']);
        $this->assertSame('********', $masked['custom_secret_field']);
        $this->assertStringNotContainsString('TVN-123456', $masked['code']);
    }

    public function testObjectsRedacted(): void
    {
        $masked = LogMasker::mask(['obj' => new \stdClass()]);
        $this->assertStringContainsString('[object:', (string) $masked['obj']);
    }
}
