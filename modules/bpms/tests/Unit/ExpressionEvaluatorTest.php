<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Unit;

use Modules\Bpms\Domain\Exceptions\BpmsException;
use Modules\Bpms\Domain\Services\ExpressionEvaluator;
use Tavaan\Testing\TestCase;

/**
 * تست واحد ExpressionEvaluator — زبان عبارت امن (DOC-098 §5.1، AC-BPMS-12).
 *
 * 🔒 E2E-10: بدون eval، مقاوم در برابر injection.
 */
final class ExpressionEvaluatorTest extends TestCase
{
    private ExpressionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ExpressionEvaluator();
    }

    // ─── مقایسه و منطق ─────────────────────────────────────────────────────

    public function testDocumentedComparisons(): void
    {
        $context = ['variables' => ['days' => 2, 'approved' => true, 'name' => 'ali']];

        self::assertTrue($this->evaluator->evaluate('variables.days <= 2', $context));
        self::assertFalse($this->evaluator->evaluate('variables.days < 2', $context));
        self::assertTrue($this->evaluator->evaluate('variables.days == 2', $context));
        self::assertTrue($this->evaluator->evaluate('variables.days === 2', $context));
        self::assertFalse($this->evaluator->evaluate('variables.days === "2"', $context));
        self::assertTrue($this->evaluator->evaluate('variables.days == "2"', $context));   // loose
        self::assertTrue($this->evaluator->evaluate('variables.approved', $context));
        self::assertTrue($this->evaluator->evaluate('variables.name == \'ali\'', $context));
        self::assertTrue($this->evaluator->evaluate('variables.name != "reza"', $context));
    }

    public function testLogicalOperatorsAndPrecedence(): void
    {
        $context = ['variables' => ['a' => 1, 'b' => 2, 'c' => 3, 'flag' => false]];

        self::assertTrue($this->evaluator->evaluate('variables.a < variables.b && variables.b < variables.c', $context));
        self::assertFalse($this->evaluator->evaluate('variables.a > variables.b || variables.flag', $context));
        self::assertTrue($this->evaluator->evaluate('!variables.flag', $context));
        self::assertTrue($this->evaluator->evaluate('variables.a == 1 or variables.flag', $context));
        self::assertFalse($this->evaluator->evaluate('variables.a == 1 and variables.flag', $context));

        // اولویت: && بر || — (false && false) || true = true
        self::assertTrue($this->evaluator->evaluate('variables.flag && variables.flag || variables.a == 1', $context));
        // پرانتز
        self::assertFalse($this->evaluator->evaluate('variables.flag && (variables.flag || variables.a == 2)', $context));
    }

    public function testArithmeticInsideConditions(): void
    {
        $context = ['variables' => ['qty' => 3, 'price' => 100, 'discount' => 10]];

        self::assertTrue($this->evaluator->evaluate('variables.qty * variables.price > 250', $context));
        self::assertTrue($this->evaluator->evaluate('variables.qty * variables.price - variables.discount == 290', $context));
        self::assertTrue($this->evaluator->evaluate('variables.qty % 2 == 1', $context));
        self::assertTrue($this->evaluator->evaluate('variables.price / 4 == 25', $context));
        self::assertTrue($this->evaluator->evaluate('-variables.qty == -3', $context));
        self::assertTrue($this->evaluator->evaluate('(variables.qty + 2) * 2 == 10', $context));
    }

    public function testDottedPathsAndMissingKeys(): void
    {
        $context = ['variables' => ['user' => ['role' => 'manager', 'age' => 40]]];

        self::assertTrue($this->evaluator->evaluate("variables.user.role == 'manager'", $context));
        self::assertTrue($this->evaluator->evaluate('variables.user.age >= 18', $context));
        // کلید ناموجود → null → falsy (بدون خطا)
        self::assertFalse($this->evaluator->evaluate('variables.missing', $context));
        self::assertFalse($this->evaluator->evaluate('variables.user.unknown.deep', $context));
        self::assertTrue($this->evaluator->evaluate('variables.missing == null', $context));
        // متغیر بدون پیشوند از ریشهٔ context
        self::assertTrue($this->evaluator->evaluate('variables != null', $context));
    }

    public function testLiteralsAndBooleans(): void
    {
        self::assertTrue($this->evaluator->evaluate('true', []));
        self::assertFalse($this->evaluator->evaluate('false', []));
        self::assertFalse($this->evaluator->evaluate('null', []));
        self::assertTrue($this->evaluator->evaluate('1', []));
        self::assertFalse($this->evaluator->evaluate('0', []));
        self::assertTrue($this->evaluator->evaluate('"non-empty"', []));
        self::assertFalse($this->evaluator->evaluate('""', []));
        self::assertTrue($this->evaluator->evaluate('1.5 > 1.4', []));
    }

    public function testStringValueComparison(): void
    {
        self::assertTrue($this->evaluator->evaluate("'abc' < 'abd'", []));
        self::assertTrue($this->evaluator->evaluate("'b' >= 'a'", []));
    }

    // ─── 🔒 امنیت (E2E-10 / AC-BPMS-12) ────────────────────────────────────

    public function testFunctionCallsAreRejected(): void
    {
        foreach ([
            'system("ls")',
            'phpinfo()',
            'exec("rm -rf /")',
            'variables.system("x")',
            'file_get_contents("/etc/passwd")',
        ] as $expr) {
            $this->assertInvalid($expr, ['variables' => []]);
        }
    }

    public function testInjectionCharactersAreRejected(): void
    {
        foreach ([
            'variables.a; system("x")',        // semicolon
            '$_GET["x"]',                       // PHP variable
            '`ls`',                             // backtick
            'variables.a # comment',           // comment marker
            '@variables.a',                     // suppress
            'variables.a\\',                    // backslash
        ] as $expr) {
            $this->assertInvalid($expr, ['variables' => ['a' => 1]]);
        }
    }

    public function testMalformedExpressionsAreRejected(): void
    {
        foreach ([
            '',
            'variables.a ==',
            '== 5',
            '(variables.a',
            'variables.a)',
            'variables.a b',
            '"unterminated',
            '1.2.3',
            'variables.a == 1, 2',
        ] as $expr) {
            $this->assertInvalid($expr, ['variables' => ['a' => 1, 'b' => 2]]);
        }
    }

    public function testDeepNestingIsBounded(): void
    {
        // ۴۰ لایه پرانتز → رد (سقف ۳۲)
        $expr = \str_repeat('(', 40) . 'true' . \str_repeat(')', 40);
        $this->assertInvalid($expr, []);

        // ۳۰ لایه → قبول
        $ok = \str_repeat('(', 30) . 'true' . \str_repeat(')', 30);
        self::assertTrue($this->evaluator->evaluate($ok, []));
    }

    public function testHugeExpressionIsRejected(): void
    {
        $expr = 'variables.a == 1 && ' . \str_repeat('variables.a == 1 && ', 200) . 'variables.a == 1';
        self::assertTrue(\strlen($expr) > 2000);
        $this->assertInvalid($expr, ['variables' => ['a' => 1]]);
    }

    public function testDivisionByZeroIsRejected(): void
    {
        $this->assertInvalid('variables.a / 0', ['variables' => ['a' => 1]]);
        $this->assertInvalid('variables.a % 0', ['variables' => ['a' => 1]]);
    }

    public function testTypeMismatchComparisonIsRejected(): void
    {
        $this->assertInvalid('variables.a > variables.b', ['variables' => ['a' => 1, 'b' => 'text']]);
    }

    public function testObjectsAndResourcesNeverLeakFromContext(): void
    {
        $context = ['variables' => ['obj' => new \stdClass(), 'ok' => 1]];
        // آبجکت در context → null (نه نشت، نه خطا)
        self::assertFalse($this->evaluator->evaluate('variables.obj', $context));
        self::assertTrue($this->evaluator->evaluate('variables.ok == 1', $context));
    }

    public function testNoEvalOrDangerousCallsInSource(): void
    {
        // 🔒 AC-BPMS-12 — کد منبع evaluator هیچ eval/create_function ندارد
        $source = (string) \file_get_contents(\dirname(__DIR__, 2) . '/src/Domain/Services/ExpressionEvaluator.php');
        self::assertStringNotContainsString('eval(', $source);
        self::assertStringNotContainsString('create_function', $source);
        self::assertStringNotContainsString('call_user_func', $source);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $context
     */
    private function assertInvalid(string $expr, array $context): void
    {
        try {
            $this->evaluator->evaluate($expr, $context);
            self::fail('باید خطا می‌داد: ' . $expr);
        } catch (BpmsException $e) {
            self::assertSame('invalid_expression', $e->reason());
        }
    }
}
