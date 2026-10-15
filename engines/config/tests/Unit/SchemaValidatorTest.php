<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Tests\Unit;

use Tavaan\Engine\Config\Exceptions\SchemaValidationException;
use Tavaan\Engine\Config\SchemaValidator;
use Tavaan\Testing\TestCase;

/**
 * تست Schema Validation — DOC-032 §5.5 (T-09).
 */
final class SchemaValidatorTest extends TestCase
{
    public function testValidSchemaPasses(): void
    {
        $v = new SchemaValidator();
        $v->validate('app', [
            'name' => 'Tavaan',
            'env' => 'local',
            'debug' => true,
        ], [
            'name' => ['type' => 'string', 'required' => true, 'max' => 100],
            'env' => ['type' => 'string', 'in' => ['local', 'production']],
            'debug' => ['type' => 'bool'],
        ]);
        $this->assertTrue(true);
    }

    public function testWrongTypeFails(): void
    {
        $v = new SchemaValidator();
        $this->expectException(SchemaValidationException::class);
        $v->validate('app', ['debug' => 'yes'], ['debug' => ['type' => 'bool']]);
    }

    public function testInvalidInValueFails(): void
    {
        $v = new SchemaValidator();
        try {
            $v->validate('app', ['env' => 'staging-x'], ['env' => ['type' => 'string', 'in' => ['local', 'production']]]);
            self::fail('expected exception');
        } catch (SchemaValidationException $e) {
            $this->assertStringContainsString('env', (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE));
        }
    }

    public function testRequiredMissingFails(): void
    {
        $v = new SchemaValidator();
        $this->expectException(SchemaValidationException::class);
        $v->validate('app', [], ['name' => ['required' => true]]);
    }

    public function testProductionModeOnlyChecksRequiredAndIn(): void
    {
        $v = new SchemaValidator();
        // نوع اشتباه در حالت non-strict (production) خطا نمی‌دهد
        $v->validate('app', ['debug' => 'yes'], ['debug' => ['type' => 'bool']], strict: false);
        // اما `in` نقض شود → خطا حتی در production
        $this->expectException(SchemaValidationException::class);
        $v->validate('app', ['env' => 'bad'], ['env' => ['in' => ['local']]], strict: false);
    }

    public function testPatternAndRange(): void
    {
        $v = new SchemaValidator();
        $v->validate('app', ['locale' => 'fa'], ['locale' => ['type' => 'string', 'pattern' => '/^[a-z]{2}$/']]);
        $this->expectException(SchemaValidationException::class);
        $v->validate('app', ['count' => 500], ['count' => ['type' => 'int', 'max' => 100]]);
    }
}
