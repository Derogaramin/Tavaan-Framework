<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Transports;

use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\MailException;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * SendmailTransport — ارسال با باینری sendmail (`-t -i`؛ DOC-065 §2).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class SendmailTransport implements TransportInterface
{
    public function __construct(
        private readonly string $path = '/usr/sbin/sendmail -t -i',
    ) {
    }

    public function send(object $message): object
    {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException('SendmailTransport expects ' . Message::class);
        }

        if ($message->allRecipients() === []) {
            throw MailException::noRecipients();
        }

        if (!\function_exists('proc_open')) {
            throw MailException::transportFailed('sendmail', 'proc_open unavailable');
        }

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @\proc_open($this->path, $descriptors, $pipes);
        if (!\is_resource($process)) {
            throw MailException::transportFailed('sendmail', 'proc_open failed: ' . $this->path);
        }

        try {
            \fwrite($pipes[0], $message->toMimeString());
            \fclose($pipes[0]);
            $stdout = (string) \stream_get_contents($pipes[1]);
            $stderr = (string) \stream_get_contents($pipes[2]);
            \fclose($pipes[1]);
            \fclose($pipes[2]);

            $exit = \proc_close($process);
            if ($exit !== 0) {
                throw MailException::transportFailed('sendmail', 'exit ' . $exit . ' ' . \trim($stderr . $stdout));
            }
        } finally {
            if (\is_resource($process)) {
                @\proc_close($process);
            }
        }

        return new SentMessage($message->id(), $message->allRecipients(), $this->name(), $message);
    }

    public function name(): string
    {
        return 'sendmail';
    }
}
