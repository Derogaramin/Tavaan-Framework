<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Module\ModuleManager;
use Tavaan\Engine\Module\ModuleRegistry;

/**
 * اعتبارسنجی manifest ماژول‌ها/موتورها (AC-SCF-02 در DOC-077؛ DoD §2).
 *
 * دو سطح: schema (که هنگام کشف در boot پاس شده) + semantic (نسخهٔ خالی،
 * چرخهٔ وابستگی، وجود module.json). با نام → فقط همان ماژول.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class ModuleValidateCommand extends Command
{
    protected string $signature = 'module:validate {name? : شناسه یا slug ماژول (خالی = همه)}';

    protected string $description = 'اعتبارسنجی manifest ماژول‌ها/موتورها';

    public function handle(): int
    {
        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);
        /** @var ModuleManager $manager */
        $manager = $this->app->make(ModuleManager::class);

        $name = $this->argument('name');
        $name = \is_string($name) && $name !== '' ? $name : null;

        $modules = $registry->all();
        if ($name !== null) {
            $modules = \array_values(\array_filter(
                $modules,
                static fn (object $m): bool => $m->id() === $name
                    || \str_ends_with($m->id(), '.' . $name)
                    || \basename($m->path()) === $name,
            ));
            if ($modules === []) {
                $this->error('  ✗ ماژول/موتوری با نام [' . $name . '] یافت نشد.');

                return self::FAILURE;
            }
        }

        $errors = $name === null ? $manager->validate() : [];
        $rows = [];
        foreach ($modules as $module) {
            $id = $module->id();
            $problems = [];
            if ($module->version() === '') {
                $problems[] = 'version empty';
            }
            if (!\is_file($module->path() . '/module.json')) {
                $problems[] = 'module.json missing';
            }

            $rows[] = [$id, $module->version(), $problems === [] ? '✅ معتبر' : '⛔ ' . \implode('؛ ', $problems)];
            foreach ($problems as $problem) {
                $errors[] = $id . ': ' . $problem;
            }
        }

        $this->table(['ID', 'Version', 'Status'], $rows);

        if ($errors !== []) {
            $this->output->newLine();
            foreach ($errors as $error) {
                $this->error('  ✗ ' . $error);
            }

            return self::FAILURE;
        }

        $this->info('  ✓ همهٔ manifestها معتبرند (' . \count($rows) . ' مورد).');

        return self::SUCCESS;
    }
}
