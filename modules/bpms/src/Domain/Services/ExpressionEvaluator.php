<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ExpressionEvaluator — ارزیاب امن عبارت‌های شرط (DOC-098 §5.1) 🔒.
 */

namespace Modules\Bpms\Domain\Services;

use Modules\Bpms\Domain\Exceptions\BpmsException;

/**
 * ExpressionEvaluator — mini-expression-language با lexer/parser/evaluator
 * (بدون `eval` — AC-BPMS-12).
 *
 * گرامر (recursive-descent):
 * ```
 * or       := and ( ('||' | 'or') and )*
 * and      := cmp ( ('&&' | 'and') cmp )*
 * cmp      := add ( ('==' | '!=' | '===' | '!==' | '<' | '<=' | '>' | '>=') add )?
 * add      := mul ( ('+' | '-') mul )*
 * mul      := unary ( ('*' | '/' | '%') unary )*
 * unary    := ('!' | '-') unary | primary
 * primary  := '(' or ')' | number | string | true | false | null | dotted-name
 * ```
 *
 * 🔒 امنیت: بدون فراخوانی تابع، بدون property access، بدون eval؛
 * identifiers فقط به آرایهٔ context نگاشت می‌شوند (dotted path).
 * تزریق (system(...), backtick, $var, ; و…) → خطای lex/parse قطعی.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ExpressionEvaluator
{
    /** سقف طول عبارت (🔒 DoS — DOC-071 §8). */
    private const MAX_LENGTH = 2000;

    /** سقف عمق پرانتز (محافظ stack). */
    private const MAX_DEPTH = 32;

    private int $pos = 0;

    /** @var list<array{0: string, 1: mixed}> */
    private array $tokens = [];

    private int $depth = 0;

    private string $expr = '';

    /**
     * ارزیابی عبارت → مقدار (mixed).
     *
     * @param array<string, mixed> $context
     *
     * @throws BpmsException invalid_expression
     */
    public function evaluateValue(string $expr, array $context): mixed
    {
        return $this->evalAst($this->parse($expr), $context);
    }

    /**
     * ارزیابی عبارت → bool (truthiness قطعی: فقط bool/int/float/string/array —
     * اشیاء هرگز در context پذیرفته نمی‌شوند).
     *
     * @param array<string, mixed> $context
     *
     * @throws BpmsException invalid_expression
     */
    public function evaluate(string $expr, array $context): bool
    {
        return $this->toBool($this->evaluateValue($expr, $context));
    }

    /**
     * آیا عبارت از نظر lexer/parser معتبر است؟ (بدون ارزیابی — برای
     * اعتبارسنجی definition در زمان publish).
     */
    public function parses(string $expr): bool
    {
        try {
            $this->parse($expr);

            return true;
        } catch (BpmsException) {
            return false;
        }
    }

    // ─── lexer ─────────────────────────────────────────────────────────────

    /**
     * @return list<array{0: string, 1: mixed}> [type, value] — type:
     *         num|str|ident|op|bool|null|eof
     *
     * @throws BpmsException
     */
    private function tokenize(string $expr): array
    {
        $expr = \trim($expr);
        if ($expr === '') {
            throw BpmsException::invalidExpression($expr, 'empty');
        }
        if (\strlen($expr) > self::MAX_LENGTH) {
            throw BpmsException::invalidExpression($expr, 'too_long');
        }

        $tokens = [];
        $len = \strlen($expr);
        $i = 0;

        while ($i < $len) {
            $ch = $expr[$i];

            // فاصله
            if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r") {
                $i++;
                continue;
            }

            // 🔒 کاراکترهای ممنوع (تزریق shell/PHP/JS)
            if ($ch === '$' || $ch === '`' || $ch === ';' || $ch === '@' || $ch === '#' || $ch === '\\') {
                throw BpmsException::invalidExpression($expr, 'forbidden_char:' . $ch);
            }

            // عدد (int/float — بدون نماد علمی با توان منفی برای سادگی امن)
            if (\ctype_digit($ch) || ($ch === '.' && isset($expr[$i + 1]) && \ctype_digit($expr[$i + 1]))) {
                $start = $i;
                $isFloat = false;
                while ($i < $len && (\ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    if ($expr[$i] === '.') {
                        if ($isFloat) {
                            throw BpmsException::invalidExpression($expr, 'bad_number');
                        }
                        $isFloat = true;
                    }
                    $i++;
                }
                $raw = \substr($expr, $start, $i - $start);
                $tokens[] = $isFloat ? ['num', (float) $raw] : ['num', (int) $raw];
                continue;
            }

            // رشتهٔ تک‌نقل یا دونقل (بدون escape پیچیده — \' و \" مجاز)
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $i++;
                $value = '';
                $closed = false;
                while ($i < $len) {
                    if ($expr[$i] === '\\' && $i + 1 < $len && ($expr[$i + 1] === $quote || $expr[$i + 1] === '\\')) {
                        $value .= $expr[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($expr[$i] === $quote) {
                        $closed = true;
                        $i++;
                        break;
                    }
                    $value .= $expr[$i];
                    $i++;
                }
                if (!$closed) {
                    throw BpmsException::invalidExpression($expr, 'unterminated_string');
                }
                $tokens[] = ['str', $value];
                continue;
            }

            // identifier / dotted path / true|false|null / and|or|not
            if (\ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                while ($i < $len && (\ctype_alnum($expr[$i]) || $expr[$i] === '_' || $expr[$i] === '.')) {
                    $i++;
                }
                $word = \substr($expr, $start, $i - $start);
                $tokens[] = match (\strtolower($word)) {
                    'true' => ['bool', true],
                    'false' => ['bool', false],
                    'null' => ['null', null],
                    'and' => ['op', '&&'],
                    'or' => ['op', '||'],
                    'not' => ['op', '!'],
                    default => ['ident', $word],
                };
                // 🔒 فراخوانی تابع مجاز نیست: ident بلافاصله با '(' → خطا
                if ($tokens[\count($tokens) - 1][0] === 'ident' && isset($expr[$i]) && $expr[$i] === '(') {
                    throw BpmsException::invalidExpression($expr, 'function_call_forbidden');
                }
                continue;
            }

            // عملگرهای دو-کاراکتری
            $two = \substr($expr, $i, 2);
            if (\in_array($two, ['==', '!=', '<=', '>=', '&&', '||', '===', '!=='], true) || \substr($expr, $i, 3) === '===') {
                $three = \substr($expr, $i, 3);
                if ($three === '===' || $three === '!==') {
                    $tokens[] = ['op', $three];
                    $i += 3;
                    continue;
                }
                $tokens[] = ['op', $two];
                $i += 2;
                continue;
            }

            // عملگرهای تک-کاراکتری
            if (\str_contains('+-*/%<>!(),', $ch)) {
                if ($ch === '(' || $ch === ')') {
                    $tokens[] = ['paren', $ch];
                } elseif ($ch === ',') {
                    throw BpmsException::invalidExpression($expr, 'comma_not_allowed');
                } else {
                    $tokens[] = ['op', $ch];
                }
                $i++;
                continue;
            }

            throw BpmsException::invalidExpression($expr, 'unexpected_char:' . $ch);
        }

        $tokens[] = ['eof', null];

        return $tokens;
    }

    // ─── parser (AST = آرایهٔ ['op'|'lit'|'var', …]) ───────────────────────

    /**
     * @return array<string, mixed> AST
     *
     * @throws BpmsException
     */
    private function parse(string $expr): array
    {
        $this->tokens = $this->tokenize($expr);
        $this->pos = 0;
        $this->depth = 0;
        $this->expr = $expr;

        $ast = $this->parseOr();

        if ($this->peek()[0] !== 'eof') {
            throw BpmsException::invalidExpression($expr, 'trailing_tokens');
        }

        return $ast;
    }

    /** @return array{0: string, 1: mixed} */
    private function peek(): array
    {
        return $this->tokens[$this->pos];
    }

    /** @return array{0: string, 1: mixed} */
    private function next(): array
    {
        return $this->tokens[$this->pos++];
    }

    private function consumeOp(string $op): bool
    {
        [$type, $value] = $this->peek();
        if ($type === 'op' && $value === $op) {
            $this->pos++;

            return true;
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function parseOr(): array
    {
        $left = $this->parseAnd();
        while ($this->consumeOp('||')) {
            $left = ['kind' => 'binary', 'op' => '||', 'left' => $left, 'right' => $this->parseAnd()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseAnd(): array
    {
        $left = $this->parseComparison();
        while ($this->consumeOp('&&')) {
            $left = ['kind' => 'binary', 'op' => '&&', 'left' => $left, 'right' => $this->parseComparison()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseComparison(): array
    {
        $left = $this->parseAdditive();
        [$type, $value] = $this->peek();
        if ($type === 'op' && \in_array($value, ['==', '!=', '===', '!==', '<', '<=', '>', '>='], true)) {
            $this->pos++;

            return ['kind' => 'binary', 'op' => (string) $value, 'left' => $left, 'right' => $this->parseAdditive()];
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseAdditive(): array
    {
        $left = $this->parseMultiplicative();
        while (true) {
            [$type, $value] = $this->peek();
            if ($type === 'op' && ($value === '+' || $value === '-')) {
                $this->pos++;
                $left = ['kind' => 'binary', 'op' => (string) $value, 'left' => $left, 'right' => $this->parseMultiplicative()];
                continue;
            }
            break;
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseMultiplicative(): array
    {
        $left = $this->parseUnary();
        while (true) {
            [$type, $value] = $this->peek();
            if ($type === 'op' && ($value === '*' || $value === '/' || $value === '%')) {
                $this->pos++;
                $left = ['kind' => 'binary', 'op' => (string) $value, 'left' => $left, 'right' => $this->parseUnary()];
                continue;
            }
            break;
        }

        return $left;
    }

    /** @return array<string, mixed> */
    private function parseUnary(): array
    {
        [$type, $value] = $this->peek();
        if ($type === 'op' && ($value === '!' || $value === '-')) {
            $this->pos++;

            return ['kind' => 'unary', 'op' => (string) $value, 'operand' => $this->parseUnary()];
        }

        return $this->parsePrimary();
    }

    /** @return array<string, mixed> */
    private function parsePrimary(): array
    {
        [$type, $value] = $this->next();

        if ($type === 'paren' && $value === '(') {
            if (++$this->depth > self::MAX_DEPTH) {
                throw BpmsException::invalidExpression($this->expr, 'max_depth_exceeded');
            }
            $inner = $this->parseOr();
            $this->depth--;
            [$closeType, $closeValue] = $this->next();
            if ($closeType !== 'paren' || $closeValue !== ')') {
                throw BpmsException::invalidExpression($this->expr, 'unbalanced_parens');
            }

            return $inner;
        }

        return match ($type) {
            'num', 'str', 'bool' => ['kind' => 'lit', 'value' => $value],
            'null' => ['kind' => 'lit', 'value' => null],
            'ident' => ['kind' => 'var', 'path' => (string) $value],
            'eof' => throw BpmsException::invalidExpression($this->expr, 'unexpected_end'),
            default => throw BpmsException::invalidExpression($this->expr, 'unexpected_token:' . $type),
        };
    }

    // ─── evaluator ─────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $ast
     * @param array<string, mixed> $context
     */
    private function evalAst(array $ast, array $context): mixed
    {
        return match ($ast['kind']) {
            'lit' => $ast['value'],
            'var' => $this->resolvePath((string) $ast['path'], $context),
            'unary' => $this->evalUnary((string) $ast['op'], $this->evalAst($ast['operand'], $context)),
            'binary' => $this->evalBinary((string) $ast['op'], $ast['left'], $ast['right'], $context),
            default => throw BpmsException::invalidExpression($this->expr, 'bad_ast'),
        };
    }

    /** @param array<string, mixed> $context */
    private function resolvePath(string $path, array $context): mixed
    {
        // مسیر dotted فقط روی آرایه‌ها — هر نوع دیگر در میانهٔ مسیر → null
        $segments = \explode('.', $path);
        $current = $context;
        foreach ($segments as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        // 🔒 اشیاء/منابع هرگز از context نشت نمی‌کنند
        return \is_object($current) || \is_resource($current) ? null : $current;
    }

    private function evalUnary(string $op, mixed $operand): mixed
    {
        return match ($op) {
            '!' => !$this->toBool($operand),
            '-' => \is_int($operand) || \is_float($operand) ? -$operand : throw BpmsException::invalidExpression($this->expr, 'unary_minus_on_non_numeric'),
            default => throw BpmsException::invalidExpression($this->expr, 'bad_unary'),
        };
    }

    /**
     * @param array<string, mixed> $leftAst
     * @param array<string, mixed> $rightAst
     * @param array<string, mixed> $context
     */
    private function evalBinary(string $op, array $leftAst, array $rightAst, array $context): mixed
    {
        // short-circuit منطقی
        if ($op === '&&') {
            $left = $this->evalAst($leftAst, $context);
            if (!$this->toBool($left)) {
                return false;
            }

            return $this->toBool($this->evalAst($rightAst, $context));
        }
        if ($op === '||') {
            $left = $this->evalAst($leftAst, $context);
            if ($this->toBool($left)) {
                return true;
            }

            return $this->toBool($this->evalAst($rightAst, $context));
        }

        $left = $this->evalAst($leftAst, $context);
        $right = $this->evalAst($rightAst, $context);

        return match ($op) {
            '==' => $this->looseEqual($left, $right),
            '!=' => !$this->looseEqual($left, $right),
            '===' => $left === $right,
            '!==' => $left !== $right,
            '<' => $this->compare($left, $right, $op),
            '<=' => $this->compare($left, $right, $op),
            '>' => $this->compare($left, $right, $op),
            '>=' => $this->compare($left, $right, $op),
            '+' => \is_string($left) && \is_string($right) ? $left . $right : $this->arith($left, $right, '+'),
            '-' => $this->arith($left, $right, '-'),
            '*' => $this->arith($left, $right, '*'),
            '/' => $this->arith($left, $right, '/'),
            '%' => $this->arith($left, $right, '%'),
            default => throw BpmsException::invalidExpression($this->expr, 'bad_binary_op'),
        };
    }

    /** مقایسهٔ loose با قواعد قطعی (بدون سورپرایزهای == در PHP). */
    private function looseEqual(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }
        if (\is_bool($left) || \is_bool($right)) {
            return $this->toBool($left) === $this->toBool($right);
        }
        if (\is_numeric($left) && \is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }

    /** @throws BpmsException مقایسهٔ ترتیبی روی غیرعدد */
    private function compare(mixed $left, mixed $right, string $op): bool
    {
        if (\is_numeric($left) && \is_numeric($right)) {
            $l = (float) $left;
            $r = (float) $right;
        } elseif (\is_string($left) && \is_string($right)) {
            return match ($op) {
                '<' => \strcmp($left, $right) < 0,
                '<=' => \strcmp($left, $right) <= 0,
                '>' => \strcmp($left, $right) > 0,
                '>=' => \strcmp($left, $right) >= 0,
                default => false,
            };
        } else {
            throw BpmsException::invalidExpression($this->expr, 'comparison_type_mismatch');
        }

        return match ($op) {
            '<' => $l < $r,
            '<=' => $l <= $r,
            '>' => $l > $r,
            '>=' => $l >= $r,
            default => false,
        };
    }

    /** @throws BpmsException عمل حسابی روی غیرعدد / تقسیم بر صفر */
    private function arith(mixed $left, mixed $right, string $op): int|float
    {
        if (!\is_numeric($left) || !\is_numeric($right)) {
            throw BpmsException::invalidExpression($this->expr, 'arith_on_non_numeric');
        }
        if (($op === '/' || $op === '%') && (float) $right === 0.0) {
            throw BpmsException::invalidExpression($this->expr, 'division_by_zero');
        }

        $l = (float) $left;
        $r = (float) $right;
        $result = match ($op) {
            '+' => $l + $r,
            '-' => $l - $r,
            '*' => $l * $r,
            '/' => $l / $r,
            '%' => $l - \floor($l / $r) * $r,
            default => throw BpmsException::invalidExpression($this->expr, 'bad_arith_op'),
        };

        // بازگشت به int وقتی هر دو int بودند و نتیجهٔ صحیح است
        if (\is_int($left) && \is_int($right) && $op !== '/' && \floor($result) === $result && \abs($result) <= PHP_INT_MAX) {
            return (int) $result;
        }

        return $result;
    }

    /** truthiness قطعی — اشیاء/آرایه‌های غیرخالی true. */
    private function toBool(mixed $value): bool
    {
        return match (true) {
            \is_bool($value) => $value,
            \is_int($value), \is_float($value) => $value !== 0 && $value !== 0.0,
            \is_string($value) => $value !== '' && $value !== '0',
            \is_array($value) => $value !== [],
            $value === null => false,
            default => false,   // 🔒 object/resource → false (هرگز نشت نمی‌کند)
        };
    }
}
