<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

/**
 * تَوان | Tavaan — Mail
 * PendingMail — builder فلونت `Mail::to(...)->cc(...)->send(...)` (DOC-065 §3).
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class PendingMail
{
    /** @var list<string> */
    private array $to = [];

    /** @var list<string> */
    private array $cc = [];

    /** @var list<string> */
    private array $bcc = [];

    /** @param string|list<string>|object $users شیء با property `email` یا متد routeNotificationFor('mail') */
    public function __construct(private readonly Mailer $mailer, string|array|object $users)
    {
        foreach ($this->resolveAddresses($users) as $address) {
            $this->to[] = $address;
        }
    }

    /** @param string|list<string>|object $users */
    public function cc(string|array|object $users): self
    {
        foreach ($this->resolveAddresses($users) as $address) {
            $this->cc[] = $address;
        }

        return $this;
    }

    /** @param string|list<string>|object $users */
    public function bcc(string|array|object $users): self
    {
        foreach ($this->resolveAddresses($users) as $address) {
            $this->bcc[] = $address;
        }

        return $this;
    }

    public function send(Mailable|string $mailable, array $data = [], ?callable $callback = null): SentMessage
    {
        return $this->mailer->send($this->applyRecipients($mailable, $data), $data, $callback);
    }

    public function queue(Mailable $mailable): mixed
    {
        return $this->mailer->queue($this->applyRecipients($mailable, []));
    }

    public function later(\DateTimeInterface|int $delay, Mailable $mailable): mixed
    {
        return $this->mailer->later($delay, $this->applyRecipients($mailable, []));
    }

    /** @param array<string, mixed> $data */
    private function applyRecipients(Mailable|string $mailable, array $data): Mailable|string
    {
        if (!$mailable instanceof Mailable) {
            return $mailable;   // view string → Mailer گیرندگان را از callback می‌گیرد
        }

        foreach ($this->to as $address) {
            $mailable->to($address);
        }
        foreach ($this->cc as $address) {
            $mailable->cc($address);
        }
        foreach ($this->bcc as $address) {
            $mailable->bcc($address);
        }

        return $mailable;
    }

    /**
     * @param string|list<string>|object $users
     *
     * @return list<string>
     */
    private function resolveAddresses(string|array|object $users): array
    {
        if (\is_string($users)) {
            return [$users];
        }

        if (\is_array($users)) {
            return \array_values(\array_map(strval(...), $users));
        }

        // شیء Notifiable/User
        if (\method_exists($users, 'routeNotificationFor')) {
            $route = $users->routeNotificationFor('mail');
            if (\is_string($route) && $route !== '') {
                return [$route];
            }
            if (\is_array($route)) {
                return \array_values(\array_map(strval(...), $route));
            }
        }

        foreach (['email', 'mail', 'address'] as $property) {
            if (\property_exists($users, $property) && \is_string($users->{$property}) && $users->{$property} !== '') {
                return [$users->{$property}];
            }
        }

        throw MailException::invalidAddress($users::class);
    }
}
