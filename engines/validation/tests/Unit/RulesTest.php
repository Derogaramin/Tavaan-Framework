<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Tests\Unit;

use Tavaan\Engine\Validation\Validator;
use Tavaan\Testing\TestCase;

/**
 * تست قواعد داخلی — DOC-051 §11 T-01 (هر قاعده: پاس و فیل).
 */
final class RulesTest extends TestCase
{
    /** @param array<string, string|list<mixed>> $rules */
    private function check(array $data, array $rules): bool
    {
        $v = new Validator($data, $rules);

        return $v->passes();
    }

    public function testPresenceRules(): void
    {
        $this->assertTrue($this->check(['a' => 'x'], ['a' => 'required']));
        $this->assertFalse($this->check(['a' => ''], ['a' => 'required']));
        $this->assertFalse($this->check([], ['a' => 'required']));
        $this->assertFalse($this->check(['a' => []], ['a' => 'required']));

        $this->assertTrue($this->check(['a' => 'x', 'b' => '1'], ['a' => 'required_if:b,1']));
        $this->assertFalse($this->check(['b' => '1'], ['a' => 'required_if:b,1']));
        $this->assertTrue($this->check(['b' => '2'], ['a' => 'required_if:b,1']));

        $this->assertTrue($this->check(['b' => '1'], ['a' => 'required_unless:b,1']));
        $this->assertFalse($this->check(['b' => '2'], ['a' => 'required_unless:b,1']));

        $this->assertTrue($this->check(['a' => ''], ['a' => 'nullable|string']));
        $this->assertTrue($this->check([], ['a' => 'sometimes|required']));
        $this->assertFalse($this->check(['a' => ''], ['a' => 'sometimes|required']));
        $this->assertTrue($this->check(['a' => null, 'b' => null], ['a' => 'required_with:b']));
        $this->assertFalse($this->check(['a' => null, 'b' => 'x'], ['a' => 'required_with:b']));
        $this->assertFalse($this->check(['a' => null], ['a' => 'required_without:b']));
        $this->assertTrue($this->check(['a' => null, 'b' => 'x'], ['a' => 'required_without:b']));
        $this->assertTrue($this->check(['a' => null], ['a' => 'present']));
        $this->assertFalse($this->check([], ['a' => 'present']));
        $this->assertFalse($this->check(['a' => ''], ['a' => 'filled']));
    }

    public function testTypeRules(): void
    {
        $this->assertTrue($this->check(['a' => 'x'], ['a' => 'string']));
        $this->assertFalse($this->check(['a' => 5], ['a' => 'string']));
        $this->assertTrue($this->check(['a' => 5], ['a' => 'integer']));
        $this->assertTrue($this->check(['a' => '5'], ['a' => 'integer']));
        $this->assertFalse($this->check(['a' => '5.5'], ['a' => 'integer']));
        $this->assertTrue($this->check(['a' => '5.5'], ['a' => 'numeric']));
        $this->assertFalse($this->check(['a' => 'x5'], ['a' => 'numeric']));
        $this->assertTrue($this->check(['a' => true], ['a' => 'boolean']));
        $this->assertTrue($this->check(['a' => '1'], ['a' => 'boolean']));
        $this->assertFalse($this->check(['a' => 'yes'], ['a' => 'boolean']));
        $this->assertTrue($this->check(['a' => [1]], ['a' => 'array']));
        $this->assertTrue($this->check(['a' => new \stdClass()], ['a' => 'object']));
        $this->assertTrue($this->check(['a' => '2026-01-01'], ['a' => 'date']));
        $this->assertFalse($this->check(['a' => 'nope'], ['a' => 'date']));
        $this->assertTrue($this->check(['a' => '12345'], ['a' => 'digits:5']));
        $this->assertFalse($this->check(['a' => '1234'], ['a' => 'digits:5']));
        $this->assertTrue($this->check(['a' => '1234'], ['a' => 'digits_between:3,5']));
    }

    public function testSizeRules(): void
    {
        $this->assertTrue($this->check(['a' => 'hello'], ['a' => 'string|min:3']));
        $this->assertFalse($this->check(['a' => 'hi'], ['a' => 'string|min:3']));
        $this->assertTrue($this->check(['a' => 5], ['a' => 'numeric|min:3|max:10']));
        $this->assertFalse($this->check(['a' => 50], ['a' => 'numeric|min:3|max:10']));
        $this->assertTrue($this->check(['a' => [1, 2, 3]], ['a' => 'array|size:3']));
        $this->assertTrue($this->check(['a' => 'سلام دنیا'], ['a' => 'min:9']));   // mb length
        $this->assertTrue($this->check(['a' => 'abcd'], ['a' => 'length:4']));
        $this->assertFalse($this->check(['a' => 'abc'], ['a' => 'length:4']));
        $this->assertTrue($this->check(['a' => 'سلام'], ['a' => 'between:1,10']));
    }

