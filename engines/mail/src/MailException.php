<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Mail
 * MailException — خطاهای موتور ایمیل (DOC-065).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
class MailException extends TavaanException
{
    /** 🔒 تزریق هدر — CR/LF در subject/آدرس (DOC-065 §8). */
    public static function headerInjection(string $field): static
    {
        return new static('mail.header_injection', ['field' => $field], 'مقادیر هدر ایمیل نباید CR/LF داشته باشند.');
    }

    public static function invalidAddress(string $address): static
    {
        return new static('mail.invalid_address', ['address' => $address]);
    }

    public static function noRecipients(): static
    {
        return new static('mail.no_recipients', [], 'حداقل یک گیرنده (to/cc/bcc) لازم است.');
    }

    public static function attachmentUnreadable(string $path): static
    {
        return new static('mail.attachment_unreadable', ['path' => $path]);
    }

    public static function smtpConnectFailed(string $host, int $port, string $error): static
    {
        return new static('mail.smtp_connect_failed', ['host' => $host, 'port' => $port, 'error' => $error]);
    }

    public static function smtpUnexpectedResponse(string $command, string $response): static
    {
        return new static('mail.smtp_bad_response', ['command' => $command, 'response' => \mb_substr($response, 0, 200)]);
    }

    public static function smtpTlsFailed(): static
    {
        return new static('mail.smtp_tls_failed', [], 'ارتقای STARTTLS ناموفق بود.');
    }

    public static function transportFailed(string $transport, string $reason): static
    {
        return new static('mail.transport_failed', ['transport' => $transport, 'reason' => $reason]);
    }

    public static function unknownTransport(string $transport): static
    {
        return new static('mail.unknown_transport', ['transport' => $transport]);
    }

    public static function viewRequired(): static
    {
        return new static('mail.view_required', [], 'Mailable باید view یا text یا html داشته باشد.');
    }

    public static function sesFailed(int $status, string $body): static
    {
        return new static('mail.ses_failed', ['status' => $status, 'body' => \mb_substr($body, 0, 300)]);
    }
}
