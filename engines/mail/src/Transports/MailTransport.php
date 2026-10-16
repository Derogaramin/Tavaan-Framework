<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Transports;

use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\MailException;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\MimeBuilder;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * MailTransport — تابع `mail()` خود PHP (fallback؛ DOC-065 §2).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class MailTransport implements TransportInterface
{
    public function send(object $message): object
    {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException('MailTransport expects ' . Message::class);
        }

        $recipients = $message->allRecipients();
        if ($recipients === []) {
            throw MailException::noRecipients();
        }

        if (!\function_exists('mail')) {
            throw MailException::transportFailed('mail', 'mail() disabled');
        }

        $to = \implode(', ', $recipients);
        $subject = MimeBuilder::encodeHeader($message->getSubject());

        // بدنه: MIME کامل بدون هدرهای To/Subject (mail() خودش می‌گذارد)
        $mime = $message->toMimeString();
        [$headerBlock, $body] = \explode("\r\n\r\n", $mime, 2);

        $headers = [];
        foreach (\explode("\r\n", $headerBlock) as $line) {
            if (\str_starts_with(\strtolower($line), 'to:') || \str_starts_with(\strtolower($line), 'subject:')) {
                continue;   // 🔒 از injection جلوگیری می‌شود (mail() اینها را جدا می‌برد)
            }
            $headers[] = $line;
        }

        $ok = @\mail($to, $subject, $body, \implode("\r\n", $headers));
        if (!$ok) {
            throw MailException::transportFailed('mail', 'mail() returned false');
        }

        return new SentMessage($message->id(), $recipients, $this->name(), $message);
    }

    public function name(): string
    {
        return 'mail';
    }
}
