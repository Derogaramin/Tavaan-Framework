<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\I18n\TranslatorInterface;
use Tavaan\Core\Contracts\Validation\ValidatorInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Support\Arr;
use Tavaan\Engine\Validation\Concerns\ValidatesAttributes;

/**
 * تَوان | Tavaan — Validation
 * Validator — موتور اعتبارسنجی با ۸۵+ قاعده، wildcard، پیام ترجمه‌پذیر (DOC-051).
 *
 * ### الگوریتم (§4)
 * ```
 * parseRules → explodeRules(wildcard) → foreach attribute/rule:
 *   implicit? → حتی اگر نباشد اجرا شود
 *   nullable و null → skip
 *   execute → fail → addError(messageId, replacements)
 *   bail → توقف در اولین خطا
 * → ValidatedData (فقط فیلدهای قاعده‌دار) + MessageBag
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class Validator implements ValidatorInterface
{
    use ValidatesAttributes;

    /** قواعدی که حتی در غیاب/null بودن مقدار اجرا می‌شوند */
    private const IMPLICIT_RULES = [
        'required', 'required_if', 'required_unless', 'required_with', 'required_without',
        'present', 'filled', 'prohibited', 'prohibited_if', 'honeypot', 'exclude_if',
    ];

    /** قواعد نشانگر (بدون بررسی مقداری) */
    private const MARKER_RULES = ['nullable', 'sometimes', 'bail', 'nested', 'each', 'rule_object', 'conditional', 'exclude_unvalidated'];

    private MessageBag $messages;

    /** @var array<string, list<ParsedRule>> قواعد expand‌شده (کلید = مسیر واقعی) */
    private array $expandedRules = [];

    /** @var array<string, mixed> */
    private array $validatedData = [];

    /** @var array<string, true> */
    private array $excluded = [];

    private bool $ran = false;

    /** @var array<string, array{0: \Closure, 1: ?string}> */
    private array $extensions = [];

    /** @var array<string, list<string>> */
    private array $failedRules = [];

    /**
     * @param array<string, mixed>       $data
     * @param array<string, string|list<mixed>> $rules
     * @param array<string, string>      $customMessages
     * @param array<string, string>      $customAttributes
     * @param array<string, mixed>       $config
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $customMessages = [],
        private array $customAttributes = [],
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?ConnectionInterface $db = null,
        private readonly array $config = [],
    ) {
        $this->messages = new MessageBag();
    }

    // ─── API عمومی ────────────────────────────────────────────────────────

    /**
     * اجرا و بازگرداندن دادهٔ معتبر؛ در صورت خطا → ValidationException (422).
     *
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->messages->toArray());
        }

        return $this->validated();
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        $this->runIfNeeded();

        return $this->validatedData;
    }

    public function fails(): bool
    {
        $this->runIfNeeded();

        return $this->messages->isNotEmpty();
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): MessageBag
    {
        $this->runIfNeeded();

        return $this->messages;
    }

    /**
     * قاعدهٔ سفارشی در زمان اجرا.
     *
     * @param \Closure(string, mixed, array<int, string>, \Closure(string): void): bool|void $extension
     */
    public function extend(string $rule, callable $extension, ?string $message = null): void
    {
        $closure = $extension instanceof \Closure ? $extension : \Closure::fromCallable($extension);
        $this->extensions[\strtolower($rule)] = [$closure, $message];
    }

    /** افزودن قاعدهٔ مشروط (فقط اگر فیلد حاضر باشد). */
    public function sometimes(string $attribute, string|array $rules): void
    {
        $existing = $this->rules[$attribute] ?? [];
        $merged = \is_string($existing) ? [$existing] : $existing;
        $merged[] = 'sometimes';
        foreach (RuleParser::parse($rules) as $parsed) {
            $merged[] = $parsed;
        }
        $this->rules[$attribute] = $merged;
        $this->ran = false;
    }

    /** @return array<string, list<string>> */
    public function failedRules(): array
    {
        $this->runIfNeeded();

        return $this->failedRules;
    }

    // ─── اجرای اصلی ───────────────────────────────────────────────────────

    private function runIfNeeded(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;
        $this->validateAll();
    }

    private function validateAll(): void
    {
        $this->expandedRules = $this->explodeRules($this->rules);

        foreach ($this->expandedRules as $attribute => $rules) {
            $this->validateAttribute((string) $attribute, $rules);

            if ($this->shouldStopOnFirst() && $this->messages->isNotEmpty()) {
                break;
            }
        }
    }

    private function shouldStopOnFirst(): bool
    {
        return (bool) ($this->config['stop_on_first'] ?? false);
    }

    /**
     * @param list<ParsedRule> $rules
     */
    private function validateAttribute(string $attribute, array $rules): void
    {
        $names = \array_map(static fn (ParsedRule $r): string => $r->name, $rules);

        $present = Arr::has($this->data, $attribute);
        $value = Arr::get($this->data, $attribute);

        // sometimes: اگر فیلد غایب است، کلاً رد شو
        if (\in_array('sometimes', $names, true) && !$present) {
            return;
        }

        $nullable = \in_array('nullable', $names, true);
        $bail = \in_array('bail', $names, true) || (bool) ($this->config['bail'] ?? false);
        $isNullish = !$present || $value === null || $value === '';

        $validated = true;
        foreach ($rules as $rule) {
            if (\in_array($rule->name, self::MARKER_RULES, true)) {
                continue;
            }

            $implicit = \in_array($rule->name, self::IMPLICIT_RULES, true);

            // مقدار تهی + قاعده غیرimplicit → skip (بدون خطا)
            if ($isNullish && !$implicit) {
                if ($value === null && !$present) {
                    continue;
                }
                if ($nullable || $value === null) {
                    continue;
                }
                // '' با قاعدهٔ غیرimplicit: فقط required-گونه‌ها معنا دارند → skip
                if (!\in_array($rule->name, ['required', 'filled', 'present'], true)) {
                    continue;
                }
            }

            if (!$this->checkRule($rule, $attribute, $value)) {
                $validated = false;
                $this->addError($rule, $attribute, $value);

                if ($bail) {
                    break;
                }
            }

            // exclude_if:field,value → حذف از validated
            if ($rule->name === 'exclude_if' && \count($rule->parameters) >= 2) {
                $other = Arr::get($this->data, $rule->parameters[0]);
                if ((string) $other === (string) $rule->parameters[1]) {
                    $this->excluded[$attribute] = true;

                    return;
                }
            }
        }

        if ($validated && !isset($this->excluded[$attribute]) && $present) {
            Arr::set($this->validatedData, $attribute, $value);
        }

        // فیلد قاعده‌دار ولی غایب و بدون خطا (مثل sometimes/nullable) → در validated نیاید
    }

    private function checkRule(ParsedRule $rule, string $attribute, mixed $value): bool
    {
        // 1) extension ثبت‌شده
        if (isset($this->extensions[$rule->name])) {
            [$callback, ] = $this->extensions[$rule->name];
            $failed = false;
            $result = $callback($attribute, $value, $rule->parameters, function () use (&$failed): void {
                $failed = true;
            });

            return $failed ? false : ($result !== false);
        }

        // 2) Rule object
        if ($rule->isObject()) {
            return $rule->object->passes($attribute, $value);
        }

        // 3) callback (Closure)
        if ($rule->isCallback()) {
            $failed = false;
            $result = ($rule->callback)($attribute, $value, function () use (&$failed): void {
                $failed = true;
            }, $this->data);

            return $failed ? false : ($result !== false);
        }

        // 4) قاعدهٔ داخلی
        $method = 'validate' . \str_replace(' ', '', \ucwords(\str_replace(['_', '-'], ' ', $rule->name)));
        if (\method_exists($this, $method)) {
            return (bool) $this->{$method}($attribute, $value, $rule->parameters);
        }

        throw new \InvalidArgumentException(\sprintf('Unknown validation rule [%s].', $rule->name));
    }

    private function addError(ParsedRule $rule, string $attribute, mixed $value): void
    {
        $ruleName = $rule->isObject() ? $rule->name : $rule->name;

        $this->failedRules[$attribute][] = $ruleName;

        $message = $this->makeMessage($rule, $attribute, $value);
        $this->messages->add($attribute, $message);
    }

    // ─── پیام‌ها ──────────────────────────────────────────────────────────

    private function makeMessage(ParsedRule $rule, string $attribute, mixed $value): string
    {
        $ruleName = $rule->name;

        // 1) پیام سفارشی attr.rule / rule / wildcard
        $candidates = [$attribute . '.' . $ruleName, $ruleName];
        foreach ($candidates as $key) {
            if (isset($this->customMessages[$key])) {
                return $this->replacePlaceholders((string) $this->customMessages[$key], $rule, $attribute, $value);
            }
        }
        // wildcard: items.*.qty.required
        foreach ($this->customMessages as $pattern => $message) {
            if (\str_contains($pattern, '*') && $this->matchesWildcardKey($pattern, $attribute . '.' . $ruleName)) {
                return $this->replacePlaceholders((string) $message, $rule, $attribute, $value);
            }
        }

        // 2) Rule object → messageId خودش
        if ($rule->isObject()) {
            $messageId = $rule->object->message();

            return $this->translateMessage($messageId, $rule, $attribute, $value);
        }

        // 3) ترجمهٔ validation.<rule> (با variant اندازه)
        return $this->translateMessage('validation.' . $ruleName, $rule, $attribute, $value);
    }

    private function translateMessage(string $messageId, ParsedRule $rule, string $attribute, mixed $value): string
    {
        if ($this->translator === null) {
            return $this->replacePlaceholders($messageId, $rule, $attribute, $value);
        }

        // variant اندازه‌ای: validation.min.numeric
        if (\in_array($rule->name, ['min', 'max', 'size', 'between'], true)) {
            $variant = $this->sizeVariant($value);
            $variantMessage = $this->translator->get($messageId . '.' . $variant, [], null);
            if ($variantMessage !== $messageId . '.' . $variant) {
                return $this->replacePlaceholders($variantMessage, $rule, $attribute, $value);
            }
        }

        $translated = $this->translator->get($messageId, [], null);

        return $this->replacePlaceholders($translated !== $messageId ? $translated : $messageId, $rule, $attribute, $value);
    }

    private function replacePlaceholders(string $message, ParsedRule $rule, string $attribute, mixed $value): string
    {
        $replacements = [
            ':attribute' => $this->displayAttribute($attribute),
            ':rule' => $rule->name,
        ];

        $params = $rule->parameters;
        $replacements[':min'] = $params[0] ?? '';
        $replacements[':max'] = $params[1] ?? ($rule->name === 'max' || $rule->name === 'size' || $rule->name === 'length' || $rule->name === 'digits' ? ($params[0] ?? '') : '');
        $replacements[':size'] = $params[0] ?? '';
        $replacements[':values'] = \implode('، ', $params);
        $replacements[':other'] = $this->displayAttribute((string) ($params[0] ?? ''));
        $replacements[':field'] = $this->displayAttribute((string) ($params[0] ?? ''));
        $replacements[':date'] = $params[0] ?? '';
        $replacements[':digits'] = $params[0] ?? '';
        $replacements[':format'] = $params[0] ?? '';

        if (\in_array($rule->name, ['between', 'numeric_range', 'digits_between'], true)) {
            $replacements[':min'] = $params[0] ?? '';
            $replacements[':max'] = $params[1] ?? '';
        }

        return \strtr($message, $replacements);
    }

    private function displayAttribute(string $attribute): string
    {
        if (isset($this->customAttributes[$attribute])) {
            return (string) $this->customAttributes[$attribute];
        }

        foreach ($this->customAttributes as $pattern => $display) {
            if (\str_contains($pattern, '*') && $this->matchesWildcardKey($pattern, $attribute)) {
                return (string) \str_replace('*', (string) $this->wildcardSegment($pattern, $attribute), $display);
            }
        }

        if ($this->translator !== null) {
            $translated = $this->translator->get('validation.attributes.' . $attribute, [], null);
            if ($translated !== 'validation.attributes.' . $attribute) {
                return $translated;
            }
            $leaf = \substr($attribute, (int) \strrpos($attribute, '.') + 1);
            $translatedLeaf = $this->translator->get('validation.attributes.' . $leaf, [], null);
            if ($translatedLeaf !== 'validation.attributes.' . $leaf) {
                return $translatedLeaf;
            }
        }

        return \str_replace(['_', '-'], ' ', $attribute);
    }

    private function sizeVariant(mixed $value): string
    {
        return match (true) {
            \is_array($value) => 'array',
            $value instanceof \Tavaan\Core\Contracts\Http\UploadedFileInterface => 'file',
            \is_numeric($value) => 'numeric',
            default => 'string',
        };
    }

    private function matchesWildcardKey(string $pattern, string $subject): bool
    {
        $regex = '/^' . \str_replace('\*', '[^.]+', \preg_quote($pattern, '/')) . '$/';

        return \preg_match($regex, $subject) === 1;
    }

    private function wildcardSegment(string $pattern, string $subject): string
    {
        $regex = '/^' . \str_replace('\*', '([^.]+)', \preg_quote($pattern, '/')) . '$/';
        if (\preg_match($regex, $subject, $m) === 1) {
            return (string) ($m[1] ?? '');
        }

        return '';
    }

    // ─── Wildcard expansion ───────────────────────────────────────────────

    /**
     * `items.*.qty` → مسیرهای واقعی موجود در data.
     *
     * @param array<string, string|list<mixed>> $rules
     *
     * @return array<string, list<ParsedRule>>
     */
    private function explodeRules(array $rules): array
    {
        $expanded = [];

        foreach ($rules as $attribute => $ruleSet) {
            $parsed = RuleParser::parse($ruleSet);

            if (!\str_contains((string) $attribute, '*')) {
                $expanded[(string) $attribute] = $parsed;
                continue;
            }

            $paths = $this->findPaths((string) $attribute);
            foreach ($paths as $path) {
                $expanded[$path] = \array_merge($expanded[$path] ?? [], $parsed);
            }
        }

        return $expanded;
    }

    /**
     * @return list<string>
     */
    private function findPaths(string $pattern): array
    {
        $regex = '/^' . \str_replace('\*', '[^.]+', \preg_quote($pattern, '/')) . '$/';
        $paths = [];
        $this->walkKeys($this->data, '', function (string $path) use ($regex, &$paths): void {
            if (\preg_match($regex, $path) === 1) {
                $paths[] = $path;
            }
        });

        \sort($paths);

        return $paths;
    }

    /** @param array<array-key, mixed> $data */
    private function walkKeys(array $data, string $prefix, \Closure $visit): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $visit($path);
            if (\is_array($value)) {
                $this->walkKeys($value, $path, $visit);
            }
        }
    }

    // ─── داده ─────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data = $data;
        $this->ran = false;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** ادغام داده (prepareForValidation). */
    public function mergeData(array $input): void
    {
        foreach ($input as $key => $value) {
            $this->data[(string) $key] = $value;
        }
        $this->ran = false;
    }
}
