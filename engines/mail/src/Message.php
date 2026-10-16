<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Mail
 * Message — پیام ایمیل (fluent value object؛ DOC-065 §3).
 *
 * 🔒 امنیت (DOC-065 §8):
 * - هرگز CR/LF در subject/آدرس/نام پذیرفته نمی‌شود (Header Injection)
 * - آدرس‌ها با `filter_var(FILTER_VALIDATE_EMAIL)` اعتبارسنجی می‌شوند
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class Message
{
    /** @var array{address: string, name: string}|null */
    private ?array $from = null;

    /** @var list<array{address: string, name: string}> */
    private array $to = [];

    /** @var list<array{address: string, name: string}> */
    private array $cc = [];

    /** @var list<array{address: string, name: string}> */
    private array $bcc = [];

    /** @var list<array{address: string, name: string}> */
    private array $replyTo = [];

    private string $subject = '';

    private string $text = '';

    private string $html = '';

    /** @var list<Attachment> */
    private array $attachments = [];

    /** @var array<string, string> */
    private array $headers = [];

    private string $id;

    public function __construct(?string $domain = null)
    {
        $this->id = (string) Ulid::generate() . '@' . ($domain ?? 'tavaan.local');
    }

    // ─── آدرس‌ها ──────────────────────────────────────────────────────────

    public function from(string $address, ?string $name = null): self
    {
        $this->from = $this->makeAddress($address, $name, 'from');

        return $this;
    }

    /** @param string|list<string> $address */
    public function to(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->to[] = $this->makeAddress((string) $item, $name, 'to');
        }

        return $this;
    }

    /** @param string|list<string> $address */
    public function cc(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->cc[] = $this->makeAddress((string) $item, $name, 'cc');
        }

        return $this;
    }

    /** @param string|list<string> $address */
    public function bcc(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->bcc[] = $this->makeAddress((string) $item, $name, 'bcc');
        }

        return $this;
    }

    /** @param string|list<string> $address */
    public function replyTo(string|array $address, ?string $name = null): self
    {
        foreach ((array) $address as $item) {
            $this->replyTo[] = $this->makeAddress((string) $item, $name, 'replyTo');
        }

        return $this;
    }

    // ─── محتوا ────────────────────────────────────────────────────────────

    public function subject(string $subject): self
    {
        $this->assertSingleLine($subject, 'subject');
        $this->subject = $subject;

        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function html(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function attach(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    public function attachPath(string $path, ?string $as = null, ?string $mime = null): self
    {
        return $this->attach(Attachment::fromPath($path, $as, $mime));
    }

    public function attachData(string $data, string $name, ?string $mime = null): self
    {
        return $this->attach(Attachment::fromData($data, $name, $mime));
    }

    /** تصویر inline — در HTML با `cid:{id}` ارجاع می‌شود. */
    public function embed(string $data, string $name, string $cid, ?string $mime = null): self
    {
        return $this->attach(Attachment::inline($data, $name, $cid, $mime));
    }

    public function withHeader(string $name, string $value): self
    {
        $this->assertSingleLine($name, 'header-name');
        $this->assertSingleLine($value, 'header-value');
        $this->headers[$name] = $value;

        return $this;
    }

    // ─── Getterها ─────────────────────────────────────────────────────────

    /** @return array{address: string, name: string}|null */
    public function getFrom(): ?array
    {
        return $this->from;
    }

    /** @return list<array{address: string, name: string}> */
    public function getTo(): array
    {
        return $this->to;
    }

    /** @return list<array{address: string, name: string}> */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return list<array{address: string, name: string}> */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /** @return list<array{address: string, name: string}> */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    /** همهٔ گیرندگان envelope (to + cc + bcc). @return list<string> */
    public function allRecipients(): array
    {
        $out = [];
        foreach ([...$this->to, ...$this->cc, ...$this->bcc] as $address) {
            $out[] = $address['address'];
        }

        return \array_values(\array_unique($out));
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /** @return array<string, string> */
    public function customHeaders(): array
    {
        return $this->headers;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function hasContent(): bool
    {
        return $this->text !== '' || $this->html !== '';
    }

    /** ساخت رشتهٔ کامل MIME (RFC 822/2045/2047). */
    public function toMimeString(): string
    {
        return MimeBuilder::build($this);
    }

    // ─── 🔒 اعتبارسنجی ────────────────────────────────────────────────────

    /** @return array{address: string, name: string} */
    private function makeAddress(string $address, ?string $name, string $field): array
    {
        $address = \trim($address);
        $this->assertSingleLine($address, $field . '.address');

        if (\filter_var($address, \FILTER_VALIDATE_EMAIL) === false) {
            throw MailException::invalidAddress($address);
        }

        if ($name !== null) {
            $this->assertSingleLine($name, $field . '.name');
        }

        return ['address' => $address, 'name' => $name ?? ''];
    }

    /** 🔒 رد CR/LF و کاراکترهای کنترلی (Header Injection — DOC-065 §8). */
    private function assertSingleLine(string $value, string $field): void
    {
        if (\preg_match('/[\r\n\0]/', $value) === 1) {
            throw MailException::headerInjection($field);
        }
    }
}
