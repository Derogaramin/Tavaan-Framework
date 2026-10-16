<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms;

use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Engine\Notification\Messages\SmsMessage;

/**
 * تَوان | Tavaan — Notification
 * LogSmsGateway — پیامک در لاگ (dev/تست؛ بدون ارسال واقعی).
 *
 * 🔒 شماره‌ها در لاگ mask می‌شوند (DOC-066 §7).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class LogSmsGateway implements SmsGatewayInterface
{
    /** @var list<SmsMessage> */
    private array $sent = [];

    public function __construct(private readonly ?LoggerInterface $logger = null)
    {
    }

    public function send(SmsMessage $message): SmsResult
    {
        $this->sent[] = $message;

        $masked = \array_map(self::maskPhone(...), $message->recipients());
        $this->logger?->info('notification.sms.logged', [
            'recipients' => $masked,
            'content_length' => \strlen($message->content),
        ]);

        return SmsResult::ok('log-' . \count($this->sent));
    }

    public function status(string $messageId): DeliveryStatus
    {
        return DeliveryStatus::Sent;
    }

    public function credit(): int
    {
        return \PHP_INT_MAX;
    }

    public function name(): string
    {
        return 'log';
    }

    /** @return list<SmsMessage> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function flush(): void
    {
        $this->sent = [];
    }

    /** 🔒 mask شمارهٔ موبایل: 0912***4567 (DOC-066 §7). */
    public static function maskPhone(string $phone): string
    {
        $digits = \preg_replace('/\D/', '', $phone) ?? $phone;

        // نرمال‌سازی فرمت بین‌المللی ایران: 98912… → 0912…
        if (\strlen($digits) === 12 && \str_starts_with($digits, '98')) {
            $digits = '0' . \substr($digits, 2);
        }

        if (\strlen($digits) < 7) {
            return '***';
        }

        return \substr($digits, 0, 4) . '***' . \substr($digits, -4);
    }
}
