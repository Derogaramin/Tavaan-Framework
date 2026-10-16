<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Mail\Mailable;
use Tavaan\Engine\Mail\Mailer;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Messages\MailMessage;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * MailChannel — کانال ایمیل با موتور Mail (DOC-066 §2).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class MailChannel implements ChannelInterface
{
    public function __construct(private readonly ?Mailer $mailer = null)
    {
    }

    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->mailer === null) {
            throw NotificationException::channelUnavailable('mail');
        }

        if (!$message instanceof MailMessage) {
            throw NotificationException::invalidMessage('mail', MailMessage::class);
        }

        $route = $this->route($notifiable, $notification);
        if ($route === null) {
            throw NotificationException::missingRoute('mail', $notifiable);
        }

        $mailable = new MailChannelMailable($route, $message);

        return $this->mailer->send($mailable);
    }

    public function name(): string
    {
        return 'mail';
    }

    /** @return string|list<string>|null */
    private function route(Notifiable $notifiable, Notification $notification): string|array|null
    {
        $route = $notifiable->routeNotificationFor('mail', $notification);

        if (\is_string($route) && $route !== '') {
            return $route;
        }
        if (\is_array($route) && $route !== []) {
            return \array_values(\array_map(strval(...), $route));
        }

        return null;
    }
}

/**
 * Mailable داخلی کانال ایمیل — از MailMessage ساخته می‌شود.
 *
 * @api internal
 */
final class MailChannelMailable extends Mailable
{
    /** @param string|list<string> $recipients */
    public function __construct(
        public readonly string|array $recipients,
        private readonly MailMessage $mailMessage,
    ) {
    }

    public function build(): self
    {
        foreach ((array) $this->recipients as $recipient) {
            $this->to($recipient);
        }

        if ($this->mailMessage->getSubject() !== '') {
            $this->subject($this->mailMessage->getSubject());
        }

        return $this
            ->html($this->mailMessage->toHtml())
            ->text($this->mailMessage->toText());
    }
}
