<?php

declare(strict_types=1);

namespace Tavaan\Testing\Http;

use Tavaan\Core\Contracts\Http\ResponseInterface;
use Tavaan\Engine\Http\Response;
use Tavaan\Testing\AssertionFailed;
use Tavaan\Testing\TestCase;

/**
 * تَوان | Tavaan — Testing
 * TestResponse — Assertionهای پاسخ HTTP با API روان (DOC-084E §8).
 *
 * ```php
 * $this->get('/api/v1/products')
 *     ->assertOk()
 *     ->assertJsonStructure(['data' => [['id', 'title']]])
 *     ->assertJsonCount(10, 'data');
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestResponse
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    /** @var array<string, mixed>|null */
    private ?array $decodedJson = null;

    private bool $jsonDecoded = false;

    public function __construct(private readonly ResponseInterface $response)
    {
    }

    // ─── دسترسی‌ها ─────────────────────────────────────────────────────────

    public function baseResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->statusCode();
    }

    public function content(): string
    {
        return $this->response->body();
    }

    /**
     * بدنهٔ JSON رمزگشایی‌شده (یا مقدار زیرکلید dot-path).
     *
     * @return array<string, mixed>|null
     */
    public function json(?string $key = null): ?array
    {
        $decoded = $this->decodedJson();
        if ($key === null) {
            return $decoded;
        }
        $value = self::dataGet($decoded, $key);

        return \is_array($value) ? $value : null;
    }

    public function header(string $name): ?string
    {
        foreach ($this->response->headers() as $key => $values) {
            if (\strcasecmp((string) $key, $name) === 0) {
                return (string) ($values[0] ?? '');
            }
        }

        return null;
    }

    // ─── Assertionهای وضعیت ───────────────────────────────────────────────

    public function assertStatus(int $expected): self
    {
        $actual = $this->status();
        TestCase::assertThat(
            $actual === $expected,
            \sprintf('Expected response status [%d] but received [%d].', $expected, $actual),
        );

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertSuccessful(): self
    {
        $status = $this->status();
        TestCase::assertThat(
            $status >= 200 && $status < 300,
            \sprintf('Expected successful status (2xx) but received [%d].', $status),
        );

        return $this;
    }

    // ─── Assertionهای JSON ────────────────────────────────────────────────

    /**
     * تطبیق زیرمجموعه‌ای بازگشتی (هر کلید/مقدار مورد انتظار باید موجود باشد).
     *
     * @param array<array-key, mixed> $subset
     */
    public function assertJson(array $subset): self
    {
        $decoded = $this->decodedJsonOrFail();
        TestCase::assertThat(
            self::matchesSubset($subset, $decoded),
            \sprintf(
                'Response JSON does not contain expected subset.%sExpected: %s%sActual: %s',
                \PHP_EOL,
                self::encode($subset),
                \PHP_EOL,
                self::encode($decoded),
            ),
        );

        return $this;
    }

    /**
     * بررسی ساختار JSON.
     *
     * - `['id', 'title']` → این کلیدها در سطح جاری وجود دارند
     * - `['data' => ['id']]` → کلید `data` وجود دارد و زیرساختارش `id` را دارد
     * - `['data' => [['id']]]` → هر عضو `data` کلید `id` را دارد
     * - `['data' => ['*' => ['id']]]` → فرم صریح wildcard
     *
     * @param array<array-key, mixed> $structure
     */
    public function assertJsonStructure(array $structure): self
    {
        $decoded = $this->decodedJsonOrFail();
        $path = '$';
        TestCase::assertThat(
            self::matchesStructure($structure, $decoded, $path),
            \sprintf('Response JSON structure mismatch at [%s].', $path),
        );

        return $this;
    }

    public function assertJsonCount(int $expected, string $key): self
    {
        $decoded = $this->decodedJsonOrFail();
        $value = self::dataGet($decoded, $key);
        TestCase::assertThat(
            \is_array($value) && \count($value) === $expected,
            \sprintf(
                'Expected JSON key [%s] to contain %d item(s), got %s.',
                $key,
                $expected,
                \is_array($value) ? (string) \count($value) : \get_debug_type($value),
            ),
        );

        return $this;
    }

    /**
     * خطاهای اعتبارسنجی (422) — سازگار با شکل RFC 9457 فریمورک:
     * `context.fields` (خروجی JsonRenderer) و نیز `errors` (سبک کلاسیک).
     *
     * @param list<string>|string $fields
     */
    public function assertJsonValidationErrors(array|string $fields = []): self
    {
        $decoded = $this->decodedJsonOrFail();
        $expected = \is_string($fields) ? [$fields] : $fields;

        $reported = [];
        $errors = self::dataGet($decoded, 'errors');
        if (\is_array($errors)) {
            $reported = \array_map('strval', \array_is_list($errors) ? $errors : \array_keys($errors));
        }
        $contextFields = self::dataGet($decoded, 'context.fields');
        if (\is_array($contextFields)) {
            $reported = [...$reported, ...\array_map('strval', $contextFields)];
        }

        $missing = \array_values(\array_diff($expected, $reported));
        TestCase::assertThat(
            $missing === [],
            \sprintf(
                'Expected validation errors for [%s] but response reported [%s].',
                \implode(', ', $expected),
                \implode(', ', $reported),
            ),
        );

        return $this;
    }

    // ─── Assertionهای دیگر ────────────────────────────────────────────────

    /**
     * پاسخ redirect با Location برابر (یا هم‌مسیر) URI داده‌شده.
     */
    public function assertRedirect(string $uri = ''): self
    {
        $status = $this->status();
        TestCase::assertThat(
            \in_array($status, self::REDIRECT_STATUSES, true),
            \sprintf('Expected redirect status (3xx) but received [%d].', $status),
        );

        $location = (string) ($this->header('Location') ?? '');
        if ($uri !== '') {
            TestCase::assertThat(
                $location === $uri || self::normalizeLocation($location) === self::normalizeLocation($uri),
                \sprintf('Expected redirect to [%s] but got [%s].', $uri, $location),
            );
        }

        return $this;
    }

    public function assertSee(string $needle): self
    {
        TestCase::assertThat(
            \str_contains($this->content(), $needle),
            \sprintf('Response body does not contain [%s].', $needle),
        );

        return $this;
    }

    public function assertDontSee(string $needle): self
    {
        TestCase::assertThat(
            !\str_contains($this->content(), $needle),
            \sprintf('Response body unexpectedly contains [%s].', $needle),
        );

        return $this;
    }

    public function assertCookie(string $name): self
    {
        $cookies = $this->response instanceof Response ? $this->response->cookies() : [];
        $names = [];
        foreach ($cookies as $cookie) {
            $names[] = $cookie->name();
        }
        TestCase::assertThat(
            \in_array($name, $names, true),
            \sprintf('Response does not contain cookie [%s] (cookies: %s).', $name, $names === [] ? 'none' : \implode(', ', $names)),
        );

        return $this;
    }

    public function assertHeader(string $name): self
    {
        TestCase::assertThat(
            $this->header($name) !== null,
            \sprintf('Response does not contain header [%s].', $name),
        );

        return $this;
    }

    // ─── internals ────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function decodedJson(): ?array
    {
        if (!$this->jsonDecoded) {
            $this->jsonDecoded = true;
            $decoded = \json_decode($this->content(), true);
            $this->decodedJson = \is_array($decoded) ? $decoded : null;
        }

        return $this->decodedJson;
    }

    /** @return array<string, mixed> */
    private function decodedJsonOrFail(): array
    {
        $decoded = $this->decodedJson();
        if ($decoded === null) {
            throw AssertionFailed::for(\sprintf(
                'Response body is not valid JSON: %s',
                \substr($this->content(), 0, 120),
            ));
        }

        return $decoded;
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    private static function matchesSubset(array $expected, mixed $actual): bool
    {
        if (!\is_array($actual)) {
            return false;
        }

        foreach ($expected as $key => $value) {
            if (!\array_key_exists($key, $actual)) {
                return false;
            }
            if (\is_array($value)) {
                if (!self::matchesSubset($value, $actual[$key])) {
                    return false;
                }
                continue;
            }
            if ($actual[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $structure
     */
    private static function matchesStructure(array $structure, mixed $actual, string &$path): bool
    {
        if (!\is_array($actual)) {
            return false;
        }

        // فهرست → یا کلیدها (همه رشته) یا «ساختار هر عضو» (همه فهرستِ رشته)
        if (\array_is_list($structure)) {
            $allStrings = true;
            $allKeyLists = true;
            foreach ($structure as $item) {
                $allStrings = $allStrings && \is_string($item);
                $allKeyLists = $allKeyLists && \is_array($item) && \array_is_list($item)
                    && \count(\array_filter($item, 'is_string')) === \count($item);
            }

            if ($allStrings) {
                foreach ($structure as $key) {
                    if (!\array_key_exists((string) $key, $actual)) {
                        $path .= '.' . $key;

                        return false;
                    }
                }

                return true;
            }

            if ($allKeyLists) {
                $keys = [];
                foreach ($structure as $keyList) {
                    foreach ($keyList as $key) {
                        $keys[] = (string) $key;
                    }
                }
                foreach ($actual as $index => $item) {
                    if (!\is_array($item)) {
                        $path .= '[' . $index . ']';

                        return false;
                    }
                    foreach ($keys as $key) {
                        if (!\array_key_exists($key, $item)) {
                            $path .= '[' . $index . '].' . $key;

                            return false;
                        }
                    }
                }

                return true;
            }

            return false;
        }

        // ساختار کلید → زیرساختار (کلید `*` = wildcard روی فهرست)
        foreach ($structure as $key => $nested) {
            if ($key === '*') {
                foreach ($actual as $index => $item) {
                    $childPath = $path . '[' . $index . ']';
                    if (!self::matchesStructure(\is_array($nested) ? $nested : [], $item, $childPath)) {
                        $path = $childPath;

                        return false;
                    }
                }
                continue;
            }

            if (!\array_key_exists((string) $key, $actual)) {
                $path .= '.' . $key;

                return false;
            }
            if (\is_array($nested) && $nested !== []) {
                $childPath = $path . '.' . $key;
                if (!self::matchesStructure($nested, $actual[$key], $childPath)) {
                    $path = $childPath;

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * دسترسی dot-path به آرایهٔ رمزگشایی‌شده (`data.0.id`).
     *
     * @param array<array-key, mixed>|null $data
     */
    private static function dataGet(?array $data, string $key): mixed
    {
        if ($data === null) {
            return null;
        }
        if (\array_key_exists($key, $data)) {
            return $data[$key];
        }

        $value = $data;
        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function normalizeLocation(string $uri): string
    {
        $path = \parse_url($uri, PHP_URL_PATH);
        if (\is_string($path) && $path !== '') {
            $query = (string) (\parse_url($uri, PHP_URL_QUERY) ?: '');

            return $path . ($query !== '' ? '?' . $query : '');
        }

        // URLهای malformed مثل `http:///admin` (host خالی در محیط تست بدون
        // request context) — strip scheme//host به‌صورت regex
        $stripped = (string) \preg_replace('#^[a-zA-Z][a-zA-Z0-9+.\-]*://[^/]*#', '', $uri);

        return $stripped !== '' ? $stripped : $uri;
    }

    private static function encode(mixed $value): string
    {
        $json = \json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? \get_debug_type($value) : $json;
    }
}
