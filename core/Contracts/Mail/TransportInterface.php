<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Mail;

/**
 * تَوان | Tavaan — Contracts
 * TransportInterface — قرارداد transport ایمیل (DOC-065 §2/§13).
 *
 * پیاده‌سازی‌ها: smtp/sendmail/mail/log/array/ses.
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
interface TransportInterface
{
    /**
     * ارسال یک پیام MIME.
     *
     * @param object $message \Tavaan\Engine\Mail\Message
     *
     * @return object \Tavaan\Engine\Mail\SentMessage
     */
    public function send(object $message): object;

    /** نام transport (smtp/array/…). */
    public function name(): string;
}
