<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\Events\MessageSending;
use Tavaan\Engine\Mail\Events\MessageSent;
use Tavaan\Engine\Mail\Jobs\SendQueuedMail;
use Tavaan\Engine\Mail\Transports\ArrayTransport;
use Tavaan\Engine\Mail\Transports\LogTransport;
use Tavaan\Engine\Mail\Transports\MailTransport;
use Tavaan\Engine\Mail\Transports\SendmailTransport;
use Tavaan\Engine\Mail\Transports\SesTransport;
use Tavaan\Engine\Mail\Transports\SmtpTransport;

/**
 * تَوان | Tavaan — Mail
 * Mailer — نقطهٔ ورود عمومی موتور ایمیل (Facade: Mail؛ DOC-065 §3).
 *
 * ```php
 * Mail::to('user@example.com')->send(new WelcomeMail($user));
 * Mail::send('emails.welcome', ['user' => $user], fn (Message $m) => $m->to($e)->subject('…'));
 * Mail::queue(new WelcomeMail($user));          // async (موتور queue)
 * Mail::later(now()->addMinutes(5), new ReminderMail($user));
 * Mail::raw('متن ساده', fn (Message $m) => $m->to($e));
 * Mail::fake(); Mail::assertSent(WelcomeMail::class);   // تست
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class Mailer
{
    /** @var list<array{mailable: ?object, message: Message, sent: SentMessage}> */
    private array $sent = [];

    private bool $faking = false;

    private ?string $mailerName = null;

    /** @var array<string, TransportInterface> */
    private array $transports = [];

    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?ContainerInterface $container = null,
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    // ─── API اصلی (DOC-065 §3) ────────────────────────────────────────────

    /** @param string|list<string>|object $users */
    public function to(string|array|object $users): PendingMail
    {
        return new PendingMail($this, $users);
    }

    /**
     * ارسال یک Mailable یا view.
     *
     * @param Mailable|string       $mailableOrView شیء Mailable یا نام template
     * @param array<string, mixed>  $data           دادهٔ template (وقتی رشته است)
     * @param callable(Message): void|null $callback تنظیم گیرندگان/subject روی Message
     */
    public function send(Mailable|string $mailableOrView, array $data = [], ?callable $callback = null): SentMessage
    {
        if ($mailableOrView instanceof Mailable) {
            $mailableOrView->build();
            $message = $mailableOrView->toMessage($this->renderer(), $this->messageDomain());
            $callback === null ?: $callback($message);

            return $this->dispatchSend($message, $mailableOrView, $mailableOrView->mailer);
        }

        // view string + callback
        $message = new Message($this->messageDomain());
        $message->html($this->renderTemplate($mailableOrView, $data));
        if ($callback !== null) {
            $callback($message);
        }

        return $this->dispatchSend($message, null);
    }

    /** ارسال متن ساده. */
    public function raw(string $text, callable $callback): SentMessage
    {
        $message = new Message($this->messageDomain());
        $message->text($text);
        $callback($message);

        return $this->dispatchSend($message, null);
    }

    /** ارسال async با موتور queue (DOC-065 §3). */
    public function queue(Mailable $mailable): mixed
    {
        return $this->queuePush($mailable, null);
    }

    /** ارسال async با تأخیر. */
    public function later(\DateTimeInterface|int $delay, Mailable $mailable): mixed
    {
        return $this->queuePush($mailable, $delay);
    }

    /** mailer خاص — `Mail::mailer('ses')->send(...)`. */
    public function mailer(?string $name = null): self
    {
        $clone = clone $this;
        $clone->mailerName = $name;

        return $clone;
    }

    // ─── Fake / Assertion (تست) ───────────────────────────────────────────

    public function fake(): self
    {
        $this->faking = true;
        $this->sent = [];

        return $this;
    }

    public function isFaking(): bool
    {
        return $this->faking;
    }

    /**
     * @param class-string|callable $mailableOrCallback کلاس Mailable یا predicate
     * @param callable|null         $callback          بررسی تکمیلی (mailable, message, sent)
     */
    public function assertSent(string|callable $mailableOrCallback, ?callable $callback = null): void
    {
        foreach ($this->sent as $entry) {
            if (\is_callable($mailableOrCallback)) {
                if ($mailableOrCallback($entry['mailable'], $entry['message'], $entry['sent'])) {
                    return;
                }
                continue;
            }

            $matchesClass = $entry['mailable'] instanceof $mailableOrCallback;
            if ($matchesClass && ($callback === null || $callback($entry['mailable'], $entry['message'], $entry['sent']))) {
                return;
            }
        }

        $expected = \is_string($mailableOrCallback) ? $mailableOrCallback : 'predicate';
        throw new MailException('mail.assertion_failed', ['expected' => $expected]);
    }

    /** @param class-string $mailableClass */
    public function assertNotSent(string $mailableClass): void
    {
        foreach ($this->sent as $entry) {
            if ($entry['mailable'] instanceof $mailableClass) {
                throw new MailException('mail.assertion_failed', ['expected' => '!' . $mailableClass]);
            }
        }
    }

    public function assertSentCount(int $count): void
    {
        if (\count($this->sent) !== $count) {
            throw new MailException('mail.assertion_failed', ['expected' => 'count=' . $count, 'actual' => \count($this->sent)]);
        }
    }

    /** @return list<array{mailable: ?object, message: Message, sent: SentMessage}> */
    public function sent(): array
    {
        return $this->sent;
    }

    // ─── Transport ────────────────────────────────────────────────────────

    public function transport(?string $name = null): TransportInterface
    {
        $name ??= $this->mailerName ?? (string) ($this->config?->get('mail.default', 'array') ?? 'array');

        return $this->transports[$name] ??= $this->resolveTransport($name);
    }

    private function resolveTransport(string $name): TransportInterface
    {
        $config = (array) ($this->config?->get('mail.mailers.' . $name, []) ?? []);
        $driver = (string) ($config['transport'] ?? $name);

        return match ($driver) {
            'smtp' => new SmtpTransport($config),
            'sendmail' => new SendmailTransport((string) ($config['path'] ?? '/usr/sbin/sendmail -t -i')),
            'mail' => new MailTransport(),
            'log' => new LogTransport($this->resolveLogger((string) ($config['channel'] ?? 'mail')), (string) ($config['channel'] ?? 'mail')),
            'array' => new ArrayTransport(),
            'ses' => new SesTransport($config),
            default => throw MailException::unknownTransport($driver),
        };
    }

    private function resolveLogger(string $channel): ?\Tavaan\Core\Contracts\Log\LoggerInterface
    {
        if ($this->container === null || !$this->container->bound('log')) {
            return null;
        }

        try {
            $logManager = $this->container->make('log');

            return \method_exists($logManager, 'channel') ? $logManager->channel($channel) : $logManager;
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function dispatchSend(Message $message, ?object $mailable, ?string $mailerName = null): SentMessage
    {
        $this->applyGlobalFrom($message);

        if ($message->allRecipients() === []) {
            throw MailException::noRecipients();
        }

        $this->events?->dispatch(new MessageSending($message));

        // حالت Fake: بدون transport واقعی — ضبط برای assertion
        if ($this->faking) {
            $sent = new SentMessage($message->id(), $message->allRecipients(), 'array', $message);
            $this->sent[] = ['mailable' => $mailable, 'message' => $message, 'sent' => $sent];
            $this->events?->dispatch(new MessageSent($message, $sent));

            return $sent;
        }

        $transport = $this->transport($mailerName);
        $result = $transport->send($message);

        if (!$result instanceof SentMessage) {
            throw MailException::transportFailed($transport->name(), 'invalid SentMessage');
        }

        $this->sent[] = ['mailable' => $mailable, 'message' => $message, 'sent' => $result];
        $this->events?->dispatch(new MessageSent($message, $result));

        return $result;
    }

    /** from سراسری از config وقتی Message از ندارد. */
    private function applyGlobalFrom(Message $message): void
    {
        if ($message->getFrom() !== null) {
            return;
        }

        $address = (string) ($this->config?->get('mail.from.address', '') ?? '');
        if ($address === '') {
            return;
        }

        $name = $this->config?->get('mail.from.name');
        $message->from($address, \is_string($name) && $name !== '' ? $name : null);
    }

    private function queuePush(Mailable $mailable, \DateTimeInterface|int|null $delay): mixed
    {
        if ($this->container === null || !$this->container->bound('queue')) {
            throw MailException::transportFailed('queue', 'موتور queue برای Mail::queue لازم است');
        }

        $queue = $this->container->make('queue');
        $job = new SendQueuedMail($mailable);

        return $delay === null ? $queue->push($job) : $queue->later($delay, $job);
    }

    /** @return callable(string, array<string, mixed>): string|null */
    private function renderer(): ?callable
    {
        if ($this->container === null || !$this->container->bound('view')) {
            return null;
        }

        return function (string $template, array $data): string {
            return $this->renderTemplate($template, $data);
        };
    }

    /** @param array<string, mixed> $data */
    private function renderTemplate(string $template, array $data): string
    {
        if ($this->container === null || !$this->container->bound('view')) {
            throw MailException::transportFailed('view', 'موتور view برای رندر template لازم است');
        }

        $view = $this->container->make('view');

        return (string) $view->render($template, $data);
    }

    private function messageDomain(): ?string
    {
        $from = (string) ($this->config?->get('mail.from.address', '') ?? '');
        if ($from !== '' && \str_contains($from, '@')) {
            return \substr($from, (int) \strpos($from, '@') + 1);
        }

        $url = (string) ($this->config?->get('app.url', '') ?? '');
        $host = $url !== '' ? \parse_url($url, \PHP_URL_HOST) : null;

        return \is_string($host) && $host !== '' ? $host : null;
    }
}
