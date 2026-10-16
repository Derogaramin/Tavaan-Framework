<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

/**
 * تَوان | Tavaan — Mail
 * Mailable — پایهٔ ایمیل‌های شیء‌محور کاربر (DOC-065 §4).
 *
 * ```php
 * final class WelcomeMail extends Mailable
 * {
 *     use SerializesModels;   // از موتور Queue — برای Mail::queue
 *
 *     public function __construct(public readonly User $user) {}
 *
 *     public function build(): self
 *     {
 *         return $this->subject('خوش آمدید')
 *             ->view('emails.welcome')
 *             ->with(['user' => $this->user])
 *             ->attach('/path/invoice.pdf', as: 'invoice.pdf', mime: 'application/pdf')
 *             ->replyTo('support@example.com');
 *     }
 * }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
abstract class Mailable
{
    /** @var list<array{address: string, name: string}> */
    public array $to = [];

    /** @var list<array{address: string, name: string}> */
    public array $cc = [];

    /** @var list<array{address: string, name: string}> */
    public array $bcc = [];

    /** @var list<array{address: string, name: string}> */
    public array $replyTo = [];

    public ?string $subjectLine = null;

    /** نام template موتور View */
    public ?string $viewTemplate = null;

    /** نام template متن ساده */
    public ?string $textTemplate = null;

    /** HTML آماده (بدون template) */
    public ?string $htmlContent = null;

    /** متن سادهٔ آماده */
    public ?string $textContent = null;

    /** @var array<string, mixed> */
    public array $viewData = [];

    /** @var list<Attachment> */
    public array $attachments = [];

    /** @var array<string, string> */
    public array $customHeaders = [];

    /** mailer خاص (null = پیش‌فرض). */
    public ?string $mailer = null;

    // ─── build (کاربر override می‌کند) ───────────────────────────────────

    /** پیکربندی ایمیل — پیش از ارسال فراخوانی می‌شود. */
    public function build(): self
    {
        return $this;
    }

    // ─── Fluent API ───────────────────────────────────────────────────────

    public function subject(string $subject): self
    {
        $this->subjectLine = $subject;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function view(string $template, array $data = []): self
    {
        $this->viewTemplate = $template;
        $this->viewData = \array_merge($this->viewData, $data);

        return $this;
    }

    /** متن سادهٔ خام (بدون template). */
    public function text(string $text): self
    {
        $this->textContent = $text;

        return $this;
    }

    /** template موتور View برای متن ساده. @param array<string, mixed> $data */
    public function textView(string $template, array $data = []): self
    {
        $this->textTemplate = $template;
        $this->viewData = \array_merge($this->viewData, $data);

        return $this;
    }

    public function html(string $html): self
    {
        $this->htmlContent = $html;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function with(array $data): self
    {
        $this->viewData = \array_merge($this->viewData, $data);

        return $this;
    }

    /** @param string|list<string> $address */
    public function to(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->to[] = ['address' => (string) $item, 'name' => $name ?? ''];
        }

        return $this;
    }

    /** @param string|list<string> $address */
    public function cc(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->cc[] = ['address' => (string) $item, 'name' => $name ?? ''];
        }

        return $this;
    }

    /** @param string|list<string> $address */
    public function bcc(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->bcc[] = ['address' => (string) $item, 'name' => $name ?? ''];
        }

        return $this;
    }

    /** @param string|list<string> $address */
    public function replyTo(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->replyTo[] = ['address' => (string) $item, 'name' => $name ?? ''];
        }

        return $this;
    }

    public function attach(string $path, ?string $as = null, ?string $mime = null): self
    {
        $this->attachments[] = Attachment::fromPath($path, $as, $mime);

        return $this;
    }

    public function attachData(string $data, string $name, ?string $mime = null): self
    {
        $this->attachments[] = Attachment::fromData($data, $name, $mime);

        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $this->customHeaders = \array_merge($this->customHeaders, $headers);

        return $this;
    }

    public function onMailer(string $mailer): self
    {
        $this->mailer = $mailer;

        return $this;
    }

    // ─── ساخت Message (توسط Mailer) ───────────────────────────────────────

    /**
     * تبدیل به Message — templateها با ViewFactory رندر می‌شوند.
     *
     * @param callable(string, array<string, mixed>): string|null $renderer رندر template (null → فقط محتوای خام)
     */
    public function toMessage(?callable $renderer = null, ?string $domain = null): Message
    {
        $message = new Message($domain);

        foreach ($this->to as $address) {
            $message->to($address['address'], $address['name'] !== '' ? $address['name'] : null);
        }
        foreach ($this->cc as $address) {
            $message->cc($address['address'], $address['name'] !== '' ? $address['name'] : null);
        }
        foreach ($this->bcc as $address) {
            $message->bcc($address['address'], $address['name'] !== '' ? $address['name'] : null);
        }
        foreach ($this->replyTo as $address) {
            $message->replyTo($address['address'], $address['name'] !== '' ? $address['name'] : null);
        }

        if ($this->subjectLine !== null) {
            $message->subject($this->subjectLine);
        }

        // رندر templateها
        if ($this->viewTemplate !== null) {
            $message->html($this->renderTemplate($this->viewTemplate, $renderer));
        }
        if ($this->textTemplate !== null) {
            $message->text($this->renderTemplate($this->textTemplate, $renderer));
        }

        // محتوای خام
        if ($this->htmlContent !== null && $message->getHtml() === '') {
            $message->html($this->htmlContent);
        }
        if ($this->textContent !== null && $message->getText() === '') {
            $message->text($this->textContent);
        }

        if (!$message->hasContent()) {
            throw MailException::viewRequired();
        }

        foreach ($this->attachments as $attachment) {
            $message->attach($attachment);
        }

        foreach ($this->customHeaders as $name => $value) {
            $message->withHeader($name, $value);
        }

        return $message;
    }

    /** @param array<string, mixed>|null $renderer */
    private function renderTemplate(string $template, ?callable $renderer): string
    {
        if ($renderer === null) {
            throw MailException::viewRequired();
        }

        return $renderer($template, $this->viewData);
    }
}
