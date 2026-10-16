<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Tests\Feature;

require_once __DIR__ . '/../Fixtures/WelcomeMail.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Mail\Attachment;
use Tavaan\Engine\Mail\Mailable;
use Tavaan\Engine\Mail\Mailer;
use Tavaan\Engine\Mail\MailException;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\MimeBuilder;
use Tavaan\Engine\Mail\Tests\Fixtures\WelcomeMail;
use Tavaan\Engine\Mail\Transports\ArrayTransport;
use Tavaan\Engine\Mail\Transports\SmtpTransport;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Engine\Queue\Worker;
use Tavaan\Engine\Queue\WorkerOptions;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Mail — DOC-065 §10 (T-01..T-07) + §11 (AC-MAIL-08).
 */
final class MailTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Clock::setTestNow(null);

        $this->app = \tavaan_test_app();
        $config = $this->app->make('config');
        $config->set('mail.default', 'array');
        $config->set('mail.from.address', 'no-reply@example.test');
        $config->set('mail.from.name', 'تَوان');

        // برای تست ایمیل صف‌روند
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('cache.default', 'array');
        $config->set('queue.default', 'database');
        $config->set('queue.connections.database', ['driver' => 'database', 'table' => 'tvn_jobs', 'queue' => 'mail', 'retry_after' => 90]);
        $config->set('queue.failed', ['driver' => 'database', 'table' => 'tvn_failed_jobs', 'dlq' => '']);
        $config->set('queue.batching', ['driver' => 'database', 'table' => 'tvn_job_batches']);

        Model::flushTestState();

        $migration = require \dirname(__DIR__, 3) . '/queue/migrations/2026_05_01_000001_create_queue_tables.php';
        $migration->up();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    private function mailer(): Mailer
    {
        return $this->app->make(Mailer::class);
    }

    private function message(): Message
    {
        return (new Message('example.test'))
            ->from('sender@example.test', 'فرستنده')
            ->to('user@example.com', 'گیرنده')
            ->subject('سلام');
    }

    // ─── T-01: MIME builder (multipart + attachment) ─────────────────────

    public function testMimeMultipartWithAttachment(): void
    {
        $message = $this->message()
            ->text('متن ساده')
            ->html('<p>متن HTML</p>')
            ->attach(Attachment::fromData('PDF-DATA', 'invoice.pdf', 'application/pdf'));

        $mime = $message->toMimeString();

        // هدرها
        $this->assertStringContainsString('From: =?UTF-8?B?', $mime);          // نام فارسی encode شد
        $this->assertStringContainsString('sender@example.test', $mime);
        $this->assertStringContainsString('To: ', $mime);
        $this->assertStringContainsString('user@example.com', $mime);
        $this->assertStringContainsString('MIME-Version: 1.0', $mime);
        $this->assertStringContainsString('Message-ID: <', $mime);

        // ساختار multipart/mixed → multipart/alternative
        $this->assertStringContainsString('Content-Type: multipart/mixed; boundary="tavaan_', $mime);
        $this->assertStringContainsString('Content-Type: multipart/alternative; boundary="tavaan_', $mime);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mime);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $mime);

        // پیوست
        $this->assertStringContainsString('Content-Disposition: attachment; filename="invoice.pdf"', $mime);
        $this->assertStringContainsString('Content-Type: application/pdf', $mime);
        $this->assertStringContainsString(\chunk_split(\base64_encode('PDF-DATA'), 76, "\r\n"), $mime);

        // بدنه‌های base64 قابل بازخوانی
        $this->assertStringContainsString(\rtrim(\chunk_split(\base64_encode('متن ساده'), 76, "\r\n")), $mime);
    }

    public function testMimeTextOnlyAndHtmlOnly(): void
    {
        $textOnly = $this->message()->text('فقط متن')->toMimeString();
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $textOnly);
        $this->assertStringNotContainsString('multipart', $textOnly);

        $htmlOnly = $this->message()->html('<b>bold</b>')->toMimeString();
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $htmlOnly);
        $this->assertStringNotContainsString('multipart', $htmlOnly);
    }

    public function testMimeInlineImageUsesRelated(): void
    {
        $message = $this->message()
            ->html('<img src="cid:logo1">')
            ->embed('PNGDATA', 'logo.png', 'logo1', 'image/png');

        $mime = $message->toMimeString();

        $this->assertStringContainsString('Content-Type: multipart/related; boundary="tavaan_', $mime);
        $this->assertStringContainsString('Content-ID: <logo1>', $mime);
        $this->assertStringContainsString('Content-Disposition: inline; filename="logo.png"', $mime);
    }

    public function testBccIsNotLeakedInHeaders(): void
    {
        $message = $this->message()->text('x')->bcc('secret@example.com');
        $mime = $message->toMimeString();

        // 🔒 Bcc فقط در envelope است — نه در هدرهای MIME
        $this->assertStringNotContainsString('secret@example.com', $mime);
        $this->assertContains('secret@example.com', $message->allRecipients());
    }

    // ─── T-02: RFC 2047 (هدر فارسی) ──────────────────────────────────────

    public function testPersianHeaderEncodingRfc2047(): void
    {
        $subject = 'خوش آمدید به تَوان — نسخهٔ ۱٫۰';
        $encoded = MimeBuilder::encodeHeader($subject);

        $this->assertStringContainsString('=?UTF-8?B?', $encoded);

        // هر encoded-word ≤ 75 کاراکتر
        foreach (\explode(' ', $encoded) as $word) {
            $this->assertLessThan(76, \strlen($word));
        }

        // roundtrip با decoder مطابق RFC 2047 §6.2:
        // فاصلهٔ بین دو encoded-word مجاور، padding انتقال است و حذف می‌شود
        $compact = $encoded;
        while (\str_contains($compact, '?= =?')) {
            $compact = \str_replace('?= =?', '?==?', $compact);
        }
        $decoded = \preg_replace_callback('/=\?UTF-8\?B\?(.+?)\?=/', static fn (array $m): string => (string) \base64_decode($m[1]), $compact);
        $this->assertSame($subject, (string) $decoded);

        // ASCII دست‌نخورده
        $this->assertSame('Hello World', MimeBuilder::encodeHeader('Hello World'));
    }

    // ─── T-03: SMTP با سرور scripted (socket pair) ───────────────────────

    public function testSmtpDialogueWithScriptedServer(): void
    {
        $pair = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);
        $this->assertTrue(\is_array($pair));
        [$client, $server] = $pair;

        // سناریوی کامل سرور (پیش‌buffer شده)
        $this->serverScript($server, withAuth: true);

        $transport = new SmtpTransport([
            'host' => 'mail.test',
            'port' => 2525,
            'encryption' => '',
            'username' => 'smtp-user',
            'password' => 'smtp-pass',
            'timeout' => 5,
        ], static fn (): mixed => $client);

        $message = $this->message()->text('بدنهٔ آزمایش');
        $sent = $transport->send($message);

        $this->assertSame($message->id(), $sent->id());
        $this->assertSame(['user@example.com'], $sent->recipients());

        // دستورات ارسال‌شده از دید سرور
        \stream_set_blocking($server, false);
        $commands = (string) \stream_get_contents($server);

        $this->assertStringContainsString('EHLO tavaan.local', $commands);
        $this->assertStringContainsString('AUTH LOGIN', $commands);
        $this->assertStringContainsString(\base64_encode('smtp-user'), $commands);
        $this->assertStringContainsString(\base64_encode('smtp-pass'), $commands);
        $this->assertStringContainsString('MAIL FROM:<sender@example.test>', $commands);
        $this->assertStringContainsString('RCPT TO:<user@example.com>', $commands);
        $this->assertStringContainsString('DATA', $commands);
        $this->assertStringContainsString('Subject:', $commands);   // MIME در DATA
        $this->assertStringContainsString("\r\n.\r\n", $commands);  // پایان DATA
        $this->assertStringContainsString('QUIT', $commands);

        // 🔒 credential در هیچ خطای احتمالی نشت نکرد (موفقیت = بدون استثنا)
        \fclose($server);
    }

    // ─── T-07: STARTTLS handshake ────────────────────────────────────────

    public function testStartTlsHandshake(): void
    {
        $pair = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);
        [$client, $server] = $pair;

        $this->serverScript($server, withAuth: false, withStartTls: true);

        $cryptoCalled = false;
        $transport = new SmtpTransport([
            'host' => 'mail.test',
            'port' => 587,
            'encryption' => 'tls',
            'timeout' => 5,
        ], static fn (): mixed => $client, static function () use (&$cryptoCalled): bool {
            $cryptoCalled = true;

            return true;   // stub برای stream_socket_enable_crypto
        });

        $transport->send($this->message()->text('tls body'));

        \stream_set_blocking($server, false);
        $commands = (string) \stream_get_contents($server);

        $this->assertTrue($cryptoCalled);
        $this->assertStringContainsString('STARTTLS', $commands);
        // EHLO دوبار (پیش و پس از STARTTLS)
        $this->assertSame(2, \substr_count($commands, 'EHLO'));
        \fclose($server);
    }

    public function testStartTlsFailureThrows(): void
    {
        $pair = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0);
        [$client, $server] = $pair;

        \fwrite($server, "220 greeting\r\n");
        \fwrite($server, "250 OK\r\n");     // EHLO
        \fwrite($server, "220 Go\r\n");     // STARTTLS

        $transport = new SmtpTransport([
            'host' => 'mail.test', 'port' => 587, 'encryption' => 'tls', 'timeout' => 5,
        ], static fn (): mixed => $client, static fn (): bool => false);   // crypto شکست می‌خورد

        try {
            $transport->send($this->message()->text('x'));
            $this->fail('شکست STARTTLS باید استثنا دهد');
        } catch (MailException $e) {
            $this->assertSame('mail.smtp_tls_failed', $e->messageId);
        }
        \fclose($server);
    }

    // ─── T-04: fake + assertSent ─────────────────────────────────────────

    public function testFakeAndAssertSent(): void
    {
        $mailer = $this->mailer();
        $mailer->fake();

        $mailer->to('ali@example.com')->send(new WelcomeMail('ali@example.com', 'علی'));

        $mailer->assertSent(WelcomeMail::class);
        $mailer->assertSent(WelcomeMail::class, function (Mailable $mailable, Message $message): bool {
            return $message->getSubject() === 'خوش آمدید به تَوان'
                && \in_array('ali@example.com', $message->allRecipients(), true);
        });
        $mailer->assertSentCount(1);
        $mailer->assertNotSent('App\\Mail\\OtherMail');

        // global from اعمال شد
        $entry = $mailer->sent()[0];
        $this->assertSame('no-reply@example.test', $entry['message']->getFrom()['address'] ?? '');
    }

    public function testAssertSentFailureThrows(): void
    {
        $mailer = $this->mailer();
        $mailer->fake();

        try {
            $mailer->assertSent(WelcomeMail::class);
            $this->fail('assertSent باید شکست بخورد');
        } catch (MailException $e) {
            $this->assertSame('mail.assertion_failed', $e->messageId);
        }
    }

    // ─── T-05: queue mail ────────────────────────────────────────────────

    public function testQueuedMailIsSentByWorker(): void
    {
        $mailer = $this->mailer();

        $mailer->queue(new WelcomeMail('queued@example.com', 'صف'));

        // هنوز ارسال نشده — فقط در صف است
        $this->assertCount(0, $mailer->sent());
        /** @var QueueManager $queue */
        $queue = $this->app->make(QueueManager::class);
        $this->assertSame(1, $queue->size('mail'));

        // worker job را اجرا می‌کند
        /** @var Worker $worker */
        $worker = $this->app->make(Worker::class);
        $this->assertTrue($worker->runNextJob(WorkerOptions::fromArray(['sleep' => 0, 'queue' => 'mail'])));

        $this->assertCount(1, $mailer->sent());
        $mailer->assertSent(WelcomeMail::class, function (Mailable $m, Message $message): bool {
            return \in_array('queued@example.com', $message->allRecipients(), true);
        });

        // transport آرایه هم پیام را دید
        $transport = $mailer->transport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
    }

    // ─── T-06: Header injection ──────────────────────────────────────────

    public function testHeaderInjectionRejected(): void
    {
        // subject با CRLF
        try {
            $this->message()->subject("بد\r\nBcc: evil@example.com");
            $this->fail('subject با CRLF باید رد شود');
        } catch (MailException $e) {
            $this->assertSame('mail.header_injection', $e->messageId);
        }

        // آدرس با newline
        try {
            (new Message())->to("user@example.com\nCc: evil@example.com");
            $this->fail('آدرس با newline باید رد شود');
        } catch (MailException $e) {
            $this->assertContains($e->messageId, ['mail.header_injection', 'mail.invalid_address']);
        }

        // آدرس نامعتبر
        try {
            (new Message())->to('not-an-email');
            $this->fail('آدرس نامعتبر باید رد شود');
        } catch (MailException $e) {
            $this->assertSame('mail.invalid_address', $e->messageId);
        }

        // هدر سفارشی با CRLF
        try {
            $this->message()->withHeader('X-Evil', "a\r\nInjected: yes");
            $this->fail('هدر سفارشی با CRLF باید رد شود');
        } catch (MailException $e) {
            $this->assertSame('mail.header_injection', $e->messageId);
        }
    }

    // ─── raw / mailer / خطاها ────────────────────────────────────────────

    public function testRawMail(): void
    {
        $mailer = $this->mailer();
        $sent = $mailer->raw('متن خام', fn (Message $m) => $m->to('raw@example.com')->subject('raw'));

        $this->assertSame(['raw@example.com'], $sent->recipients());
        $this->assertSame('array', $sent->transport());
    }

    public function testNoRecipientsRejected(): void
    {
        try {
            $this->mailer()->raw('x', fn (Message $m) => $m->subject('بدون گیرنده'));
            $this->fail('بدون گیرنده باید رد شود');
        } catch (MailException $e) {
            $this->assertSame('mail.no_recipients', $e->messageId);
        }
    }

    public function testMailerSelectionAndUnknownTransport(): void
    {
        $this->assertInstanceOf(ArrayTransport::class, $this->mailer()->transport('array'));
        $this->assertInstanceOf(SmtpTransport::class, $this->mailer()->transport('smtp'));
        $this->assertInstanceOf(\Tavaan\Engine\Mail\Transports\LogTransport::class, $this->mailer()->transport('log'));

        try {
            $this->mailer()->transport('nope');
            $this->fail('transport ناشناخته باید رد شود');
        } catch (MailException $e) {
            $this->assertSame('mail.unknown_transport', $e->messageId);
        }
    }

    public function testSendViewStringWithCallback(): void
    {
        // بدون موتور view فعال در مسیر تست — از html مستقیم روی Message استفاده می‌کنیم
        $mailer = $this->mailer();
        $mailer->fake();

        // Mailable با html خام (بدون template)
        $mailable = new class () extends Mailable {
            public function build(): self
            {
                return $this->subject('inline')->html('<p>inline html</p>')->to('inline@example.com');
            }
        };
        $mailer->send($mailable);

        $mailer->assertSentCount(1);
        $entry = $mailer->sent()[0];
        $this->assertSame('<p>inline html</p>', $entry['message']->getHtml());
    }

    // ─── AC-MAIL-08: ایمیل فارسی UTF-8 سرتاسری ───────────────────────────

    public function testPersianEmailRoundtrip(): void
    {
        $message = (new Message('example.test'))
            ->from('no-reply@example.test', 'تَوان')
            ->to('user@example.com', 'علی رضایی')
            ->subject('سفارش شما ثبت شد — کد ۱۲۳۴')
            ->text('سلام علی، سفارش شما با موفقیت ثبت شد.')
            ->html('<p>سلام <b>علی</b>، سفارش شما ثبت شد.</p>');

        $mime = $message->toMimeString();

        // subject فارسی به‌شکل RFC 2047
        $this->assertMatchesRegex('/Subject: =\?UTF-8\?B\?.+\?=/', $mime);

        // بدنهٔ base64 → decode → متن فارسی سالم
        $decoded = $this->decodeFirstBase64Part($mime, 'text/plain');
        $this->assertStringContainsString('سلام علی، سفارش شما با موفقیت ثبت شد.', $decoded);

        $decodedHtml = $this->decodeFirstBase64Part($mime, 'text/html');
        $this->assertStringContainsString('سفارش شما ثبت شد', $decodedHtml);
    }

    // ─── ابزار ───────────────────────────────────────────────────────────

    /** @param resource $server */
    private function serverScript($server, bool $withAuth, bool $withStartTls = false): void
    {
        \fwrite($server, "220 mail.test ESMTP ready\r\n");
        \fwrite($server, "250-mail.test\r\n250-AUTH LOGIN PLAIN\r\n250 OK\r\n");   // EHLO (چندخطی)
        if ($withStartTls) {
            \fwrite($server, "220 Ready for TLS\r\n");                              // STARTTLS
            \fwrite($server, "250-mail.test\r\n250 OK\r\n");                        // EHLO دوم
        }
        if ($withAuth) {
            \fwrite($server, "334 VXNlcm5hbWU6\r\n");                               // AUTH LOGIN
            \fwrite($server, "334 UGFzc3dvcmQ6\r\n");                               // username
            \fwrite($server, "235 Authentication successful\r\n");                  // password
        }
        \fwrite($server, "250 OK sender\r\n");                                      // MAIL FROM
        \fwrite($server, "250 OK recipient\r\n");                                   // RCPT TO
        \fwrite($server, "354 End data with <CR><LF>.<CR><LF>\r\n");                // DATA
        \fwrite($server, "250 Accepted id=abc123\r\n");                             // پایان DATA
        \fwrite($server, "221 Bye\r\n");                                            // QUIT
    }

    /** اولین part با Content-Type مشخص را از MIME بیرون کشیده و base64 آن را decode می‌کند. */
    private function decodeFirstBase64Part(string $mime, string $contentType): string
    {
        $normalized = \str_replace("\r\n", "\n", $mime);
        $pattern = '#Content-Type: ' . \preg_quote($contentType, '#') . '[^\n]*\n(?:[^\n]*\n)*?Content-Transfer-Encoding: base64\n\n([A-Za-z0-9+/\n=]+)#';

        if (\preg_match($pattern, $normalized, $m) !== 1) {
            return '';
        }

        return (string) \base64_decode(\str_replace("\n", '', $m[1]), true);
    }
}
