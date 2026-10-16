<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Messages;

/**
 * تَوان | Tavaan — Notification
 * SmsMessage — پیام کانال پیامک (DOC-066 §3/§5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class SmsMessage
{
    /** @var list<string> گیرندگان (توسط کانال از route پر می‌شود) */
    private array $recipients = [];

    private ?string $sender = null;

    private function __construct(public readonly string $content)
    {
    }

    public static function create(string $content): self
    {
        return new self($content);
    }

    /** @param string|list<string> $to */
    public function to(string|array $to): self
    {
        foreach ((array) $to as $recipient) {
            $this->recipients[] = (string) $recipient;
        }

        return $this;
    }

    /** خط مبدأ (sender number) — override تنظیم gateway. */
    public function from(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function sender(): ?string
    {
        return $this->sender;
    }
}
