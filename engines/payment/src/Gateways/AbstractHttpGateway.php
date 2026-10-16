<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Gateways;

use Tavaan\Core\Contracts\Payment\GatewayInterface;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\HttpClient\Factory;
use Tavaan\Engine\HttpClient\Response;
use Tavaan\Engine\Payment\Exceptions\GatewayException;
use Tavaan\Engine\Payment\Exceptions\PaymentException;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PaymentResolver;

/**
 * تَوان | Tavaan — Payment
 * AbstractHttpGateway — پایهٔ مشترک درگاه‌های HTTP (DOC-073).
 *
 * - Factory موتور http-client (🔒 SSRF guard داخلی؛ Fake در تست)
 * - اعتبارنامه فقط از config/.env (🔒 بدون هاردکد)
 * - maskPan (🔒 نشت PAN)
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
abstract class AbstractHttpGateway implements GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config = [],
        protected readonly ?Factory $http = null,
    ) {
    }

    /**
     * کلیدهای پیکربندی موردنیاز درگاه — نبود هرکدام → GatewayNotConfigured.
     *
     * @return list<string>
     */
    abstract protected function requiredConfig(): array;

    protected function http(): Factory
    {
        if ($this->http !== null) {
            return $this->http;
        }

        // Factory مشترک کانتینر → Http::fake() در تست‌ها روی آن می‌نشیند
        $container = PaymentResolver::container();
        if ($container !== null && $container->bound('http.client')) {
            $factory = $container->make('http.client');
            if ($factory instanceof Factory) {
                return $factory;
            }
        }

        return new Factory();
    }

    protected function configValue(string $key, mixed $default = null): mixed
    {
        $value = $this->config[$key] ?? $default;

        return $value === '' ? $default : $value;
    }

    protected function apiUrl(string $default): string
    {
        return \rtrim((string) $this->configValue('api_url', $default), '/');
    }

    protected function sandbox(): bool
    {
        return (bool) $this->configValue('sandbox', false);
    }

    protected function timeout(): int
    {
        return (int) $this->configValue('timeout', 30);
    }

    /** 🔒 بررسی وجود اعتبارنامه پیش از هر تماس شبکه. */
    protected function assertConfigured(): void
    {
        $missing = [];
        foreach ($this->requiredConfig() as $key) {
            $value = $this->config[$key] ?? null;
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw PaymentException::gatewayNotConfigured($this->name(), \implode(', ', $missing));
        }
    }

    /**
     * 🔒 mask کارت: فقط ۶ رقم اول + ۴ رقم آخر (DOC-073 §7).
     */
    public static function maskPan(string $pan): string
    {
        $digits = \preg_replace('/\D+/', '', $pan) ?? '';

        return \strlen($digits) >= 10 ? \substr($digits, 0, 6) . '****' . \substr($digits, -4) : '';
    }

    /**
     * تبدیل Money به تومان (درگاه‌های ایرانی — 🔒 تومان نه ریال، DOC-073 §5).
     */
    protected static function toToman(Money $money): int
    {
        return match ($money->currency) {
            'IRT' => $money->amount,
            'IRR' => \intdiv($money->amount, 10),
            default => throw PaymentException::invalidAmount('unsupported currency ' . $money->currency),
        };
    }

    /** تبدیل Money به ریال. */
    protected static function toRial(Money $money): int
    {
        return match ($money->currency) {
            'IRT' => $money->amount * 10,
            'IRR' => $money->amount,
            default => throw PaymentException::invalidAmount('unsupported currency ' . $money->currency),
        };
    }

    /** POST JSON → [status, decoded, response]. */
    protected function postJson(string $url, array $payload, array $headers = []): array
    {
        $response = $this->send('json', $url, $payload, ['Accept' => 'application/json', ...$headers]);

        return [$response->status(), $response->json() ?? [], $response];
    }

    /** POST فرم (form-urlencoded). */
    protected function postForm(string $url, array $payload, array $headers = []): array
    {
        $response = $this->send('form', $url, $payload, $headers);

        return [$response->status(), $response->json() ?? [], $response];
    }

    /** POST بدنهٔ خام (XML/SOAP). */
    protected function postRaw(string $url, string $body, string $contentType = 'text/xml; charset=utf-8', array $headers = []): Response
    {
        return $this->send('raw', $url, $body, ['Content-Type' => $contentType, ...$headers]);
    }

    protected function get(string $url, array $headers = []): Response
    {
        return $this->send('get', $url, null, $headers);
    }

    /**
     * @param array<string, mixed>|string|null $data
     * @param array<string, string>            $headers
     */
    private function send(string $mode, string $url, mixed $data, array $headers): Response
    {
        $this->assertConfigured();

        try {
            $request = $this->http()->withHeaders($headers)->timeout($this->timeout());
            $request = match ($mode) {
                'json' => $request->asJson(),
                'form' => $request->asForm(),
                default => $request,
            };

            return $mode === 'get' ? $request->get($url) : $request->post($url, $data);
        } catch (GatewayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw GatewayException::connection($this->name(), $mode, $e);
        }
    }

    /**
     * مقدار یک تگ در XML (پارسر سبک — بدون ext-dom؛ سازگار با zero-dependency).
     */
    protected static function xmlValue(string $xml, string $tag): ?string
    {
        $pattern = '/<' . \preg_quote($tag, '/') . '>\s*(?:<!\[CDATA\[(.*?)\]\]>|(.*?))\s*<\/' . \preg_quote($tag, '/') . '>/s';
        if (\preg_match($pattern, $xml, $m) !== 1) {
            return null;
        }

        return $m[1] !== '' ? $m[1] : ($m[2] !== '' ? $m[2] : '');
    }

    /** escape مقدار برای بدنهٔ XML. */
    protected static function xmlEscape(mixed $value): string
    {
        return \htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** پیش‌فرض: درگاه‌های بدون API استعلام → Pending (بدون حدس موفقیت — 🔒 محافظه‌کارانه). */
    public function inquire(string $authority): PaymentStatus
    {
        return PaymentStatus::Pending;
    }
}
