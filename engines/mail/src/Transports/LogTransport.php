<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Transports;

use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * LogTransport — نوشتن ایمیل در لاگ (dev؛ DOC-065 §2).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class LogTransport implements TransportInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly string $channel = 'mail',
    ) {
    }

    public function send(object $message): object
    {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException('LogTransport expects ' . Message::class);
        }

        $this->logger?->info('mail.sent', [
            'channel' => $this->channel,
            'id' => $message->id(),
            'to' => $message->allRecipients(),
            'subject' => $message->getSubject(),
            'transport' => $this->name(),
            // 🔒 بدنه کامل لاگ نمی‌شود (احتمال دادهٔ حساس) — فقط اندازه
            'mime_bytes' => \strlen($message->toMimeString()),
        ]);

        return new SentMessage($message->id(), $message->allRecipients(), $this->name(), $message);
    }

    public function name(): string
    {
        return 'log';
    }
}
