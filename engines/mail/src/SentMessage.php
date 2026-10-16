<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

/**
 * تَوان | Tavaan — Mail
 * SentMessage — رسید ارسال (شناسه + گیرندگان + transport).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class SentMessage
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        private readonly string $id,
        private readonly array $recipients,
        private readonly string $transport = '',
        private readonly ?Message $message = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function transport(): string
    {
        return $this->transport;
    }

    public function message(): ?Message
    {
        return $this->message;
    }

    public function originalMessage(): ?Message
    {
        return $this->message;
    }
}
