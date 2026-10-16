<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پست الکترونیک (Mail).
 *
 * @method static \Tavaan\Engine\Mail\PendingMail to(string|array|object $users)
 * @method static \Tavaan\Engine\Mail\SentMessage send(\Tavaan\Engine\Mail\Mailable|string $mailableOrView, array $data = [], ?callable $callback = null)
 * @method static \Tavaan\Engine\Mail\SentMessage raw(string $text, callable $callback)
 * @method static mixed queue(\Tavaan\Engine\Mail\Mailable $mailable)
 * @method static mixed later(\DateTimeInterface|int $delay, \Tavaan\Engine\Mail\Mailable $mailable)
 * @method static \Tavaan\Engine\Mail\Mailer mailer(?string $name = null)
 * @method static \Tavaan\Core\Contracts\Mail\TransportInterface transport(?string $name = null)
 * @method static \Tavaan\Engine\Mail\Mailer fake()
 * @method static bool isFaking()
 * @method static void assertSent(string|callable $mailableOrCallback, ?callable $callback = null)
 * @method static void assertNotSent(string $mailableClass)
 * @method static void assertSentCount(int $count)
 * @method static list<array{mailable: ?object, message: \Tavaan\Engine\Mail\Message, sent: \Tavaan\Engine\Mail\SentMessage}> sent()
 * @mixin \Tavaan\Engine\Mail\Mailer
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class Mail extends Facade
{
    protected static function accessor(): string
    {
        return 'mailer';
    }
}
