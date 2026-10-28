<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Concerns;

use Tavaan\Core\Contracts\Http\UploadedFileInterface;
use Tavaan\Engine\Validation\Support\IranianChecks;
use Tavaan\Support\Arr;

/**
 * تَوان | Tavaan — Validation
 * ValidatesAttributes — پیاده‌سازی قواعد داخلی (۸۵+ قاعده — DOC-051 §3).
 *
 * 🔒 قواعد دیتابیس فقط با Prepared Statement + allowlist شناسه.
 * 🔒 regex با محافظ ReDoS (طول + رد nested quantifier).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
trait ValidatesAttributes
{
    // ─── ۳٫۱ حضور و نوع ───────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateRequired(string $attribute, mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        // رشتهٔ فقط-فاصله تهی محسوب می‌شود (هم‌راستا با Laravel — مثال: عنوان «   »)
        if (\is_string($value) && \trim($value) === '') {
            return false;
        }
        if (\is_array($value) && \count($value) === 0) {
            return false;
        }
        if ($value instanceof \Countable && \count($value) === 0) {
            return false;
        }

        return true;
    }

    /** @param list<string> $params */
    protected function validateRequiredIf(string $attribute, mixed $value, array $params): bool
    {
        [$field, $values] = [$params[0] ?? null, \array_slice($params, 1)];
        if ($field === null) {
            return true;
        }

        $other = Arr::get($this->data, $field);
        foreach ($values as $expected) {
            if ((string) $other === (string) $expected) {
                return $this->validateRequired($attribute, $value, []);
            }
        }

        return true;
    }

    /** @param list<string> $params */
    protected function validateRequiredUnless(string $attribute, mixed $value, array $params): bool
    {
        [$field, $values] = [$params[0] ?? null, \array_slice($params, 1)];
        if ($field === null) {
            return true;
        }

        $other = Arr::get($this->data, $field);
        foreach ($values as $expected) {
            if ((string) $other === (string) $expected) {
                return true;
            }
        }

        return $this->validateRequired($attribute, $value, []);
    }

    /** @param list<string> $params */
    protected function validateRequiredWith(string $attribute, mixed $value, array $params): bool
    {
        foreach ($params as $field) {
            $other = Arr::get($this->data, $field);
            if ($other !== null && $other !== '' && Arr::has($this->data, $field)) {
                return $this->validateRequired($attribute, $value, []);
            }
        }

        return true;
    }

    /** @param list<string> $params */
    protected function validateRequiredWithout(string $attribute, mixed $value, array $params): bool
    {
        foreach ($params as $field) {
            $other = Arr::get($this->data, $field);
            if ($other === null || $other === '' || !Arr::has($this->data, $field)) {
                return $this->validateRequired($attribute, $value, []);
            }
        }

        return true;
    }

    /** @param list<string> $params */
    protected function validatePresent(string $attribute, mixed $value, array $params): bool
    {
        return Arr::has($this->data, $attribute);
    }

    /** @param list<string> $params */
    protected function validateFilled(string $attribute, mixed $value, array $params): bool
    {
        if (!Arr::has($this->data, $attribute)) {
            return true;
        }

        return $this->validateRequired($attribute, $value, []);
    }

    /** @param list<string> $params */
    protected function validateString(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value);
    }

    /** @param list<string> $params */
    protected function validateInteger(string $attribute, mixed $value, array $params): bool
    {
        return \is_int($value) || (\is_string($value) && \preg_match('/^-?\d+$/', $value) === 1);
    }

    /** @param list<string> $params */
    protected function validateNumeric(string $attribute, mixed $value, array $params): bool
    {
        return \is_int($value) || \is_float($value) || (\is_string($value) && \is_numeric($value));
    }

    /** @param list<string> $params */
    protected function validateBoolean(string $attribute, mixed $value, array $params): bool
    {
        return \in_array($value, [true, false, 1, 0, '1', '0'], true);
    }

    /** @param list<string> $params */
    protected function validateArray(string $attribute, mixed $value, array $params): bool
    {
        return \is_array($value);
    }

    /** @param list<string> $params */
    protected function validateObject(string $attribute, mixed $value, array $params): bool
    {
        return \is_object($value);
    }

    /** @param list<string> $params */
    protected function validateDate(string $attribute, mixed $value, array $params): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }
        if (!\is_string($value) && !\is_int($value)) {
            return false;
        }

        return \strtotime((string) $value) !== false;
    }

    /** @param list<string> $params */
    protected function validateDigits(string $attribute, mixed $value, array $params): bool
    {
        $n = (int) ($params[0] ?? 0);

        return \preg_match('/^\d+$/', (string) $value) === 1 && \strlen((string) $value) === $n;
    }

    /** @param list<string> $params */
    protected function validateDigitsBetween(string $attribute, mixed $value, array $params): bool
    {
        $len = \strlen((string) $value);

        return \preg_match('/^\d+$/', (string) $value) === 1
            && $len >= (int) ($params[0] ?? 0)
            && $len <= (int) ($params[1] ?? \PHP_INT_MAX);
    }

    // ─── ۳٫۲ طول و بازه ───────────────────────────────────────────────────

    private function sizeOf(string $attribute, mixed $value): float|int
    {
        if (\is_numeric($value) && $this->hasNumericRule($attribute)) {
            return $value + 0;
        }
        if (\is_array($value)) {
            return \count($value);
        }
        if ($value instanceof UploadedFileInterface) {
            return (int) \round($value->size() / 1024);   // KB
        }
        if (\is_bool($value)) {
            return $value ? 1 : 0;
        }

        return \mb_strlen((string) $value, 'UTF-8');
    }

    private function hasNumericRule(string $attribute): bool
    {
        foreach ($this->expandedRules[$attribute] ?? [] as $rule) {
            if (\in_array($rule->name, ['numeric', 'integer', 'decimal', 'money', 'percent', 'positive', 'negative', 'non_negative'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $params */
    protected function validateMin(string $attribute, mixed $value, array $params): bool
    {
        return $this->sizeOf($attribute, $value) >= (float) ($params[0] ?? 0);
    }

    /** @param list<string> $params */
    protected function validateMax(string $attribute, mixed $value, array $params): bool
    {
        return $this->sizeOf($attribute, $value) <= (float) ($params[0] ?? \PHP_INT_MAX);
    }

    /** @param list<string> $params */
    protected function validateBetween(string $attribute, mixed $value, array $params): bool
    {
        $size = $this->sizeOf($attribute, $value);

        return $size >= (float) ($params[0] ?? 0) && $size <= (float) ($params[1] ?? \PHP_INT_MAX);
    }

    /** @param list<string> $params */
    protected function validateSize(string $attribute, mixed $value, array $params): bool
    {
        return $this->sizeOf($attribute, $value) === (float) ($params[0] ?? 0)
            || (string) $this->sizeOf($attribute, $value) === (string) ($params[0] ?? '');
    }

    /** @param list<string> $params */
    protected function validateLength(string $attribute, mixed $value, array $params): bool
    {
        return \mb_strlen((string) $value, 'UTF-8') === (int) ($params[0] ?? 0);
    }

    /** @param list<string> $params */
    protected function validateGt(string $attribute, mixed $value, array $params): bool
    {
        return $this->compareWithField($attribute, $value, $params) === 1;
    }

    /** @param list<string> $params */
    protected function validateGte(string $attribute, mixed $value, array $params): bool
    {
        return ($this->compareWithField($attribute, $value, $params) ?? -1) >= 0;
    }

    /** @param list<string> $params */
    protected function validateLt(string $attribute, mixed $value, array $params): bool
    {
        return $this->compareWithField($attribute, $value, $params) === -1;
    }

    /** @param list<string> $params */
    protected function validateLte(string $attribute, mixed $value, array $params): bool
    {
        return ($this->compareWithField($attribute, $value, $params) ?? 1) <= 0;
    }

    /**
     * مقایسهٔ هم‌جنس دو مقدار (عدد↔عدد، آرایه↔تعداد، فایل↔KB، رشته↔طول).
     *
     * @param list<string> $params
     */
    private function compareWithField(string $attribute, mixed $value, array $params): ?int
    {
        $other = Arr::get($this->data, (string) ($params[0] ?? ''));

        if (\is_numeric($value) && \is_numeric($other)) {
            return ($value + 0) <=> ($other + 0);
        }
        if (\is_array($value) && \is_array($other)) {
            return \count($value) <=> \count($other);
        }
        if ($value instanceof UploadedFileInterface && $other instanceof UploadedFileInterface) {
            return $value->size() <=> $other->size();
        }
        if (\is_string($value) && \is_string($other) && !\is_numeric($value) && !\is_numeric($other)) {
            return \mb_strlen($value, 'UTF-8') <=> \mb_strlen($other, 'UTF-8');
        }

        return $this->sizeOf($attribute, $value) <=> $this->sizeOf((string) ($params[0] ?? ''), $other);
    }

    // ─── ۳٫۳ قالب ─────────────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateEmail(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_string($value) || \strlen($value) > 320) {
            return false;
        }

        return \filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @param list<string> $params */
    protected function validateUrl(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_string($value) || \filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = \strtolower((string) \parse_url($value, PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true);
    }

    /** @param list<string> $params */
    protected function validateUuid(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di',
            $value,
        ) === 1;
    }

    /** @param list<string> $params */
    protected function validateUlid(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', \strtoupper($value)) === 1;
    }

    /** @param list<string> $params */
    protected function validateIp(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /** @param list<string> $params */
    protected function validateIpv4(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /** @param list<string> $params */
    protected function validateIpv6(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /** @param list<string> $params */
    protected function validateMacAddress(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateRegex(string $attribute, mixed $value, array $params): bool
    {
        $pattern = (string) ($params[0] ?? '');
        $this->assertSafeRegex($pattern);

        return \is_string($value) && \preg_match($pattern, $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateNotRegex(string $attribute, mixed $value, array $params): bool
    {
        $pattern = (string) ($params[0] ?? '');
        $this->assertSafeRegex($pattern);

        return !\is_string($value) || \preg_match($pattern, $value) !== 1;
    }

    /** 🔒 ReDoS guard: طول الگو + رد nested quantifier (DOC-051 §9). */
    private function assertSafeRegex(string $pattern): void
    {
        if (\strlen($pattern) > 500) {
            throw new \InvalidArgumentException('Regex pattern too long (max 500 chars) — ReDoS guard.');
        }
        if (\preg_match('/[)+}]\s*[+*{]|\(\?[^)]*\)[+*]/', $pattern) === 1) {
            throw new \InvalidArgumentException('Nested quantifier detected in regex — ReDoS guard.');
        }
    }

    /** @param list<string> $params */
    protected function validateAlpha(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \preg_match('/^[\pL\s]+$/u', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateAlphaNum(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \preg_match('/^[\pL\pN\s]+$/u', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateAlphaDash(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \preg_match('/^[\pL\pN\s_-]+$/u', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateSlug(string $attribute, mixed $value, array $params): bool
    {
        // حروف (لاتین/فارسی)، رقم، خط تیره؛ بدون تیرهٔ ابتدا/انتها/متوالی
        return \is_string($value) && \preg_match('/^[\pL\pN]+(?:-[\pL\pN]+)*$/u', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateJson(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_string($value)) {
            return false;
        }
        try {
            \json_decode($value, flags: JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    /** @param list<string> $params */
    protected function validateDateFormat(string $attribute, mixed $value, array $params): bool
    {
        $format = (string) ($params[0] ?? '');
        if (!\is_string($value) || $format === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat($format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /** @param list<string> $params */
    protected function validateTimezone(string $attribute, mixed $value, array $params): bool
    {
        return \is_string($value) && \in_array($value, \DateTimeZone::listIdentifiers(), true);
    }

    // ─── ۳٫۴ مقایسه و مجموعه ──────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateIn(string $attribute, mixed $value, array $params): bool
    {
        return \in_array((string) $value, $params, true);
    }

    /** @param list<string> $params */
    protected function validateNotIn(string $attribute, mixed $value, array $params): bool
    {
        return !\in_array((string) $value, $params, true);
    }

    /** @param list<string> $params */
    protected function validateConfirmed(string $attribute, mixed $value, array $params): bool
    {
        $confirmation = Arr::get($this->data, $attribute . '_confirmation');

        return $confirmation !== null && (string) $confirmation === (string) $value;
    }

    /** @param list<string> $params */
    protected function validateSame(string $attribute, mixed $value, array $params): bool
    {
        $other = Arr::get($this->data, (string) ($params[0] ?? ''));

        return (string) $other === (string) $value;
    }

    /** @param list<string> $params */
    protected function validateDifferent(string $attribute, mixed $value, array $params): bool
    {
        $other = Arr::get($this->data, (string) ($params[0] ?? ''));

        return (string) $other !== (string) $value;
    }

    /** @param list<string> $params */
    protected function validateAccepted(string $attribute, mixed $value, array $params): bool
    {
        return \in_array($value, ['yes', 'on', '1', 1, true, 'true'], true);
    }

    /** @param list<string> $params */
    protected function validateDeclined(string $attribute, mixed $value, array $params): bool
    {
        return \in_array($value, ['no', 'off', '0', 0, false, 'false'], true);
    }

    // ─── ۳٫۵ فایل ─────────────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateFile(string $attribute, mixed $value, array $params): bool
    {
        return $value instanceof UploadedFileInterface && $value->isValid();
    }

    /** @param list<string> $params */
    protected function validateImage(string $attribute, mixed $value, array $params): bool
    {
        if (!$this->validateFile($attribute, $value, $params)) {
            return false;
        }
        \assert($value instanceof UploadedFileInterface);

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        return \in_array(\strtolower($value->extension()), $allowed, true)
            && \str_starts_with($value->mimeType(), 'image/');
    }

    /** @param list<string> $params */
    protected function validateMimes(string $attribute, mixed $value, array $params): bool
    {
        if (!$value instanceof UploadedFileInterface) {
            return false;
        }

        $ext = \strtolower($value->extension());

        return \in_array($ext, \array_map('\strtolower', $params), true);
    }

    /** @param list<string> $params */
    protected function validateMimetypes(string $attribute, mixed $value, array $params): bool
    {
        if (!$value instanceof UploadedFileInterface) {
            return false;
        }

        foreach ($params as $pattern) {
            if (\fnmatch($pattern, $value->mimeType())) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $params */
    protected function validateDimensions(string $attribute, mixed $value, array $params): bool
    {
        if (!$value instanceof UploadedFileInterface) {
            return false;
        }

        $path = \method_exists($value, 'path') ? (string) $value->path() : '';
        if ($path === '' || !\is_file($path)) {
            return false;
        }

        $info = @\getimagesize($path);
        if ($info === false) {
            return false;
        }

        [$width, $height] = $info;

        foreach ($params as $constraint) {
            [$key, $expected] = \array_pad(\explode('=', $constraint, 2), 2, '');
            $actual = match ($key) {
                'min_width' => $width >= (int) $expected,
                'max_width' => $width <= (int) $expected,
                'min_height' => $height >= (int) $expected,
                'max_height' => $height <= (int) $expected,
                'width' => $width === (int) $expected,
                'height' => $height === (int) $expected,
                'ratio' => $height > 0 && \abs($width / $height - (float) $expected) < 0.01,
                default => true,
            };
            if ($actual === false) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $params */
    protected function validateMaxFilesize(string $attribute, mixed $value, array $params): bool
    {
        if (!$value instanceof UploadedFileInterface) {
            return false;
        }

        return $value->size() <= (int) ($params[0] ?? 0) * 1024;
    }

    /** @param list<string> $params */
    protected function validateUploadedFile(string $attribute, mixed $value, array $params): bool
    {
        return $value instanceof UploadedFileInterface && $value->error() === 0;
    }

    // ─── ۳٫۶ دیتابیس ──────────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateUnique(string $attribute, mixed $value, array $params): bool
    {
        [$table, $column] = $this->dbIdentifiers($params, $attribute);
        $ignoreId = $params[2] ?? null;
        $ignoreColumn = $this->assertIdentifier($params[3] ?? 'id');

        if ($ignoreId !== null && $ignoreId !== '' && \strtolower((string) $ignoreId) === 'null') {
            $ignoreId = null;
        }
        // {id} placeholder → مقدار فیلد id از data
        if ($ignoreId === '{id}' || $ignoreId === 'NULL') {
            $ignoreId = Arr::get($this->data, 'id');
        }

        $query = $this->dbTable($table)->where($column, $value);
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where($ignoreColumn, '!=', $ignoreId);
        }

        return !$query->exists();
    }

    /** @param list<string> $params */
    protected function validateExists(string $attribute, mixed $value, array $params): bool
    {
        [$table, $column] = $this->dbIdentifiers($params, $attribute);

        return $this->dbTable($table)->where($column, $value)->exists();
    }

    /** @param list<string> $params */
    protected function validateExistsMultiple(string $attribute, mixed $value, array $params): bool
    {
        [$table, $column] = $this->dbIdentifiers($params, $attribute);
        if (!\is_array($value)) {
            return false;
        }

        return $this->dbTable($table)->whereIn($column, $value)->count() === \count(\array_unique($value));
    }

    /** @param list<string> $params */
    protected function validateUniqueComposite(string $attribute, mixed $value, array $params): bool
    {
        $table = $this->assertIdentifier($params[0] ?? '');
        $columns = \array_slice($params, 1);
        if ($columns === []) {
            return false;
        }

        $query = $this->dbTable($table);
        foreach ($columns as $column) {
            $column = $this->assertIdentifier($column);
            $query->where($column, Arr::get($this->data, $column));
        }

        return !$query->exists();
    }

    /**
     * @param list<string> $params
     *
     * @return array{0: string, 1: string} [table, column]
     */
    private function dbIdentifiers(array $params, string $attribute): array
    {
        $table = $this->assertIdentifier($params[0] ?? '');
        $leaf = \substr($attribute, (int) \strrpos($attribute, '.') + 1);
        $column = $this->assertIdentifier($params[1] ?? $leaf);

        return [$table, $column];
    }

    /** 🔒 allowlist شناسه — جلوگیری از injection در نام جدول/ستون. */
    private function assertIdentifier(string $identifier): string
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid database identifier [%s].', $identifier));
        }

        return $identifier;
    }

    private function dbTable(string $table): \Tavaan\Core\Contracts\Database\QueryBuilderInterface
    {
        if ($this->db === null) {
            throw new \LogicException('Database rules (unique/exists) require a database connection.');
        }

        return $this->db->table($table);
    }

    // ─── ۳٫۷ بومی ایران 🇮🇷 ─────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateIranMobile(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::mobile((string) $value);
    }

    /** @param list<string> $params */
    protected function validateIranNationalCode(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::nationalCode((string) $value);
    }

    /** @param list<string> $params */
    protected function validateIranPostalCode(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::postalCode((string) $value);
    }

    /** @param list<string> $params */
    protected function validateIranSheba(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::sheba((string) $value);
    }

    /** @param list<string> $params */
    protected function validateIranCardNumber(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::cardNumber((string) $value);
    }

    /** @param list<string> $params */
    protected function validateIranCompanyId(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::companyId((string) $value);
    }

    /** @param list<string> $params */
    protected function validateJalaliDate(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::jalaliDate((string) $value, (string) ($params[0] ?? 'Y/m/d'));
    }

    /** @param list<string> $params */
    protected function validateJalaliDateFormat(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::jalaliDate((string) $value, (string) ($params[0] ?? 'Y/m/d'));
    }

    /** @param list<string> $params */
    protected function validatePersianDigits(string $attribute, mixed $value, array $params): bool
    {
        return IranianChecks::persianDigits((string) $value);
    }

    // ─── ۳٫۸ امنیتی ───────────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateHoneypot(string $attribute, mixed $value, array $params): bool
    {
        $field = (string) ($params[0] ?? $attribute);
        $trap = Arr::get($this->data, $field);

        return $trap === null || $trap === '';
    }

    /** @param list<string> $params */
    protected function validateNoHtml(string $attribute, mixed $value, array $params): bool
    {
        return !\is_string($value) || $value === \strip_tags($value);
    }

    /** @param list<string> $params */
    protected function validateSafeUrl(string $attribute, mixed $value, array $params): bool
    {
        return $value === null || IranianChecks::safeUrl((string) $value);
    }

    /** سیاست رمز از config (password_rules). @param list<string> $params */
    protected function validatePassword(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        $policy = (array) ($this->config['password_rules'] ?? ['min:12', 'letters', 'numbers']);

        foreach ($policy as $rule) {
            $ok = match ($rule) {
                'letters' => \preg_match('/\pL/u', $value) === 1,
                'numbers' => \preg_match('/\d/', $value) === 1,
                'symbols' => \preg_match('/[^\pL\d]/u', $value) === 1,
                'mixed_case' => \preg_match('/\p{Ll}/u', $value) === 1 && \preg_match('/\p{Lu}/u', $value) === 1,
                'not_compromised' => $this->validateNotCompromised($attribute, $value, []),
                default => \str_starts_with((string) $rule, 'min:')
                    ? \mb_strlen($value, 'UTF-8') >= (int) \substr((string) $rule, 4)
                    : true,
            };
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $params */
    protected function validateStrongPassword(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_string($value) || \mb_strlen($value, 'UTF-8') < 8) {
            return false;
        }

        return \preg_match('/\pL/u', $value) === 1
            && \preg_match('/\d/', $value) === 1
            && \preg_match('/[^\pL\d]/u', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validateNotCompromised(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        // 🔒 blacklist داخلی (رایج‌ترین رمزها) با مقایسهٔ hash constant-time
        $hash = \hash('sha256', \strtolower($value));
        foreach (IranianChecks::COMMON_PASSWORD_HASHES as $known) {
            if (\hash_equals($known, $hash)) {
                return false;
            }
        }

        return true;
    }

    // ─── ۳٫۹ ریاضی/پول ────────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateDecimal(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_numeric($value)) {
            return false;
        }

        $maxDecimals = (int) ($params[0] ?? 2);
        $str = (string) $value;
        $decimals = \str_contains($str, '.') ? \strlen(\substr($str, (int) \strpos($str, '.') + 1)) : 0;

        return $decimals <= $maxDecimals;
    }

    /** پول = int minor-unit (ADR-0013). @param list<string> $params */
    protected function validateMoney(string $attribute, mixed $value, array $params): bool
    {
        if (\is_int($value)) {
            return $value >= 0;
        }

        return \is_string($value) && \preg_match('/^\d+$/', $value) === 1;
    }

    /** @param list<string> $params */
    protected function validatePositive(string $attribute, mixed $value, array $params): bool
    {
        return \is_numeric($value) && $value + 0 > 0;
    }

    /** @param list<string> $params */
    protected function validateNegative(string $attribute, mixed $value, array $params): bool
    {
        return \is_numeric($value) && $value + 0 < 0;
    }

    /** @param list<string> $params */
    protected function validateNonNegative(string $attribute, mixed $value, array $params): bool
    {
        return \is_numeric($value) && $value + 0 >= 0;
    }

    /** @param list<string> $params */
    protected function validatePercent(string $attribute, mixed $value, array $params): bool
    {
        return \is_numeric($value) && $value + 0 >= 0 && $value + 0 <= 100;
    }

    /** @param list<string> $params */
    protected function validateNumericRange(string $attribute, mixed $value, array $params): bool
    {
        return \is_numeric($value)
            && $value + 0 >= (float) ($params[0] ?? 0)
            && $value + 0 <= (float) ($params[1] ?? \PHP_INT_MAX);
    }

    // ─── ۳٫۱۰ پیشرفته ─────────────────────────────────────────────────────

    /** @param list<string> $params */
    protected function validateProhibited(string $attribute, mixed $value, array $params): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** @param list<string> $params */
    protected function validateProhibitedIf(string $attribute, mixed $value, array $params): bool
    {
        $other = Arr::get($this->data, (string) ($params[0] ?? ''));
        if ((string) $other !== (string) ($params[1] ?? '')) {
            return true;
        }

        return $this->validateProhibited($attribute, $value, []);
    }

    /** @param list<string> $params */
    protected function validateExcludeIf(string $attribute, mixed $value, array $params): bool
    {
        return true;   // منطق حذف در حلقهٔ اصلی Validator انجام می‌شود
    }

    /** @param list<string> $params */
    protected function validateDistinct(string $attribute, mixed $value, array $params): bool
    {
        if (!\is_array($value)) {
            return false;
        }

        $serialized = \array_map(static fn ($v): string => \is_scalar($v) ? (string) $v : (string) \json_encode($v), $value);

        return \count($serialized) === \count(\array_unique($serialized));
    }

    /** marker — قواعد marker همیشه پاس هستند. @param list<string> $params */
    protected function validateNullable(string $attribute, mixed $value, array $params): bool
    {
        return true;
    }

    /** @param list<string> $params */
    protected function validateSometimes(string $attribute, mixed $value, array $params): bool
    {
        return true;
    }

    /** @param list<string> $params */
    protected function validateBail(string $attribute, mixed $value, array $params): bool
    {
        return true;
    }

    /** @param list<string> $params */
    protected function validateNested(string $attribute, mixed $value, array $params): bool
    {
        return \is_array($value);
    }

    /** @param list<string> $params */
    protected function validateEach(string $attribute, mixed $value, array $params): bool
    {
        return \is_array($value);
    }
}
