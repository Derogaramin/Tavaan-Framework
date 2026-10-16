<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Jobs;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Mail\Mailable;
use Tavaan\Engine\Mail\Mailer;

/**
 * تَوان | Tavaan — Mail
 * SendQueuedMail — job ارسال ایمیل صف‌روند (DOC-065 §3: Mail::queue).
 *
 * Mailable با Queue Serializer (JSON + Allowlist) سریال می‌شود؛
 * پیوست‌ها base64 (JSON-safe) هستند.
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class SendQueuedMail implements ShouldQueue
{
    public ?int $tries = 3;

    public ?int $timeout = 60;

    public function __construct(public readonly Mailable $mailable)
    {
    }

    public function handle(Mailer $mailer): void
    {
        $mailer->send($this->mailable);
    }
}
