# DOC-068 — موتور Backup | پشتیبان‌گیری

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-068 |
| Engine ID | `tavaan.engine.backup` |
| Namespace | `Tavaan\Engine\Backup` |
| اولویت | **P2** — فاز M5 |
| وابستگی | `database`, `filesystem`, `crypt` |
| Facade | `Backup` |
| افزونه | `zip`(اختیاری + fallback خالص) |
| LOC | ۷۰۰ |

## ۱. هدف
پشتیبان‌گیری رمزنگاری‌شده از DB و فایل‌ها، زمان‌بندی‌شده، با Retention، Verify و Restore.

## ۲. API
```php
Backup::run();                              // full backup
Backup::database();                         // فقط DB
Backup::files(['storage/app']);             // فقط فایل‌ها
Backup::restore($backupId);
Backup::list();
Backup::verify($backupId);                  // بررسی یکپارچگی
Backup::prune(keepDays: 30);
```

## ۳. جریان Backup
```
tvn backup:run
  │
  ├─ 1. DB Dump (موتور database)
  │      MySQL: mysqldump یا SELECT → SQL/CSV
  │      🔒 بدون exec خارجی (fallback خالص)
  ├─ 2. فایل‌ها (storage/app, config, .env)
  ├─ 3. فشرده‌سازی (ZIP با ZipWriter از compat)
  ├─ 4. رمزنگاری (AES-256-GCM با کلید backup)
  ├─ 5. Hash (SHA-256) برای verify
  ├─ 6. ذخیره در storage/backups/ یا remote (S3/FTP)
  └─ 7. Manifest ثبت (id, size, hash, created_at)
```

## ۴. DB Dump (بدون mysqldump)
```php
final class DatabaseDumper
{
    public function dump(ConnectionInterface $conn): string
    {
        $sql = [];
        foreach ($conn->getTables() as $table) {
            $sql[] = $conn->getCreateTable($table) . ';';   // Schema

            // داده به‌صورت chunked (حافظهٔ ثابت)
            $conn->table($table)->orderBy('id')->chunk(1000, function ($rows) use (&$sql, $table): void {
                foreach ($rows as $row) {
                    $sql[] = $this->insertStatement($table, $row);
                }
            });
        }
        return \implode("\n", $sql);
    }

    private function insertStatement(string $table, array $row): string
    {
        $cols = \array_map(fn($c) => "`{$c}`", \array_keys($row));
        $vals = \array_map(fn($v) => $this->escape($v), \array_values($row));
        return "INSERT INTO `{$table}` (" . \implode(',', $cols) . ") VALUES (" . \implode(',', $vals) . ");";
    }
}
```
💡 اگر `mysqldump` در دسترس باشد (با allowlist) → استفاده سریع‌تر؛ وگرنه dump خالص.

## ۵. رمزنگاری Backup
```php
final class BackupEncrypter
{
    public function encrypt(string $content): string
    {
        // 🔒 کلید جدا از APP_KEY (اگر APP_KEY لو رفت، backup امن بماند)
        return $this->encrypter->encrypt($content, aad: 'tavaan-backup');
    }
    public function decrypt(string $cipher): string
    {
        return $this->encrypter->decrypt($cipher, aad: 'tavaan-backup');
    }
}
```

## ۶. Manifest
```json
{
  "id": "bkp_01J...",
  "version": "1.0.0",
  "type": "full",
  "created_at": "2026-10-15T03:00:00Z",
  "size": 52428800,
  "hash": "sha256:...",
  "contents": {"database": true, "files": ["storage/app","config"]},
  "encrypted": true,
  "tavaan_version": "1.0.0",
  "php_version": "8.2.0"
}
```

## ۷. زمان‌بندی و Retention
```php
// در schedule
Schedule::command('backup:run')->dailyAt('03:00');
Schedule::command('backup:prune')->weekly();

// config/backup.php
return [
    'disk' => 'backups',                  // یا s3
    'schedule' => ['frequency'=>'daily','time'=>'03:00'],
    'retention' => ['daily'=>7, 'weekly'=>4, 'monthly'=>12],
    'encrypt' => true,
    'key' => env_string('TVN_BACKUP_KEY'),  // 🔒 جدا از APP_KEY
    'include_files' => ['storage/app','config','.env'],
    'exclude' => ['storage/cache','storage/logs','storage/temp'],
    'verify_after' => true,
    'notify_on_failure' => true,
];
```

## ۸. Restore
```php
public function restore(string $backupId): void
{
    $backup = $this->find($backupId);
    $this->verify($backup);                    // hash + decrypt test

    $this->maintenance->enable();
    try {
        $content = $this->encrypter->decrypt($backup->cipher());
        $this->restoreDatabase($content);      // با تأیید + drop موجود
        $this->restoreFiles($content);
        $this->cache->clear();
    } finally {
        $this->maintenance->disable();
    }
}
```
🔒 Restore نیازمند `--force` و تأیید (خطر overwrite).

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| نشت backup | رمزنگاری با کلید جدا |
| دستکاری backup | hash + verify |
| Restore مخرب | تأیید + maintenance mode |
| نشت `.env` در backup | backup رمزنگاری‌شده + دسترسی محدود |
| فضای دیسک | retention + prune |

## ۱۰. تست
| T-01 | backup full → manifest + hash |
| T-02 | DB dump (chunked، حافظه ثابت) |
| T-03 | رمزنگاری/رمزگشایی roundtrip |
| T-04 | verify: دستکاری → fail |
| T-05 | restore |
| T-06 | retention/prune |
| T-07 | ZIP خالص (بدون ext-zip) |

## ۱۱. معیار پذیرش 🧪
- AC-BKP-01..07. AC-BKP-08: backup روی ۱GB داده با حافظهٔ ثابت (chunked).

## ۱۲. مراجع
| منبع | تفاوت |
|---|---|
| spatie/laravel-backup | backup/restore | خودنوشته، بدون mysqldump اجباری، رمزنگاری جدا |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.backup.v1
engine:
  id: tavaan.engine.backup
  namespace: Tavaan\Engine\Backup
  path: engines/backup
  priority: P2
  phase: M5
  depends_on: [database, filesystem, crypt]
  extensions: {required: [], optional: [{name: zip, fallback: ZipWriterPure}]}
  facade: {name: Backup, accessor: backup}
  config_file: config/backup.php
  public_api:
    - class: BackupManager
      methods: [run, database, files, restore, list, verify, prune]
  loc_estimate: 700
```
