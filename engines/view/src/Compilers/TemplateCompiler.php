<?php

declare(strict_types=1);

namespace Tavaan\Engine\View\Compilers;

use Tavaan\Engine\View\DirectiveRegistry;
use Tavaan\Engine\View\Escaper;
use Tavaan\Engine\View\Exceptions\ViewException;

/**
 * تَوان | Tavaan — View
 * TemplateCompiler — کامپایل `.tv.php` به PHP خالص (بدون eval در اجرا).
 *
 * ترتیب passها (DOC-043 §4.1 — حیاتی):
 * 1. protectVerbatim  2. stripComments  3. compileComponents
 * 4. compileDirectives  5. compileEchos  6. restoreVerbatim  7. guardRawPhp
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class TemplateCompiler
{
    public const VERSION = '1.0.1';

    /** @var array<string, string> directive ساده → قالب PHP */
    private const SIMPLE = [
        'if'       => 'if (%s):',
        'elseif'   => 'elseif (%s):',
        'else'     => 'else:',
        'endif'    => 'endif;',
        'unless'   => 'if (! (%s)):',
        'endunless' => 'endif;',
        'for'      => 'for (%s):',
        'endfor'   => 'endfor;',
        'foreach'  => 'foreach (%s):',
        'endforeach' => 'endforeach;',
        'while'    => 'while (%s):',
        'endwhile' => 'endwhile;',
        'switch'   => 'switch (%s):',
        'endswitch' => 'endswitch;',
        'isset'    => 'if (isset(%s)):',
        'endisset' => 'endif;',
        'empty'    => 'if (empty(%s)):',
        'endempty' => 'endif;',
        'auth'     => 'if ($__env->auth()):',
        'endauth'  => 'endif;',
        'guest'    => 'if (! $__env->auth()):',
        'endguest' => 'endif;',
        'production' => 'if ($__env->isProduction()):',
        'endproduction' => 'endif;',
        'break'    => 'break;',
        'continue' => 'continue;',
        'default'  => 'default:',
    ];

    /** @var array<string, string> */
    private array $verbatimBlocks = [];

    private int $forelseCounter = 0;

    public function __construct(private readonly DirectiveRegistry $directives = new DirectiveRegistry())
    {
    }

    public function compile(string $template): string
    {
        $this->verbatimBlocks = [];
        $this->forelseCounter = 0;

        $php = $this->protectVerbatim($template);
        $php = $this->stripComments($php);
        $php = $this->compileComponents($php);
        $php = $this->compileDirectives($php);
        $php = $this->compileEchos($php);
        $php = $this->restoreVerbatim($php);

        return $php;
    }

    // ─── Pass 1/6: Verbatim ───────────────────────────────────────────────

    private function protectVerbatim(string $template): string
    {
        $template = (string) \preg_replace_callback(
            '/@verbatim(.*?)@endverbatim/s',
            function (array $m): string {
                $token = '@__VERBATIM_' . \count($this->verbatimBlocks) . '__@';
                $this->verbatimBlocks[$token] = $m[1];

                return $token;
            },
            $template,
        );

        // @{{ ... }} → بدون پردازش
        return (string) \preg_replace_callback(
            '/@(\{\{.+?\}\})/s',
            function (array $m): string {
                $token = '@__VERBATIM_' . \count($this->verbatimBlocks) . '__@';
                $this->verbatimBlocks[$token] = $m[1];

                return $token;
            },
            $template,
        );
    }

    private function restoreVerbatim(string $php): string
    {
        foreach ($this->verbatimBlocks as $token => $content) {
            $php = \str_replace($token, $content, $php);
        }

        return $php;
    }

    // ─── Pass 2: Comments ─────────────────────────────────────────────────

    private function stripComments(string $php): string
    {
        return (string) \preg_replace('/\{\{--.*?--\}\}/s', '', $php);
    }

    // ─── Pass 3: Components ───────────────────────────────────────────────

    private function compileComponents(string $php): string
    {
        // <x-slot name="n"> ... </x-slot>
        $php = (string) \preg_replace_callback(
            '/<x-slot\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/x-slot>/s',
            static fn (array $m): string => '<?php $__env->startSlot(' . \var_export($m[1], true) . '); ?>' . $m[2] . '<?php $__env->endSlot(); ?>',
            $php,
        );

        // self-closing: <x-name attrs />
        $php = (string) \preg_replace_callback(
            '/<x-([a-z0-9\.\-]+)((?:\s+[^>\/]*?)?)\s*\/>/i',
            fn (array $m): string => '<?php $__env->startComponent(' . \var_export(\str_replace('-', '.', $m[1]), true) . ', ' . $this->compileAttributes($m[2]) . '); echo $__env->renderComponent(); ?>',
            $php,
        );

        // pair: <x-name attrs> ... </x-name>
        $php = (string) \preg_replace_callback(
            '/<x-([a-z0-9\.\-]+)((?:\s+[^>]*?)?)>(.*?)<\/x-\1>/is',
            fn (array $m): string => '<?php $__env->startComponent(' . \var_export(\str_replace('-', '.', $m[1]), true) . ', ' . $this->compileAttributes($m[2]) . '); ?>'
                . $m[3]
                . '<?php echo $__env->renderComponent(); ?>',
            $php,
        );

        return $php;
    }

    /** پارس attributeها: `type="x"` رشته، `:count="$n"` عبارت PHP، `required` بول. */
    private function compileAttributes(string $attributeString): string
    {
        $attrs = [];
        \preg_match_all('/(:?[a-zA-Z@][\w\-.:]*)(?:=(".*?"|\'.*?\'|[^\s>]+))?/s', \trim($attributeString), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $value = $match[2] ?? null;

            if (\str_starts_with($name, ':')) {
                $name = \substr($name, 1);
                $expr = $value !== null ? \trim($value, '"\'') : 'true';
                $attrs[] = \var_export($name, true) . ' => ' . $expr;
                continue;
            }

            if ($value === null) {
                $attrs[] = \var_export($name, true) . ' => true';
                continue;
            }

            $value = \trim($value, '\'"');
            $attrs[] = \var_export($name, true) . ' => ' . \var_export($value, true);
        }

        return '[' . \implode(', ', $attrs) . ']';
    }

    // ─── Pass 4: Directives ───────────────────────────────────────────────

    /**
     * پارس دستی directiveها (بدون regex بازگشتی — JIT-safe و قطعی).
     *
     * قواعد: `@name` با آرگومان پرانتز-متوازن اختیاری؛ `@@name` → literal؛
     * `@` چسبیده به حرف (ایمیل) → literal.
     */
    private function compileDirectives(string $php): string
    {
        $out = '';
        $offset = 0;
        $len = \strlen($php);

        while ($offset < $len) {
            $at = \strpos($php, '@', $offset);
            if ($at === false) {
                $out .= \substr($php, $offset);
                break;
            }

            // @@name → literal @name
            if ($at + 1 < $len && $php[$at + 1] === '@' && $at + 2 < $len && \ctype_alpha($php[$at + 2])) {
                $out .= \substr($php, $offset, $at - $offset) . '@';
                $offset = $at + 2;   // یک @ حذف، بقیه در دور بعد (literal)
                // نام بعد از @@ باید literal بماند: مستقیم کپی کن
                $nameEnd = $at + 2;
                while ($nameEnd < $len && (\ctype_alnum($php[$nameEnd]) || $php[$nameEnd] === '_')) {
                    $nameEnd++;
                }
                $out .= \substr($php, $at + 2, $nameEnd - ($at + 2));
                $offset = $nameEnd;
                continue;
            }

            // ایمیل/نام: اگر کاراکتر قبل @ حرف/رقم است → literal
            if ($at > 0 && (\ctype_alnum($php[$at - 1]) || $php[$at - 1] === '_')) {
                $out .= \substr($php, $offset, $at - $offset + 1);
                $offset = $at + 1;
                continue;
            }

            // تطبیق @name
            $nameEnd = $at + 1;
            while ($nameEnd < $len && (\ctype_alnum($php[$nameEnd]) || $php[$nameEnd] === '_')) {
                $nameEnd++;
            }
            $name = \substr($php, $at + 1, $nameEnd - $at - 1);
            if ($name === '' || !\ctype_alpha($name[0])) {
                $out .= \substr($php, $offset, $at - $offset + 1);
                $offset = $at + 1;
                continue;
            }

            $out .= \substr($php, $offset, $at - $offset);

            // آرگومان پرانتز-متوازن (اختیاری؛ فاصله/تب مجاز — مانند Blade)
            $args = null;
            $after = $nameEnd;
            while ($after < $len && ($php[$after] === ' ' || $php[$after] === "\t")) {
                $after++;
            }
            if ($after < $len && $php[$after] === '(') {
                $close = $this->findBalancedParen($php, $after);
                if ($close !== null) {
                    $args = \substr($php, $after + 1, $close - $after - 1);
                    $after = $close + 1;
                }
            }

            $out .= $this->compileDirective($name, $args);
            $offset = $after;
        }

        return $out;
    }

    /**
     * یافتن پرانتز بستهٔ متوازن (با درک quote/escape).
     *
     * @return int|null ایندکس `)` متناظر
     */
    private function findBalancedParen(string $s, int $open): ?int
    {
        $depth = 0;
        $len = \strlen($s);
        $quote = '';

        for ($i = $open; $i < $len; $i++) {
            $ch = $s[$i];

            if ($quote !== '') {
                if ($ch === '\\' && $quote === '"') {
                    $i++;
                    continue;
                }
                if ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function compileDirective(string $name, ?string $args): string
    {
        $args = $args === null ? null : \trim($args);

        // forelse/empty خاص
        if ($name === 'forelse') {
            $this->forelseCounter++;

            return '<?php $__empty_' . $this->forelseCounter . ' = true; foreach (' . (string) $args . '): $__empty_' . $this->forelseCounter . ' = false; ?>';
        }
        if ($name === 'empty' && $this->forelseCounter > 0 && $args === null) {
            $counter = $this->forelseCounter;

            return '<?php endforeach; if ($__empty_' . $counter . '): ?>';
        }
        if ($name === 'endforelse') {
            $this->forelseCounter = \max(0, $this->forelseCounter - 1);

            return '<?php endif; ?>';
        }

        if ($name === 'case') {
            return '<?php case ' . (string) $args . ': ?>';
        }

        if (isset(self::SIMPLE[$name])) {
            $compiled = \sprintf(self::SIMPLE[$name], $args ?? '');

            return '<?php ' . $compiled . ' ?>';
        }

        if ($this->directives->has($name)) {
            return $this->directives->call($name, $args);
        }

        return match ($name) {
            'extends'    => '<?php $__env->extends(' . (string) $args . '); ?>',
            'section'    => $this->compileSection((string) $args),
            'endsection', 'stop' => '<?php $__env->stopSection(); ?>',
            'yield'      => '<?php echo $__env->yieldSection(' . (string) $args . '); ?>',
            'include'    => '<?php echo $__env->renderInclude(' . (string) $args . '); ?>',
            'includeIf'  => '<?php echo $__env->renderIncludeIf(' . (string) $args . '); ?>',
            'includeWhen' => '<?php echo $__env->renderIncludeWhen(' . (string) $args . '); ?>',
            'push'       => '<?php $__env->startPush(' . (string) $args . '); ?>',
            'endpush'    => '<?php $__env->stopPush(); ?>',
            'stack'      => '<?php echo $__env->yieldStack(' . (string) $args . '); ?>',
            'csrf'       => '<input type="hidden" name="_token" value="<?php echo ' . Escaper::class . '::html($__env->csrfToken()); ?>">',
            'method'     => $this->compileMethod((string) $args),
            'json'       => '<?php echo ' . Escaper::class . '::js(' . (string) $args . '); ?>',
            'props'      => $this->compileProps((string) $args),
            'php'        => '<?php ',
            'endphp'     => ' ?>',
            'trans'      => '<?php echo ' . Escaper::class . '::html($__env->trans(' . (string) $args . ')); ?>',
            'choice'     => '<?php echo ' . Escaper::class . '::html($__env->choice(' . (string) $args . ')); ?>',
            'can'        => '<?php if ($__env->can(' . (string) $args . ')): ?>',
            'endcan'     => '<?php endif; ?>',
            'cannot'     => '<?php if (! $__env->can(' . (string) $args . ')): ?>',
            'endcannot'  => '<?php endif; ?>',
            'dump'       => '<?php echo "<pre>" . ' . Escaper::class . '::html(print_r(' . (string) $args . ', true)) . "</pre>"; ?>',
            default      => throw ViewException::unknownDirective($name),
        };
    }

    /** @section('x') بلوکی یا @section('x','inline') */
    private function compileSection(string $args): string
    {
        if ($this->isInlineSection($args)) {
            return '<?php $__env->inlineSection(' . $args . '); ?>';
        }

        return '<?php $__env->startSection(' . $args . '); ?>';
    }

    private function isInlineSection(string $args): bool
    {
        // دو آرگومان سطح بالا (جداکنندهٔ ',' خارج از پرانتز/quote)
        $depth = 0;
        $inQuote = '';
        $len = \strlen($args);
        for ($i = 0; $i < $len; $i++) {
            $ch = $args[$i];
            if ($inQuote !== '') {
                if ($ch === '\\' && $inQuote === "'") {
                    $i++;
                    continue;
                }
                if ($ch === $inQuote) {
                    $inQuote = '';
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $inQuote = $ch;
                continue;
            }
            if ($ch === '(' || $ch === '[') {
                $depth++;
                continue;
            }
            if ($ch === ')' || $ch === ']') {
                $depth--;
                continue;
            }
            if ($ch === ',' && $depth === 0) {
                return true;
            }
        }

        return false;
    }

    private function compileMethod(string $args): string
    {
        return '<input type="hidden" name="_method" value="<?php echo ' . Escaper::class . '::html(strtoupper((string) (' . $args . '))); ?>">';
    }

    /** @props(['type' => 'info', …]) → default برای متغیرهای تعریف‌نشده */
    private function compileProps(string $args): string
    {
        return '<?php foreach ((' . $args . ') as $__pk => $__pv) { if (!\array_key_exists($__pk, \get_defined_vars())) { $$__pk = $__pv; } } ?>';
    }

    // ─── Pass 5: Echos ────────────────────────────────────────────────────

    private function compileEchos(string $php): string
    {
        // {!! raw !!}
        $php = (string) \preg_replace(
            '/\{!!\s*(.+?)\s*!!\}/s',
            '<?php echo $1; ?>',
            $php,
        );

        // {{ escaped }} — 🔒 auto-escape همیشه
        return (string) \preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?php echo ' . Escaper::class . '::html($1); ?>',
            $php,
        );
    }

    // ─── Pass 7: guard ────────────────────────────────────────────────────

    /** بررسی وجود <? خام (فقط هشدار — templateهای مخزن trusted هستند). */
    public function hasRawPhp(string $compiled): bool
    {
        return \str_contains($compiled, '<?');
    }
}
