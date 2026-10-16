<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Tenancy\TenantResolverInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Tenancy\Middleware\IdentifyTenantMiddleware;
use Tavaan\Engine\Tenancy\Resolvers\DefaultResolver;
use Tavaan\Engine\Tenancy\Resolvers\DomainResolver;
use Tavaan\Engine\Tenancy\Resolvers\HeaderResolver;
use Tavaan\Engine\Tenancy\Resolvers\PathResolver;
use Tavaan\Engine\Tenancy\Resolvers\QueryResolver;
use Tavaan\Engine\Tenancy\Resolvers\TokenResolver;

/**
 * تَوان | Tavaan — Tenancy
 * TenancyServiceProvider — ثبت TenantManager (`tenant`) و اجزای چنداجاره‌ای.
 *
 * aliasها: `tenant` (TenantManager)، `tenant.quota` (QuotaGuard)،
 * `tenant.provisioner`، middleware `tenant`.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        TenancyResolver::set($this->app);

        $this->app->singleton(TenantManager::class, static function (ContainerInterface $c): TenantManager {
            $config = $c->bound('config') ? $c->make('config') : null;
            $events = $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class)
                : ($c->bound('events') ? $c->make('events') : null);

            return new TenantManager(
                $c,
                $config instanceof ConfigRepository ? $config : null,
                $events instanceof DispatcherInterface ? $events : null,
            );
        });
        $this->app->alias(TenantManager::class, 'tenant');

        $this->app->singleton(TenantResolverChain::class, static function (ContainerInterface $c): TenantResolverChain {
            $config = $c->bound('config') ? (array) $c->make('config')->get('tenancy', []) : [];
            $manager = $c->make(TenantManager::class);

            return new TenantResolverChain(self::buildResolvers($config, $manager));
        });

        $this->app->singleton(IdentifyTenantMiddleware::class, static fn (ContainerInterface $c): IdentifyTenantMiddleware
            => new IdentifyTenantMiddleware($c->make(TenantManager::class), $c->make(TenantResolverChain::class)));

        $this->app->singleton(DatabaseTenantManager::class, static function (ContainerInterface $c): DatabaseTenantManager {
            $config = $c->bound('config') ? $c->make('config') : null;
            $events = $c->bound('events') ? $c->make('events') : null;

            return new DatabaseTenantManager(
                $c,
                $config instanceof ConfigRepository ? $config : null,
                $events instanceof DispatcherInterface ? $events : null,
            );
        });

        $this->app->singleton(SchemaTenantManager::class, static function (ContainerInterface $c): SchemaTenantManager {
            $config = $c->bound('config') ? $c->make('config') : null;
            $events = $c->bound('events') ? $c->make('events') : null;

            return new SchemaTenantManager(
                $c,
                $config instanceof ConfigRepository ? $config : null,
                $events instanceof DispatcherInterface ? $events : null,
            );
        });

        $this->app->singleton(QuotaGuard::class, static function (ContainerInterface $c): QuotaGuard {
            $cache = null;
            if ($c->bound(CacheRepository::class)) {
                $candidate = $c->make(CacheRepository::class);
                $cache = $candidate instanceof CacheRepository ? $candidate : null;
            } elseif ($c->bound('cache')) {
                $manager = $c->make('cache');
                if ($manager instanceof CacheRepository) {
                    $cache = $manager;
                } elseif (\method_exists($manager, 'repository')) {
                    $cache = $manager->repository();
                }
            }
            $events = $c->bound('events') ? $c->make('events') : null;

            return new QuotaGuard(
                $cache instanceof CacheRepository ? $cache : null,
                $events instanceof DispatcherInterface ? $events : null,
            );
        });
        $this->app->alias(QuotaGuard::class, 'tenant.quota');

        $this->app->singleton(TenantProvisioner::class, static fn (ContainerInterface $c): TenantProvisioner
            => new TenantProvisioner($c, $c->make(TenantManager::class)));
        $this->app->alias(TenantProvisioner::class, 'tenant.provisioner');

        // store «tenant» برای cache ایزوله (Cache::store('tenant'))
        $this->registerTenantCacheStore();
    }

    public function boot(): void
    {
        // middleware alias
        if ($this->app->bound('config')) {
            /** @var ConfigRepository $config */
            $config = $this->app->make('config');
            $aliases = (array) $config->get('http.middleware.aliases', []);
            $aliases += ['tenant' => IdentifyTenantMiddleware::class];
            $config->set('http.middleware.aliases', $aliases);
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            TenantManager::class, 'tenant',
            TenantResolverChain::class,
            IdentifyTenantMiddleware::class,
            DatabaseTenantManager::class,
            SchemaTenantManager::class,
            QuotaGuard::class, 'tenant.quota',
            TenantProvisioner::class, 'tenant.provisioner',
        ];
    }

    /**
     * ساخت resolverها از config (ترتیب = زنجیره — DOC-028 §2.3).
     *
     * @param array<string, mixed> $config
     *
     * @return list<TenantResolverInterface>
     */
    private static function buildResolvers(array $config, TenantManager $manager): array
    {
        $chain = (array) ($config['resolver'] ?? ['domain', 'header', 'path', 'token']);
        $settings = (array) ($config['resolvers'] ?? []);

        $resolvers = [];
        foreach ($chain as $name) {
            $resolvers[] = match ((string) $name) {
                'domain' => new DomainResolver(
                    (string) ($settings['domain_suffix'] ?? ''),
                    static fn (string $domain): ?string => $manager->findByDomain($domain)?->identifier(),
                ),
                'header' => new HeaderResolver((string) ($settings['header'] ?? 'X-Tenant-Id')),
                'path' => new PathResolver((string) ($settings['path_prefix'] ?? 't')),
                'query' => new QueryResolver(
                    (string) ($settings['query_param'] ?? 'tenant'),
                    (bool) ($settings['query_dev_only'] ?? true),
                ),
                'token' => new TokenResolver(
                    (string) ($settings['token_claim'] ?? 'tenant_id'),
                    (bool) ($settings['token_decode_unverified'] ?? false),
                ),
                'default' => new DefaultResolver(($settings['default_tenant'] ?? '') !== '' ? (string) $settings['default_tenant'] : null),
                default => null,
            };
        }

        return \array_values(\array_filter($resolvers));
    }

    /** ثبت driver «tenant» در CacheManager (دکوراتور scoped). */
    private function registerTenantCacheStore(): void
    {
        if (!$this->app->bound('cache')) {
            return;
        }

        try {
            $cacheManager = $this->app->make('cache');
        } catch (\Throwable) {
            return;
        }
        if (!\method_exists($cacheManager, 'extend')) {
            return;
        }

        $app = $this->app;
        $cacheManager->extend('tenant', static function (array $config) use ($app): \Tavaan\Core\Contracts\Cache\StoreInterface {
            $innerStore = isset($config['store']) && \is_string($config['store']) && $config['store'] !== ''
                ? $config['store']
                : null;
            /** @var \Tavaan\Engine\Cache\CacheManager $manager */
            $manager = $app->make('cache');
            /** @var \Tavaan\Core\Contracts\Cache\RepositoryInterface $innerRepo */
            $innerRepo = $manager->store($innerStore);

            return new TenantScopeStore($innerRepo->getStore());
        });

        // store پیش‌فرض scoped
        if (!$this->app->bound('config')) {
            return;
        }
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');
        if ((bool) $config->get('tenancy.isolate_cache', true)) {
            $stores = (array) $config->get('cache.stores', []);
            if (!isset($stores['tenant'])) {
                $stores['tenant'] = ['driver' => 'tenant', 'store' => null];
                $config->set('cache.stores', $stores);
            }
        }
    }
}
