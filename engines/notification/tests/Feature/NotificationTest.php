<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Tests\Feature;

require_once __DIR__ . '/../Fixtures/TestNotifiable.php';
require_once __DIR__ . '/../Fixtures/OrderShippedNotification.php';
require_once __DIR__ . '/../Fixtures/MockSmsGateway.php';
require_once __DIR__ . '/../Fixtures/QueuedTestNotification.php';

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\HttpClient\Factory as HttpFactory;
use Tavaan\Engine\HttpClient\Request as HttpRequest;
use Tavaan\Engine\Mail\Mailer;
use Tavaan\Engine\Notification\AnonymousNotifiable;
use Tavaan\Engine\Notification\Channels\BroadcastChannel;
use Tavaan\Engine\Notification\Messages\MailMessage;
use Tavaan\Engine\Notification\Messages\TelegramMessage;
use Tavaan\Engine\Notification\Messages\WebhookMessage;
use Tavaan\Engine\Notification\Notification;
use Tavaan\Engine\Notification\NotificationSender;
use Tavaan\Engine\Notification\Sms\LogSmsGateway;
use Tavaan\Engine\Notification\Tests\Fixtures\MockSmsGateway;
use Tavaan\Engine\Notification\Tests\Fixtures\OrderShippedNotification;
use Tavaan\Engine\Notification\Tests\Fixtures\QueuedTestNotification;
use Tavaan\Engine\Notification\Tests\Fixtures\TestNotifiable;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Engine\Queue\Worker;
use Tavaan\Engine\Queue\WorkerOptions;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Notification — DOC-066 §8 (T-01..T-07).
 */
final class NotificationTest extends TestCase
{
    private Application $app;

    private MockSmsGateway $sms;

    protected function setUp(): void
    {
        Clock::setTestNow(null);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');

        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');

        $config->set('mail.default', 'array');
        $config->set('mail.from.address', 'no-reply@example.test');

        $config->set('queue.default', 'database');
        $config->set('queue.connections.database', ['driver' => 'database', 'table' => 'tvn_jobs', 'queue' => 'default', 'retry_after' => 90]);
        $config->set('queue.failed', ['driver' => 'database', 'table' => 'tvn_failed_jobs', 'dlq' => '']);
        $config->set('queue.batching', ['driver' => 'database', 'table' => 'tvn_job_batches']);

        $config->set('notification.sms.driver', 'log');
        $config->set('notification.telegram.token', 'T0KEN');
        $config->set('notification.rate_limit.per_user_per_hour', 20);

        Model::flushTestState();

        (require \dirname(__DIR__, 3) . '/notification/migrations/2026_05_02_000001_create_notifications_table.php')->up();
        (require \dirname(__DIR__, 3) . '/queue/migrations/2026_05_01_000001_create_queue_tables.php')->up();

        // درگاه ساختگی پیامک برای همهٔ تست‌ها
        $this->sms = new MockSmsGateway();
        $this->sender()->useSmsGateway($this->sms);
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    private function sender(): NotificationSender
    {
        return $this->app->make(NotificationSender::class);
    }

    private function user(int $id = 1): TestNotifiable
    {
        return new TestNotifiable($id);
    }

    // ─── T-01: ارسال به چند کانال ────────────────────────────────────────

    public function testSendToMultipleChannels(): void
    {
        $user = $this->user();
        $delivered = $this->sender()->sendNow($user, new OrderShippedNotification(101, 'TRK-9'));

        $this->assertSame(['mail', 'sms', 'database'], $delivered);

        // ایمیل
        $mailer = $this->app->make(Mailer::class);
        $this->assertCount(1, $mailer->sent());
        $mailMessage = $mailer->sent()[0]['message'];
        $this->assertSame('سفارش ارسال شد', $mailMessage->getSubject());
        $this->assertContains('user@example.com', $mailMessage->allRecipients());

        // پیامک
        $this->assertCount(1, $this->sms->sent);
        $this->assertStringContainsString('TRK-9', $this->sms->sent[0]->content);
        $this->assertSame(['09121234567'], $this->sms->sent[0]->recipients());

        // database
        $rows = $this->sender()->database()->allFor($user);
        $this->assertCount(1, $rows);
        $this->assertSame(OrderShippedNotification::class, $rows[0]['type']);
        $this->assertSame(101, $rows[0]['data']['order_id']);
    }

    // ─── T-02: انتخاب کانال با via() ─────────────────────────────────────

    public function testViaSelectsChannels(): void
    {
        $databaseOnly = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['database'];
            }

            /** @return array<string, mixed> */
            public function toDatabase(Notifiable $notifiable): array
            {
                return ['only' => 'db'];
            }
        };

