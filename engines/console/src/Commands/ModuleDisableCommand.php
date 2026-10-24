<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Module\ModuleManager;

/**
 * `tvn module:disable {name}` — غیرفعال‌سازی ماژول/موتور (DOC-083 §2.8).
 *
 * اگر ماژول فعالِ دیگری به آن وابسته باشد، خطا (hasDependents).
 *
 * 🔒 ممیزی M0–M8 (R3): متدها در ModuleManager بودند ولی CLI نداشتند.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class ModuleDisableCommand extends Command
{
    protected string $signature = 'module:disable {name : شناسه یا slug ماژول}';

    protected string $description = 'غیرفعال‌سازی ماژول/موتور (با بررسی وابسته‌ها)';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        /** @var ModuleManager $manager */
        $manager = $this->app->make(ModuleManager::class);

        try {
            $manager->disable($name);
        } catch (\Tavaan\Core\Exceptions\TavaanException $e) {
            $this->error('  ✗ ' . $this->translate($e->messageId, $e->context));
            if ($e->hint !== null) {
                $this->comment('    راهنما: ' . $e->hint);
            }

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('  ✗ ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('  ✓ ماژول [' . $name . '] غیرفعال شد.');

        return self::SUCCESS;
    }

    /** ترجمهٔ messageId با fallback به خودِ کلید. @param array<string, mixed> $context */
    private function translate(string $messageId, array $context = []): string
    {
        if (\function_exists('trans')) {
            $text = \trans($messageId);
            if ($text !== $messageId) {
                foreach ($context as $key => $value) {
                    $text = \str_replace(':' . $key, (string) $value, $text);
                }

                return $text;
            }
        }

        return $messageId;
    }
}
