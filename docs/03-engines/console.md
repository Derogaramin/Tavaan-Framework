# DOC-076 — موتور Console | چارچوب CLI

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-076 |
| Engine ID | `tavaan.engine.console` |
| Namespace | `Tavaan\Engine\Console` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `container`, `event`, `config` |
| Facade | ندارد (Entry: `bin/tvn`) |
| LOC | ۱۶۰۰ |

## ۱. هدف
چارچوب CLI کامل برای `tvn` با Argument/Option، خروجی رنگی، Table/Progress/Ask،
Command Discovery، Scheduling Hook و Repl.

## ۲. آناتومی یک Command
```php
<?php
declare(strict_types=1);

namespace Tavaan\Engine\Cache\Console;

use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Console\Attributes\Argument;
use Tavaan\Engine\Console\Attributes\Option;

#[AsCommand(name: 'cache:clear', description: 'پاک‌کردن حافظهٔ نهان')]
final class CacheClearCommand extends Command
{
    protected string $signature = 'cache:clear
        {store? : نام store (پیش‌فرض: default)}
        {--tags= : فقط تگ‌های مشخص}
        {--force : بدون تأیید}';

    public function handle(): int
    {
        $store = $this->argument('store') ?? Cache::getDefaultDriver();

        if (!$this->option('force') && !$this->confirm("پاک‌کردن کش store={$store}؟")) {
            $this->warn('لغو شد.');
            return self::SUCCESS;
        }

        $tags = $this->option('tags');
        if ($tags) {
            Cache::store($store)->tags(\explode(',', $tags))->flush();
        } else {
            Cache::store($store)->flush();
        }

        $this->info("✓ کش '{$store}' پاک شد.");
        return self::SUCCESS;
    }
}
```

## ۳. API خروجی
```php
$this->info('سبز'); $this->error('قرمز'); $this->warn('زرد');
$this->line('ساده'); $this->comment('خاکستری'); $this->newLine();
$this->table(['ستون۱','ستون۲'], $rows);
$this->progressStart($total); $this->progressAdvance(); $this->progressFinish();
$this->withProgressBar($items, fn($item) => /*…*/);
$this->ask('نام؟'); $this->secret('رمز؟'); $this->confirm('مطمئنی؟');
$this->choice('انتخاب کنید', ['a','b','c']);
$this->anticipate('مسیر؟', $suggestions);
$this->components->task('نصب', fn() => /*…*/);
$this->components->twoColumnDetail('کلید', 'مقدار');
$this->alert('هشدار');
```

## ۴. Signature Parsing
```
command:name {required} {optional?} {--option} {--opt=default} {--flag}
```
```php
final class SignatureParser
{
    /** @return array{name:string, arguments:array, options:array, description:?string} */
    public function parse(string $signature): array
    {
        $parts = \preg_split('/\s+/', \trim($signature), 2);
        $name = $parts[0];
        $description = null;

        \preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $arguments = $options = [];

        foreach ($matches[1] as $token) {
            [$token, $desc] = \array_pad(\explode(' : ', $token, 2), 2, null);

            if (\str_starts_with($token, '--')) {                     // option
                $token = \substr($token, 2);
                $default = null; $isFlag = true;
                if (\str_contains($token, '=')) {
                    [$token, $default] = \explode('=', $token, 2);
                    $isFlag = false;
                }
                $options[$token] = ['default' => $default, 'flag' => $isFlag, 'description' => $desc];
            } else {                                                    // argument
                $optional = \str_ends_with($token, '?');
                $token = \rtrim($token, '?');
                $isArray = \str_ends_with($token, '*');
                $arguments[\rtrim($token, '*')] = ['optional'=>$optional, 'array'=>$isArray, 'description'=>$desc];
            }
        }
        return \compact('name', 'arguments', 'options', 'description');
    }
}
```

## ۵. ورودی/خروجی رنگی (بدون ext)
```php
final class Output
{
    private const array COLORS = [
        'info'=>['fg'=>'green'], 'error'=>['fg'=>'white','bg'=>'red'],
        'warn'=>['fg'=>'black','bg'=>'yellow'], 'comment'=>['fg'=>'yellow'],
    ];

    public function writeln(string $text, string $style = ''): void
    {
        if ($this->supportsColor()) {
            $text = $this->colorize($text, $style);
        }
        \fwrite($this->stream, $text . PHP_EOL);
    }

    private function supportsColor(): bool
    {
        if (\getenv('NO_COLOR') !== false) { return false; }
        if (\getenv('FORCE_COLOR') !== false) { return true; }
        return \function_exists('stream_isatty') ? \stream_isatty($this->stream) : false;
    }

    private function colorize(string $text, string $style): string
    {
        $c = self::COLORS[$style] ?? [];
        $fg = isset($c['fg']) ? self::FG[$c['fg']] : '';
        $bg = isset($c['bg']) ? self::BG[$c['bg']] : '';
        return "\033[{$fg}{$bg}m{$text}\033[0m";
    }
}
```