    public function testComparisonRules(): void
    {
        $this->assertTrue($this->check(['a' => 5, 'b' => 3], ['a' => 'numeric|gt:b']));
        $this->assertFalse($this->check(['a' => 2, 'b' => 3], ['a' => 'numeric|gt:b']));
        $this->assertTrue($this->check(['a' => 3, 'b' => 3], ['a' => 'numeric|gte:b|lte:b']));
        $this->assertTrue($this->check(['a' => 2, 'b' => 3], ['a' => 'numeric|lt:b']));
        $this->assertTrue($this->check(['a' => 'x', 'a_confirmation' => 'x'], ['a' => 'confirmed']));
        $this->assertFalse($this->check(['a' => 'x', 'a_confirmation' => 'y'], ['a' => 'confirmed']));
        $this->assertTrue($this->check(['a' => 'x', 'b' => 'x'], ['a' => 'same:b']));
        $this->assertTrue($this->check(['a' => 'x', 'b' => 'y'], ['a' => 'different:b']));
        $this->assertTrue($this->check(['a' => 'yes'], ['a' => 'accepted']));
        $this->assertTrue($this->check(['a' => 'no'], ['a' => 'declined']));
        $this->assertTrue($this->check(['a' => 'b'], ['a' => 'in:a,b,c']));
        $this->assertFalse($this->check(['a' => 'z'], ['a' => 'in:a,b,c']));
        $this->assertTrue($this->check(['a' => 'z'], ['a' => 'not_in:a,b,c']));
    }

    public function testFormatRules(): void
    {
        $this->assertTrue($this->check(['a' => 'user@example.com'], ['a' => 'email']));
        $this->assertFalse($this->check(['a' => 'user@'], ['a' => 'email']));
        $this->assertTrue($this->check(['a' => 'https://example.com/x'], ['a' => 'url']));
        $this->assertFalse($this->check(['a' => 'javascript:alert(1)'], ['a' => 'url']));
        $this->assertTrue($this->check(['a' => '550e8400-e29b-41d4-a716-446655440000'], ['a' => 'uuid']));
        $this->assertTrue($this->check(['a' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], ['a' => 'ulid']));
        $this->assertFalse($this->check(['a' => '01ARZ3NDEKTSV4RRFFQ69G5FAU'], ['a' => 'ulid']));   // U مجاز نیست (Crockford)
        $this->assertTrue($this->check(['a' => '192.168.1.1'], ['a' => 'ip|ipv4']));
        $this->assertTrue($this->check(['a' => '::1'], ['a' => 'ipv6']));
        $this->assertTrue($this->check(['a' => 'AA:BB:CC:DD:EE:FF'], ['a' => 'mac_address']));
        $this->assertTrue($this->check(['a' => 'abc123'], ['a' => 'alpha_num']));
        $this->assertFalse($this->check(['a' => 'abc-123'], ['a' => 'alpha_num']));
        $this->assertTrue($this->check(['a' => 'abc-123_x'], ['a' => 'alpha_dash']));
        $this->assertTrue($this->check(['a' => 'متن فارسی'], ['a' => 'alpha']));   // Persian-first
        $this->assertTrue($this->check(['a' => 'متن-فارسی'], ['a' => 'alpha_dash']));
        $this->assertTrue($this->check(['a' => 'hello-world-1'], ['a' => 'slug']));
        $this->assertFalse($this->check(['a' => '-hello'], ['a' => 'slug']));
        $this->assertTrue($this->check(['a' => '{"x":1}'], ['a' => 'json']));
        $this->assertFalse($this->check(['a' => '{x:1}'], ['a' => 'json']));
        $this->assertTrue($this->check(['a' => '2026-01-15'], ['a' => 'date_format:Y-m-d']));
        $this->assertFalse($this->check(['a' => '2026-13-15'], ['a' => 'date_format:Y-m-d']));
        $this->assertTrue($this->check(['a' => 'Asia/Tehran'], ['a' => 'timezone']));
        $this->assertFalse($this->check(['a' => 'Mars/Olympus'], ['a' => 'timezone']));
    }

