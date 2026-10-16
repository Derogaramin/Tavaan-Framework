<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Transports;

use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * ArrayTransport — در حافظه (تست؛ DOC-065 §2).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class ArrayTransport implements TransportInterface
{
    /** @var list<SentMessage> */
    private array $messages = [];

    public function send(object $message): object
    {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException('ArrayTransport expects ' . Message::class);
        }

        $sent = new SentMessage($message->id(), $message->allRecipients(), $this->name(), $message);
        $this->messages[] = $sent;

        return $sent;
    }

    public function name(): string
    {
        return 'array';
    }

    /** @return list<SentMessage> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function flush(): void
    {
        $this->messages = [];
    }
}
