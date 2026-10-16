<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms;

use Tavaan\Engine\HttpClient\Factory as HttpFactory;
use Tavaan\Engine\Notification\Messages\SmsMessage;

/**
 * تَوان | Tavaan — Notification
 * AbstractHttpSmsGateway — پایهٔ درگاه‌های پیامک HTTP (بدون SDK؛ DOC-066 §5).
 *
 * 🔒 ارسال با موتور HttpClient (SSRF Guard فعال).
 * 🔒 credential فقط از config/ENV — در لاگ mask می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
abstract class AbstractHttpSmsGateway implements SmsGatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected readonly array $config = [],
        protected readonly ?HttpFactory $http = null,
    ) {
    }

    abstract public function name(): string;

    public function send(SmsMessage $message): SmsResult
    {
        $recipients = $message->recipients();
        if ($recipients === []) {
            return SmsResult::fail('no recipients');
        }

        $http = $this->http();
        if ($http === null) {
            return SmsResult::fail('http-client engine unavailable');
        }

        try {
            $response = $http->newRequest()
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->withHeaders($this->authHeaders())
                ->asJson()
                ->post($this->endpoint(), $this->payload($message, $recipients));

            return $this->parseResult($response->status(), $response->body());
        } catch (\Throwable $e) {
            return SmsResult::fail($e->getMessage());
        }
    }

    public function status(string $messageId): DeliveryStatus
    {
        // وضعیت تحویل: پیاده‌سازی پایه — درایورها override می‌کنند
        return DeliveryStatus::Unknown;
    }

    public function credit(): int
    {
        return 0;   // درایورها override می‌کنند
    }

    // ─── نقاط توسعهٔ هر درایور ────────────────────────────────────────────

    abstract protected function endpoint(): string;

    /**
     * @param list<string> $recipients
     *
     * @return array<string, mixed>
     */
    abstract protected function payload(SmsMessage $message, array $recipients): array;

    /** @return array<string, string> */
    protected function headers(): array
    {
        return [];
    }

    /** @return array<string, string> هدرهای auth اختصاصی */
    abstract protected function authHeaders(): array;

    /** نتیجهٔ HTTP → SmsResult. */
    abstract protected function parseResult(int $status, string $body): SmsResult;

    // ─── داخلی ────────────────────────────────────────────────────────────

    protected function http(): ?HttpFactory
    {
        return $this->http;
    }

    protected function senderNumber(SmsMessage $message): string
    {
        return $message->sender() ?? (string) ($this->config['sender'] ?? '');
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
