<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Notification
 * ArrayNotification — اعلان عمومی آرایه‌محور (بدون نیاز به کلاس اختصاصی).
 */

namespace Tavaan\Engine\Notification;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Messages\MailMessage;
use Tavaan\Engine\Notification\Messages\SmsMessage;

/**
 * ArrayNotification — اعلان عمومی ساخته‌شده از آرایهٔ payload.
 *
 * برای ماژول‌ها/کیت‌هایی که به مرز معماری (ARCH-003) نمی‌توانند کلاس
 * اختصاصی از `Notification` بسازند؛ از راه binding کانتینر در دسترس است:
 *
 * ```php
 * $notification = $app->make('notification.message', ['payload' => [
 *     'via' => ['mail', 'database'],
 *     'mail' => ['subject' => 'نظر جدید', 'lines' => ['...'], 'action' => ['text' => 'دیدن', 'url' => '/x']],
 *     'database' => ['kind' => 'blog.comment.submitted', 'post_id' => '...'],
 *     'sms' => ['text' => '...'],
 * ]]);
 * ```
 *
 * کلیدهای payload:
 * - `via`: list<string> کانال‌ها (پیش‌فرض: ['database'])
 * - `mail`: {subject, greeting?, lines[], salutation?, action?: {text, url}}
 * - `database`: array (دادهٔ خام برای جدول notifications)
 * - `sms`: {text}
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class ArrayNotification extends Notification
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload = [])
    {
        parent::__construct();
    }

    /** @return list<string> */
    public function via(Notifiable $notifiable): array
    {
        $via = (array) ($this->payload['via'] ?? ['database']);

        return \array_values(\array_filter(\array_map('strval', $via), static fn (string $c): bool => $c !== ''));
    }

    public function toMail(Notifiable $notifiable): ?object
    {
        $mail = $this->payload['mail'] ?? null;
        if (!\is_array($mail)) {
            return null;
        }

        $message = new MailMessage();
        if (isset($mail['subject'])) {
            $message->subject((string) $mail['subject']);
        }
        if (isset($mail['greeting'])) {
            $message->greeting((string) $mail['greeting']);
        }
        foreach ((array) ($mail['lines'] ?? []) as $line) {
            $message->line((string) $line);
        }
        if (isset($mail['salutation'])) {
            $message->salutation((string) $mail['salutation']);
        }
        $action = $mail['action'] ?? null;
        if (\is_array($action) && isset($action['text'], $action['url'])) {
            $message->action((string) $action['text'], (string) $action['url']);
        }

        return $message;
    }

    /** @return array<string, mixed>|null */
    public function toDatabase(Notifiable $notifiable): ?array
    {
        $data = $this->payload['database'] ?? null;

        return \is_array($data) ? $data : null;
    }

    public function toSms(Notifiable $notifiable): ?object
    {
        $sms = $this->payload['sms'] ?? null;
        if (!\is_array($sms) || !isset($sms['text'])) {
            return null;
        }

        return SmsMessage::create((string) $sms['text']);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }
}
