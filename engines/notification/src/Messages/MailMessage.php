<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Messages;

/**
 * تَوان | Tavaan — Notification
 * MailMessage — پیام کانال ایمیل (fluent؛ DOC-066 §3).
 *
 * ```php
 * (new MailMessage)->subject('سفارش ارسال شد')->line('...')->action('مشاهده', $url);
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class MailMessage
{
    /** @var list<string> */
    private array $lines = [];

    private string $subjectLine = '';

    private string $greetingLine = '';

    private string $salutationLine = '';

    private ?string $actionText = null;

    private ?string $actionUrl = null;

    public function subject(string $subject): self
    {
        $this->subjectLine = $subject;

        return $this;
    }

    public function greeting(string $greeting): self
    {
        $this->greetingLine = $greeting;

        return $this;
    }

    public function line(string $line): self
    {
        $this->lines[] = $line;

        return $this;
    }

    public function salutation(string $salutation): self
    {
        $this->salutationLine = $salutation;

        return $this;
    }

    /** دکمهٔ فراخوان (CTA). */
    public function action(string $text, string $url): self
    {
        $this->actionText = $text;
        $this->actionUrl = $url;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subjectLine;
    }

    /** ساخت HTML سادهٔ خودکفا (بدون نیاز به template). */
    public function toHtml(): string
    {
        $parts = [];
        if ($this->greetingLine !== '') {
            $parts[] = '<h2>' . $this->escape($this->greetingLine) . '</h2>';
        }
        foreach ($this->lines as $line) {
            $parts[] = '<p>' . $this->escape($line) . '</p>';
        }
        if ($this->actionText !== null && $this->actionUrl !== null) {
            $parts[] = '<p><a href="' . $this->escape($this->actionUrl)
                . '" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;'
                . 'text-decoration:none;border-radius:6px;">' . $this->escape($this->actionText) . '</a></p>';
            $parts[] = '<p style="color:#666;font-size:12px;">' . $this->escape($this->actionUrl) . '</p>';
        }
        if ($this->salutationLine !== '') {
            $parts[] = '<p>' . $this->escape($this->salutationLine) . '</p>';
        }

        return '<div dir="rtl" style="font-family:Tahoma,sans-serif;max-width:600px;margin:0 auto;">'
            . \implode("\n", $parts) . '</div>';
    }

    /** نسخهٔ متن ساده. */
    public function toText(): string
    {
        $parts = [];
        if ($this->greetingLine !== '') {
            $parts[] = $this->greetingLine;
            $parts[] = '';
        }
        foreach ($this->lines as $line) {
            $parts[] = $line;
        }
        if ($this->actionText !== null && $this->actionUrl !== null) {
            $parts[] = '';
            $parts[] = $this->actionText . ': ' . $this->actionUrl;
        }
        if ($this->salutationLine !== '') {
            $parts[] = '';
            $parts[] = $this->salutationLine;
        }

        return \implode("\n", $parts);
    }

    private function escape(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