    public function testRegexRule(): void
    {
        $this->assertTrue($this->check(['a' => 'ABC-123'], ['a' => 'regex:/^[A-Z]+-\d+$/']));
        $this->assertFalse($this->check(['a' => 'abc'], ['a' => 'regex:/^[A-Z]+-\d+$/']));
        // الگو با | و , درون regex سالم بماند
        $this->assertTrue($this->check(['a' => 'fa'], ['a' => 'regex:/^(fa|en)$/']));
        $this->assertFalse($this->check(['a' => 'de'], ['a' => 'regex:/^(fa|en)$/']));
        $this->assertTrue($this->check(['a' => 'x'], ['a' => 'not_regex:/^\d+$/']));
    }

    public function testMathRules(): void
    {
        $this->assertTrue($this->check(['a' => '10.25'], ['a' => 'decimal:2']));
        $this->assertFalse($this->check(['a' => '10.255'], ['a' => 'decimal:2']));
        $this->assertTrue($this->check(['a' => 1500], ['a' => 'money']));
        $this->assertFalse($this->check(['a' => -5], ['a' => 'money']));
        $this->assertFalse($this->check(['a' => 10.5], ['a' => 'money']));   // float ممنوع (ADR-0013)
        $this->assertTrue($this->check(['a' => 5], ['a' => 'positive']));
        $this->assertFalse($this->check(['a' => 0], ['a' => 'positive']));
        $this->assertTrue($this->check(['a' => -5], ['a' => 'negative']));
        $this->assertTrue($this->check(['a' => 0], ['a' => 'non_negative']));
        $this->assertTrue($this->check(['a' => 50], ['a' => 'percent']));
        $this->assertFalse($this->check(['a' => 150], ['a' => 'percent']));
        $this->assertTrue($this->check(['a' => 5], ['a' => 'numeric_range:1,10']));
        $this->assertFalse($this->check(['a' => 50], ['a' => 'numeric_range:1,10']));
    }

    public function testAdvancedRules(): void
    {
        $this->assertTrue($this->check(['a' => ''], ['a' => 'prohibited']));
        $this->assertFalse($this->check(['a' => 'x'], ['a' => 'prohibited']));
        $this->assertTrue($this->check(['a' => '', 'b' => '1'], ['a' => 'prohibited_if:b,1']));
        $this->assertTrue($this->check(['a' => [1, 2, 3]], ['a' => 'distinct']));
        $this->assertFalse($this->check(['a' => [1, 2, 2]], ['a' => 'distinct']));
        $this->assertTrue($this->check(['a' => 'x', 'website' => ''], ['website' => 'honeypot:website']));
        $this->assertFalse($this->check(['website' => 'http://spam'], ['website' => 'honeypot:website']));
        $this->assertTrue($this->check(['a' => 'plain text'], ['a' => 'no_html']));
        $this->assertFalse($this->check(['a' => '<b>html</b>'], ['a' => 'no_html']));
        $this->assertTrue($this->check(['a' => [1, 2]], ['a' => 'array|each']));
        $this->assertTrue($this->check(['a' => ['x' => 1]], ['a' => 'nested']));
    }

    public function testSecurityRules(): void
    {
        $this->assertTrue($this->check(['a' => 'Str0ng!Pass#2026xyz'], ['a' => 'strong_password']));
        $this->assertFalse($this->check(['a' => 'weak'], ['a' => 'strong_password']));
        $this->assertFalse($this->check(['a' => 'password'], ['a' => 'not_compromised']));
        $this->assertFalse($this->check(['a' => '123456'], ['a' => 'not_compromised']));
        $this->assertTrue($this->check(['a' => 'Xk9#mQ2$vL7@nB4'], ['a' => 'not_compromised']));
        $this->assertTrue($this->check(['a' => 'https://example.com'], ['a' => 'safe_url']));
        $this->assertFalse($this->check(['a' => 'http://127.0.0.1/admin'], ['a' => 'safe_url']));
        $this->assertFalse($this->check(['a' => 'http://169.254.169.254/meta'], ['a' => 'safe_url']));
        $this->assertFalse($this->check(['a' => 'file:///etc/passwd'], ['a' => 'safe_url']));
    }

    public function testBailStopsOnFirst(): void
    {
        $v = new Validator(['a' => ''], ['a' => 'bail|required|string|min:5']);
        $this->assertTrue($v->fails());
        $this->assertCount(1, $v->errors()->get('a'));
    }

    public function testNullableSkipsRules(): void
    {
        $this->assertTrue($this->check(['a' => null], ['a' => 'nullable|email']));
        $this->assertFalse($this->check(['a' => 'bad'], ['a' => 'nullable|email']));
    }

    public function testClosureRule(): void
    {
        $v = new Validator(['a' => 5], [
            'a' => ['required', function (string $attr, mixed $value, \Closure $fail): void {
                if ($value !== 10) {
                    $fail('custom fail');
                }
            }],
        ]);
        $this->assertTrue($v->fails());
    }
}
