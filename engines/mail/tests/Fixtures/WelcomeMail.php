<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Tests\Fixtures;

use Tavaan\Engine\Mail\Mailable;

/** Fixture: ایمیل خوش‌آمدگویی فارسی (DOC-065 §4). */
final class WelcomeMail extends Mailable
{
    public function __construct(
        public readonly string $recipient = 'user@example.com',
        public readonly string $name = 'کاربر',
    ) {
    }

    public function build(): self
    {
        return $this->subject('خوش آمدید به تَوان')
            ->html('<h1>سلام ' . $this->name . '</h1><p>به تَوان خوش آمدید.</p>')
            ->text('سلام ' . $this->name . '، به تَوان خوش آمدید.')
            ->to($this->recipient)
            ->attachData('PDF-BYTES-FAKE', 'invoice.pdf', 'application/pdf')
            ->replyTo('support@example.com');
    }
}
