# DOC-065 — موتور Mail | پست الکترونیک

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-065 |
| Engine ID | `tavaan.engine.mail` |
| Namespace | `Tavaan\Engine\Mail` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `view`, `queue`, `event`, `config` |
| Facade | `Mail` → `mailer` |
| افزونه | هیچ (SMTP خالص با sockets/stream) |
| LOC | ۱۴۰۰ |

## ۱. هدف
ارسال ایمیل با **SMTP client خودنوشته** (بدون SwiftMailer/Symfony Mailer)، MIME builder،
Template با View Engine، Queue، Attachment و Inline Image.

## ۲. Transportها
| Transport | توضیح |
|---|---|
| `smtp` | SMTP خالص با STARTTLS/SSL (بدون ext) |
| `sendmail` | `/usr/sbin/sendmail` |
| `mail` | `mail()` تابع PHP (fallback) |
| `log` | نوشتن در لاگ (dev) |
| `array` | در حافظه (تست) |
| `ses` | AWS SES HTTP API (اختیاری) |

## ۳. API
```php
Mail::to('user@example.com')->send(new WelcomeMail($user));
Mail::to($user)->cc($admin)->bcc(...)->send(...);
Mail::send('emails.welcome', ['user'=>$user], fn (Message $m) => $m->to($email)->subject('خوش آمدید'));
Mail::queue(new WelcomeMail($user));           // async
Mail::later(now()->addMinutes(5), new ReminderMail($user));
Mail::raw('متن ساده', fn ($m) => $m->to($e));
Mail::assertSent(WelcomeMail::class);           // تست
Mail::fake();
```

## ۴. Mailable
```php
final class WelcomeMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly User $user) {}

    public function build(): self
    {
        return $this->subject('خوش آمدید به ' . config('app.name'))
            ->view('emails.welcome')
            ->with(['user' => $this->user])
            ->attach('/path/invoice.pdf', as: 'invoice.pdf', mime: 'application/pdf')
            ->replyTo('support@example.com');
    }
}
```

## ۵. SMTP Client (خودنوشته)
```php
final class SmtpTransport implements TransportInterface
{
    public function send(Message $message): SentMessage
    {
        $socket = $this->connect();
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . $this->hostname, 250);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                $this->enableEncryption($socket);
                $this->command($socket, 'EHLO ' . $this->hostname, 250);
            }
            if ($this->auth) {
                $this->authenticate($socket);      // AUTH LOGIN / PLAIN
            }

            $this->command($socket, 'MAIL FROM:<' . $message->from() . '>', 250);
            foreach ($message->allRecipients() as $rcpt) {
                $this->command($socket, 'RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            $this->command($socket, 'DATA', 354);
            $this->write($socket, $message->toMimeString() . "\r\n.");
            $this->expect($socket, 250);
            $this->command($socket, 'QUIT', 221);
        } finally {
            $this->disconnect($socket);
        }
        return new SentMessage($message->id(), $message->recipients());
    }

    private function connect()
    {
        $host = $this->encryption === 'ssl' ? 'ssl://' . $this->host : $this->host;
        $socket = @\stream_socket_client("{$host}:{$this->port}", $errno, $errstr, $this->timeout);
        if ($socket === false) {
            throw new MailTransportException(messageId: 'mail.smtp.connect_failed',
                context: ['host' => $this->host, 'error' => $errstr]);
        }
        \stream_set_timeout($socket, $this->timeout);
        return $socket;
    }

    private function authenticate($socket): void
    {
        $this->command($socket, 'AUTH LOGIN', 334);
        $this->command($socket, \base64_encode($this->username), 334);
        $this->command($socket, \base64_encode($this->password), 235);
    }
}
```
🔒 SMTP خالص با `stream_socket_client` (بدون ext-curl).
🔒 STARTTLS با `stream_socket_enable_crypto`.
🔒 Timeout اجباری برای همهٔ عملیات socket.