        $delivered = $this->sender()->sendNow($this->user(), $databaseOnly);

        $this->assertSame(['database'], $delivered);
        $this->assertCount(0, $this->sms->sent);
        $this->assertCount(0, $this->app->make(Mailer::class)->sent());
    }

    // ─── T-03: database channel + unread ─────────────────────────────────

    public function testDatabaseChannelUnreadFlow(): void
    {
        $user = $this->user(7);
        $db = $this->sender()->database();

        $this->sender()->sendNow($user, new OrderShippedNotification(1));
        $this->sender()->sendNow($user, new OrderShippedNotification(2));

        $this->assertSame(2, $db->unreadCount($user));
        $this->assertCount(2, $db->allFor($user, unreadOnly: true));

        // markAsRead یک مورد
        $id = $db->allFor($user)[0]['id'];
        $this->assertTrue($db->markAsRead((string) $id));
        $this->assertSame(1, $db->unreadCount($user));

        // markAllAsRead
        $this->assertSame(1, $db->markAllAsRead($user));
        $this->assertSame(0, $db->unreadCount($user));

        // کاربران دیگر اعلان‌های هم را نمی‌بینند
        $this->assertCount(0, $db->allFor($this->user(8)));

        // delete
        $this->assertSame(2, $db->deleteFor($user));
        $this->assertCount(0, $db->allFor($user));
    }

    // ─── T-04: آگاه‌سازی صف‌روند ─────────────────────────────────────────

    public function testQueuedNotificationIsSentByWorker(): void
    {
        $user = $this->user(3);

        $results = $this->sender()->send($user, new QueuedTestNotification('Q-77'));

        $this->assertSame('queued', $results[0]['status']);
        $this->assertCount(0, $this->sms->sent);    // هنوز ارسال نشده

        /** @var QueueManager $queue */
        $queue = $this->app->make(QueueManager::class);
        $this->assertSame(1, $queue->size('default'));

        /** @var Worker $worker */
        $worker = $this->app->make(Worker::class);
        $this->assertTrue($worker->runNextJob(WorkerOptions::fromArray(['sleep' => 0])));

        // اکنون ارسال شد
        $this->assertCount(1, $this->sms->sent);
        $this->assertStringContainsString('Q-77', $this->sms->sent[0]->content);
        $this->assertSame(['09121234567'], $this->sms->sent[0]->recipients());
    }

    public function testQueueDisabledSendsInline(): void
    {
        $this->app->make('config')->set('notification.queue', false);

        $results = $this->sender()->send($this->user(4), new QueuedTestNotification('Q-INLINE'));

        $this->assertSame('sent', $results[0]['status']);
        $this->assertCount(1, $this->sms->sent);
    }

    // ─── T-05: درگاه پیامک با Mock ───────────────────────────────────────

    public function testSmsGatewayFailureDoesNotStopOtherChannels(): void
    {
        $this->sms->shouldFail = true;

        $delivered = $this->sender()->sendNow($this->user(5), new OrderShippedNotification());

        // sms شکست خورد اما mail و database ارسال شدند
        $this->assertFalse(\in_array('sms', $delivered, true));
        $this->assertContains('mail', $delivered);
        $this->assertContains('database', $delivered);
    }

    public function testPhoneMasking(): void
    {
        $this->assertSame('0912***4567', LogSmsGateway::maskPhone('09121234567'));
        $this->assertSame('0912***4567', LogSmsGateway::maskPhone('+98 912-123-4567'));
        $this->assertSame('***', LogSmsGateway::maskPhone('12'));
    }

    // ─── T-06: Rate limit ────────────────────────────────────────────────

    public function testRateLimitBlocksAfterThreshold(): void
    {
        $this->app->make('config')->set('notification.rate_limit.per_user_per_hour', 2);
        $this->app->make('config')->set('notification.queue', false);

        $smsOnly = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['sms'];
            }

            public function toSms(Notifiable $notifiable): \Tavaan\Engine\Notification\Messages\SmsMessage
            {
                return \Tavaan\Engine\Notification\Messages\SmsMessage::create('ping');
            }
        };

        $user = $this->user(6);
        $sender = $this->sender();

        $this->assertSame(['sms'], $sender->sendNow($user, $smsOnly));   // ۱
        $this->assertSame(['sms'], $sender->sendNow($user, $smsOnly));   // ۲
        $this->assertSame([], $sender->sendNow($user, $smsOnly));        // ۳ → مسدود 🔒

        $this->assertCount(2, $this->sms->sent);

        // کاربر دیگر محدود نیست
        $this->assertSame(['sms'], $sender->sendNow($this->user(9), $smsOnly));
    }

    // ─── T-07: fake + assertSentTo ───────────────────────────────────────

    public function testFakeAndAssertSentTo(): void
    {
        $sender = $this->sender();
        $sender->fake();

        $user = $this->user(10);
        $sender->sendNow($user, new OrderShippedNotification(55));

        $sender->assertSentTo($user, OrderShippedNotification::class);
        $sender->assertSentTo($user, OrderShippedNotification::class, function (Notifiable $n, Notification $notification, array $channels): bool {
            return $channels === ['mail', 'sms', 'database']
                && $notification instanceof OrderShippedNotification
                && $notification->orderId === 55;
        });
        $sender->assertSentCount(1);

        // هیچ ارسال واقعی انجام نشد
        $this->assertCount(0, $this->sms->sent);

        $sender->assertNotSentTo($this->user(11), OrderShippedNotification::class);

        try {
            $sender->assertSentTo($this->user(12), OrderShippedNotification::class);
            $this->fail('assertSentTo باید شکست بخورد');
        } catch (\Tavaan\Engine\Notification\Exceptions\NotificationException $e) {
            $this->assertSame('notification.assertion_failed', $e->messageId);
        }
    }

    // ─── AnonymousNotifiable + Facade ────────────────────────────────────

    public function testAnonymousNotifiableSendsMail(): void
    {
        $mailer = $this->app->make(Mailer::class);

        $welcome = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(Notifiable $notifiable): MailMessage
            {
                return (new MailMessage())->subject('خوش آمدید')->line('سلام مهمان!');
            }
        };

        $anonymous = $this->sender()->route('mail', 'guest@example.test');
        $this->assertInstanceOf(AnonymousNotifiable::class, $anonymous);

        $delivered = $this->sender()->sendNow($anonymous, $welcome);

        $this->assertSame(['mail'], $delivered);
        $this->assertCount(1, $mailer->sent());
        $this->assertContains('guest@example.test', $mailer->sent()[0]['message']->allRecipients());
    }

    public function testFacadeNotifyOnAnonymous(): void
    {
        $mailer = $this->app->make(Mailer::class);

        $welcome = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(Notifiable $notifiable): MailMessage
            {
                return (new MailMessage())->subject('facade')->line('x');
            }
        };

        // مسیر Facade: Notification::route(...)->notify(...)
        \Tavaan\Core\Facade\Facades\Notification::route('mail', 'facade@example.test')->notify($welcome);

        $this->assertCount(1, $mailer->sent());
        $this->assertContains('facade@example.test', $mailer->sent()[0]['message']->allRecipients());
    }

    public function testUserNotifyTrait(): void
    {
        $mailer = $this->app->make(Mailer::class);

        // فقط کانال mail تا سریع بماند
        $mailOnly = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(Notifiable $notifiable): MailMessage
            {
                return (new MailMessage())->subject('trait')->line('y');
            }
        };

        $this->user(21)->notify($mailOnly);

        $this->assertCount(1, $mailer->sent());
        $this->assertContains('user@example.com', $mailer->sent()[0]['message']->allRecipients());
    }

    // ─── webhook / telegram / broadcast ──────────────────────────────────

    public function testWebhookChannel(): void
    {
        /** @var HttpFactory $http */
        $http = $this->app->make('http.client');
        $http->fake(['hook.test/*' => HttpFactory::response(['ok' => true])]);

        $webhookNotification = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['webhook'];
            }

            public function toWebhook(Notifiable $notifiable): WebhookMessage
            {
                return WebhookMessage::create('https://hook.test/events', ['event' => 'shipped']);
            }
        };

        $delivered = $this->sender()->sendNow($this->user(30), $webhookNotification);

        $this->assertSame(['webhook'], $delivered);
        $http->assertSent(function (HttpRequest $request): bool {
            return $request->method() === 'POST'
                && $request->urlIs('hook.test/events')
                && \str_contains((string) $request->body(), '"event":"shipped"')
                && \str_contains((string) $request->body(), 'notification_id');
        });
    }

    public function testTelegramChannel(): void
    {
        /** @var HttpFactory $http */
        $http = $this->app->make('http.client');
        $http->fake(['api.telegram.org/*' => HttpFactory::response(['ok' => true, 'result' => ['message_id' => 42]])]);

        $telegramNotification = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['telegram'];
            }

            public function toTelegram(Notifiable $notifiable): TelegramMessage
            {
                return TelegramMessage::create('سفارش ارسال شد 📦')->action('مشاهده', 'https://shop.test/o/1');
            }
        };

        $delivered = $this->sender()->sendNow($this->user(31), $telegramNotification);

        $this->assertSame(['telegram'], $delivered);
        $http->assertSent(function (HttpRequest $request): bool {
            $body = (string) $request->body();

            return \str_contains($request->url(), '/botT0KEN/sendMessage')
                && \str_contains($body, '"chat_id":"98765"')
                && \str_contains($body, 'reply_markup');
        });
    }

    public function testBroadcastChannelDispatchesEvent(): void
    {
        $heard = [];
        $this->app->make('events')->listen(\Tavaan\Engine\Notification\Channels\NotificationBroadcast::class, static function ($event) use (&$heard): void {
            $heard[] = $event->data;
        });

        $broadcastNotification = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['broadcast'];
            }

            /** @return array<string, mixed> */
            public function toBroadcast(Notifiable $notifiable): array
            {
                return ['type' => 'order.shipped', 'order_id' => 1];
            }
        };

        $delivered = $this->sender()->sendNow($this->user(32), $broadcastNotification);

        $this->assertSame(['broadcast'], $delivered);
        $this->assertCount(1, $heard);
        $this->assertSame('order.shipped', $heard[0]['type']);
    }

    // ─── کانال سفارشی با extend ──────────────────────────────────────────

    public function testCustomChannelViaExtend(): void
    {
        $custom = new class () implements \Tavaan\Engine\Notification\Channels\ChannelInterface {
            /** @var list<mixed> */
            public array $received = [];

            public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
            {
                $this->received[] = $message;

                return 'ok';
            }

            public function name(): string
            {
                return 'pigeon';
            }
        };

        $this->sender()->extend('pigeon', static fn () => $custom);

        $pigeonNotification = new class () extends Notification {
            /** @return list<string> */
            public function via(Notifiable $notifiable): array
            {
                return ['pigeon'];
            }

            public function toPigeon(Notifiable $notifiable): string
            {
                return 'coo-coo';
            }
        };

        // کانال سفارشی باید در فهرست channels مجاز config هم باشد
        $this->app->make('config')->set('notification.channels', ['pigeon']);

        $delivered = $this->sender()->sendNow($this->user(33), $pigeonNotification);

        $this->assertSame(['pigeon'], $delivered);
        $this->assertSame(['coo-coo'], $custom->received);
    }
}
