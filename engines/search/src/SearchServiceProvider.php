<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Search
 * SearchServiceProvider — ثبت SearchManager به‌عنوان `search` (Facade: Search).
 *
 * ایندکس خودکار (DOC-052 §5): به رویدادهای `model.saved`/`model.deleted`
 * گوش می‌دهد و مدل‌های Searchable را ایندکس/حذف می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchManager::class, static fn (ContainerInterface $c): SearchManager => new SearchManager($c));
        $this->app->alias(SearchManager::class, 'search');
        $this->app->alias(SearchManager::class, \Tavaan\Core\Contracts\Search\SearchManagerInterface::class);
    }

    public function boot(): void
    {
        // resolver ایستا برای trait Searchable
        if ($this->app->bound(SearchManager::class)) {
            SearchResolver::set($this->app->make(SearchManager::class));
        }

        $this->registerAutoIndexing();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [SearchManager::class, 'search'];
    }

    private function registerAutoIndexing(): void
    {
        if (!$this->app->bound('events') || !$this->app->bound('config')) {
            return;
        }
        if (!(bool) $this->app->make('config')->get('search.auto_index', true)) {
            return;
        }

        $app = $this->app;
        $dispatcher = $this->app->make('events');

        $dispatcher->listen('model.saved', static function (string $event, mixed $payload) use ($app): void {
            self::autoIndex($app, $payload);
        });

        $dispatcher->listen('model.deleted', static function (string $event, mixed $payload) use ($app): void {
            self::autoUnindex($app, $payload);
        });
    }

    private static function autoIndex(ContainerInterface $app, mixed $payload): void
    {
        $class = \is_array($payload) ? (string) ($payload['model'] ?? '') : '';
        $key = \is_array($payload) ? ($payload['key'] ?? null) : null;
        if ($class === '' || $key === null || !\class_exists($class)) {
            return;
        }
        if (!\method_exists($class, 'toSearchableArray') || !\method_exists($class, 'searchIndex')) {
            return;   // فقط مدل‌های Searchable
        }

        try {
            $model = $class::find($key);
            if ($model === null) {
                return;
            }
            /** @var SearchManager $search */
            $search = $app->make(SearchManager::class);
            $search->index($model->searchIndex(), $model);
        } catch (\Throwable) {
            // 🔒 ایندکس خودکار هرگز نباید ذخیرهٔ مدل را بشکند
        }
    }

    private static function autoUnindex(ContainerInterface $app, mixed $payload): void
    {
        $class = \is_array($payload) ? (string) ($payload['model'] ?? '') : '';
        $key = \is_array($payload) ? ($payload['key'] ?? null) : null;
        if ($class === '' || $key === null || !\class_exists($class)) {
            return;
        }
        if (!\method_exists($class, 'searchIndex')) {
            return;
        }

        try {
            /** @var SearchManager $search */
            $search = $app->make(SearchManager::class);
            /** @var \Tavaan\Engine\Database\ORM\Model $probe */
            $probe = new $class();
            $search->delete($probe->searchIndex(), (string) $key);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
