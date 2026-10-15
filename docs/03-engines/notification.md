# DOC-066 — موتور Notification | آگاه‌سازی چندکاناله

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-066 |
| Engine ID | `tavaan.engine.notification` |
| Namespace | `Tavaan\Engine\Notification` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `mail`, `queue`, `database`, `event` |
| Facade | `Notification` |
| LOC | ۹۰۰ |

## ۱. هدف
آگاه‌سازی یکپارچه از طریق چند کانال (Mail/SMS/Push/InApp/Webhook/Telegram/Bale/Eitaa)
با یک اعلان واحد، Queue، Template و Rate Limit.

## ۲. کانال‌ها
| کانال | پیاده‌سازی | توضیح |
|---|---|---|
| `mail` | موتور mail | ایمیل |
| `sms` | Gateway (Kavenegar/Melipayamak/…) | پیامک |
| `database` | جدول `notifications` | In-App (اعلان درون برنامه) |
| `broadcast` | موتور realtime | Realtime |
| `push` | FCM/APNs (Adapter) | موبایل |
| `webhook` | HTTP POST | سرویس خارجی |
| `telegram`/`bale`/`eitaa` | Bot API | پیام‌رسان ایرانی |

## ۳. API
```php
$user->notify(new OrderShipped($order));
Notification::send($users, new OrderShipped($order));
Notification::sendNow($user, new OrderShipped($order));     // sync
Notification::route('mail', $email)->notify(new Welcome()); // بدون مدل

final class OrderShipped extends Notification
{
    use Queueable;
    public function __construct(public Order $order) {}

    /** @return list<string> کانال‌ها */
    public function via(Notifiable $notifiable): array
    {
        return ['mail', 'sms', 'database'];
    }
    public function toMail(Notifiable $n): MailMessage
    {
        return (new MailMessage)->subject('سفارش ارسال شد')->line('...')->action('مشاهده', url('/orders/'.$this->order->id));
    }
    public function toSms(Notifiable $n): SmsMessage
    {
        return SmsMessage::create('سفارش شما ارسال شد. کد رهگیری: '.$this->order->tracking_code);
    }
    public function toDatabase(Notifiable $n): array
    {
        return ['order_id' => $this->order->id, 'message' => 'ارسال شد'];
    }
    public function toTelegram(Notifiable $n): TelegramMessage { /* … */ }
}
```

## ۴. In-App Notifications (database)
```sql
CREATE TABLE tvn_notifications (
  id CHAR(26) PRIMARY KEY,
  type VARCHAR(190) NOT NULL,
  notifiable_type VARCHAR(190) NOT NULL,
  notifiable_id VARCHAR(64) NOT NULL,
  data JSON NOT NULL,
  read_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL,
  KEY idx_notifiable (notifiable_type, notifiable_id, read_at)
) ENGINE=InnoDB;
```
```php
$user->notifications()->unread()->get();
$user->unreadNotifications()->count();
$notification->markAsRead();
$user->notifications()->delete();
```

## ۵. SMS Gateway Contract
```php
interface SmsGatewayInterface
{
    public function send(SmsMessage $message): SmsResult;
    public function status(string $messageId): DeliveryStatus;
    public function credit(): int;
}

// درایورها: Kavenegar, Melipayamak, Ghasedak, SMS.ir, FarazSMS
```

## ۶. پیکربندی
```php
// config/notification.php
return [
    'channels' => ['mail','sms','database','broadcast','telegram'],
    'database' => ['table'=>'tvn_notifications'],
    'queue' => env_bool('TVN_NOTIFICATION_QUEUE', true),
    'sms' => [
        'driver' => env_string('TVN_SMS_DRIVER', 'kavenegar'),
        'gateways' => [
            'kavenegar' => ['api_key'=>env_string('KAVENEGAR_KEY'), 'sender'=>env_string('KAVENEGAR_SENDER')],
            'melipayamak' => ['username'=>env_string('MP_USER'), 'password'=>env_string('MP_PASS')],
        ],
    ],
    'rate_limit' => ['per_user_per_hour' => 20],
    'log_sent' => true,
];
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| SMS Bombing | Rate limit per user/IP |
| نشت شماره در لاگ | Mask (`0912***4567`) |
| Webhook SSRF | SSRF guard (موتور security) |
| Credential نشت | ENV + mask |

## ۸. تست
| T-01 | send به چند کانال |
| T-02 | `via()` انتخاب کانال |
| T-03 | database channel + unread |
| T-04 | Queue notification |
| T-05 | SMS gateway با Mock |
| T-06 | Rate limit |
| T-07 | `Notification::fake()` + assertSentTo |

## ۹. معیار پذیرش 🧪
- AC-NOTIF-01..07.

## ۱۰. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.notification.v1
engine:
  id: tavaan.engine.notification
  namespace: Tavaan\Engine\Notification
  path: engines/notification
  priority: P1
  phase: M5
  depends_on: [mail, queue, database, event]
  facade: {name: Notification, accessor: notification}
  tables: [tvn_notifications]
  channels: [mail, sms, database, broadcast, push, webhook, telegram, bale, eitaa]
  config_file: config/notification.php
  public_api:
    - class: NotificationSender
      methods: [send, sendNow, route]
    - class: Notification
      methods: [via, toMail, toSms, toDatabase, toTelegram]
  loc_estimate: 900
```