## ۶. MIME Builder (خودنوشته)
```php
final class MimeMessage
{
    public function toString(): string
    {
        $boundary = 'tavaan_' . \bin2hex(\random_bytes(16));
        $headers = $this->buildHeaders($boundary);
        $body = $this->buildBody($boundary);      // multipart/alternative (text + html) + attachments
        return $headers . "\r\n\r\n" . $body;
    }

    private function buildHeaders(string $boundary): string
    {
        $h = [];
        $h[] = 'From: ' . $this->formatAddress($this->from);
        $h[] = 'To: ' . \implode(', ', \array_map($this->formatAddress(...), $this->to));
        $h[] = 'Subject: ' . $this->encodeHeader($this->subject);
        $h[] = 'Date: ' . \date('r');
        $h[] = 'Message-ID: <' . $this->messageId . '>';
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        return \implode("\r\n", $h);
    }

    private function encodeHeader(string $value): string
    {
        // RFC 2047 برای هدرهای غیر-ASCII (فارسی)
        return \preg_match('/[^\x20-\x7E]/', $value)
            ? '=?UTF-8?B?' . \base64_encode($value) . '?='
            : $value;
    }
}
```

## ۷. پیکربندی
```php
// config/mail.php
return [
    'default' => env_string('TVN_MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp', 'host' => env_string('TVN_MAIL_HOST'),
            'port' => env_int('TVN_MAIL_PORT', 587), 'encryption' => env_string('TVN_MAIL_ENCRYPTION', 'tls'),
            'username' => env_string('TVN_MAIL_USERNAME'), 'password' => env_string('TVN_MAIL_PASSWORD'),
            'timeout' => 10, 'auth_mode' => null,
        ],
        'ses' => ['transport'=>'ses', 'key'=>env_string('AWS_KEY'), /*…*/],
        'sendmail' => ['transport'=>'sendmail', 'path'=>'/usr/sbin/sendmail -bs'],
        'log' => ['transport'=>'log', 'channel'=>'mail'],
        'array' => ['transport'=>'array'],
    ],
    'from' => ['address'=>env_string('TVN_MAIL_FROM_ADDRESS'), 'name'=>env_string('TVN_MAIL_FROM_NAME')],
    'markdown' => ['theme'=>'default'],
];
```

## ۸. امنیت
| تهدید | کنترل |
|---|---|
| Header Injection | رد `\r\n` در subject/address |
| Credential نشت | فقط ENV، mask در لاگ |
| Open Relay | فقط recipientهای مجاز |
| Plaintext در TLS | STARTTLS اجباری + verify |
| Attachment مخرب | MIME validation + size limit |

🔒 `encodeHeader` برای جلوگیری از injection در subject فارسی.
🔒 آدرس‌ها با `filter_var(FILTER_VALIDATE_EMAIL)` + رد newline.

## ۹. کارایی
| عملیات | هدف |
|---|---|
| ساخت MIME (بدون ارسال) | ≤ ۲ms |
| SMTP connect + send | ≤ ۲s (وابسته به سرور) |
| Queue mode | non-blocking (push ≤ ۱ms) |

## ۱۰. تست
| T-01 | MIME builder: multipart + attachment |
| T-02 | Header encoding فارسی (RFC 2047) |
| T-03 | SMTP با Mock server (array transport) |
| T-04 | `Mail::fake()` + assertSent |
| T-05 | Queue mail |
| T-06 | Header injection → رد |
| T-07 | STARTTLS handshake |

## ۱۱. معیار پذیرش 🧪
- AC-MAIL-01..07. AC-MAIL-08: ارسال ایمیل فارسی با موضوع/بدنهٔ UTF-8 درست.

## ۱۲. مراجع
| منبع | تفاوت |
|---|---|
| Symfony Mailer | SMTP/MIME | خودنوشته، بدون وابستگی |
| PHPMailer | SMTP client | بدون پکیج خارجی |
| Laravel Mail | Mailable/Queue | — |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.mail.v1
engine:
  id: tavaan.engine.mail
  namespace: Tavaan\Engine\Mail
  path: engines/mail
  priority: P1
  phase: M5
  depends_on: [view, queue, event, config]
  facade: {name: Mail, accessor: mailer}
  transports: [smtp, sendmail, mail, log, array, ses]
  config_file: config/mail.php
  public_api:
    - class: Mailer
      methods: [to, cc, bcc, send, queue, later, raw, fake, assertSent]
    - class: Mailable
      methods: [build, subject, view, with, attach, replyTo, send, queue]
  loc_estimate: 1400
```