## ۶. Command Discovery + Cache
```php
// کشف خودکار همهٔ Commandها از modules/*/Console/Commands و engines/*/Console
final class CommandRegistry
{
    public function all(): array
    {
        if ($this->cache && ($cached = $this->loadCache()) !== null) { return $cached; }
        $commands = $this->finder->find('*Command', paths: $this->paths);
        \ksort($commands);           // 🔒 قطعی
        $this->saveCache($commands);
        return $commands;
    }
}
```

## ۷. دستورات داخلی تَوان (`tvn`)
| دسته | دستورات |
|---|---|
| **Serve** | `serve`, `about`, `env`, `doctor`, `version` |
| **Optimize** | `optimize`, `optimize:clear`, `config:cache/clear`, `route:cache/clear`, `view:cache/clear`, `autoload:cache` |
| **DB** | `migrate`, `migrate:fresh`, `migrate:rollback`, `migrate:status`, `db:seed`, `db:reset`, `db:show`, `db:wipe` |
| **Make** | `make:module`, `make:controller`, `make:model`, `make:migration`, `make:middleware`, `make:request`, `make:policy`, `make:command`, `make:job`, `make:event`, `make:listener`, `make:seeder`, `make:factory`, `make:test`, `make:engine`, `make:widget`, `make:resource` |
| **Queue** | `queue:work`, `queue:listen`, `queue:retry`, `queue:failed`, `queue:flush`, `queue:restart`, `queue:monitor` |
| **Schedule** | `schedule:run`, `schedule:list` |
| **Module** | `module:list`, `module:install`, `module:uninstall`, `module:enable`, `module:disable`, `module:update`, `module:validate`, `module:extract` |
| **Cache/Log** | `cache:clear`, `cache:stats`, `log:tail`, `log:find`, `log:trace`, `log:clear` |
| **Auth** | `key:generate`, `key:rotate`, `auth:logout-all` |
| **Security** | `security:scan`, `security:sbom` |
| **Quality** | `test`, `bench:all`, `arch:check`, `lint:php`, `style:check/fix` |
| **Misc** | `tinker`, `route:list`, `route:match`, `middleware:list`, `event:list`, `lang:check`, `down`, `up` |

## ۸. کد خروج استاندارد
```php
public const int SUCCESS = 0;
public const int FAILURE = 1;
public const int INVALID = 2;
```

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| Command Injection در `exec` | ممنوعیت shell با ورودی کاربر؛ escapeshellarg |
| اجرای دستور مخرب در PROD | `--force` برای عملیات مخرب + تأیید |
| Secret در argv | `$this->secret()` + عدم لاگ argv |

## ۱۰. تست
| T-01 | Signature parsing (argument/option/flag/array) |
| T-02 | خروجی رنگی + `NO_COLOR` |
| T-03 | Table/Progress/Ask با Input Fake |
| T-04 | Command discovery + cache |
| T-05 | کد خروج درست |
| T-06 | `--force` bypass تأیید |
| T-07 | Nested command (module:install) |

## ۱۱. معیار پذیرش 🧪
- AC-CLI-01: `tvn` بدون آرگومان → فهرست دستورات.
- AC-CLI-02: `tvn about` اطلاعات کامل (نسخه، PHP، env، ماژول‌ها، extensionها).
- AC-CLI-03: همهٔ دستورات §7 ثبت و اجراشدنی.
- AC-CLI-04: Discovery cache ≤ ۱ms در اجرا.

## ۱۲. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.console.v1
engine:
  id: tavaan.engine.console
  namespace: Tavaan\Engine\Console
  path: engines/console
  priority: P1
  phase: M5
  depends_on: [container, event, config]
  facade: null
  config_file: config/console.php
  public_api:
    - class: Command
      methods: [handle, argument, option, info, error, warn, line, table, ask, confirm, choice, progressStart]
    - class: ConsoleKernel
      methods: [handle, call, register, commands]
  loc_estimate: 1600
```
