<?php

declare(strict_types=1);

namespace Modules\Poll\Providers;

use Modules\Poll\Application\UseCases\PollReadService;
use Modules\Poll\Application\UseCases\PollWriteService;
use Modules\Poll\Application\UseCases\VoteService;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Modules\Poll\Domain\Services\VoteTally;
use Modules\Poll\Infrastructure\Persistence\DatabasePollRepository;
use Modules\Poll\Infrastructure\Persistence\DatabaseVoteRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollServiceProvider — مخزن‌ها + UseCaseها + routes + ویجت داشبورد + lang.
 *
 * 🔒 مرز ماژول (ARCH-003): فقط Core Contracts/Facades — بدون ارجاع به موتور.
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class PollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabasePollRepository::class, static fn (): DatabasePollRepository => new DatabasePollRepository());
        $this->app->singleton(DatabaseVoteRepository::class, static fn (): DatabaseVoteRepository => new DatabaseVoteRepository());
        $this->app->alias(DatabasePollRepository::class, 'poll.polls');
        $this->app->alias(DatabaseVoteRepository::class, 'poll.votes');

        if (!$this->app->bound(PollRepositoryInterface::class)) {
            $this->app->singleton(
                PollRepositoryInterface::class,
                static fn (ContainerInterface $c): PollRepositoryInterface => $c->make(DatabasePollRepository::class),
            );
        }
        if (!$this->app->bound(VoteRepositoryInterface::class)) {
            $this->app->singleton(
                VoteRepositoryInterface::class,
                static fn (ContainerInterface $c): VoteRepositoryInterface => $c->make(DatabaseVoteRepository::class),
            );
        }

        $this->app->singleton(VoteTally::class, static fn (): VoteTally => new VoteTally());

        $this->app->singleton(PollWriteService::class, static function (ContainerInterface $c): PollWriteService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('poll', []) : [];

            return new PollWriteService(
                polls: $c->make(PollRepositoryInterface::class),
                votes: $c->make(VoteRepositoryInterface::class),
                config: $config,
            );
        });

        $this->app->singleton(VoteService::class, static function (ContainerInterface $c): VoteService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('poll', []) : [];

            return new VoteService(
                votes: $c->make(VoteRepositoryInterface::class),
                config: $config,
            );
        });

        $this->app->singleton(PollReadService::class, static function (ContainerInterface $c): PollReadService {
            $config = $c->bound('config') ? (array) $c->make('config')->get('poll', []) : [];

            return new PollReadService(
                polls: $c->make(PollRepositoryInterface::class),
                votes: $c->make(VoteRepositoryInterface::class),
                tally: $c->make(VoteTally::class),
                config: $config,
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        // namespace ترجمه: poll::messages.* / poll::status.* → modules/poll/lang/<locale>/*.php
        if ($this->app->bound('lang')) {
            $translator = $this->app->make('lang');
            if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
                $translator->addNamespace('poll', $moduleRoot . '/lang');
            }
        }

        if (!$this->pollEnabled()) {
            return;
        }

        foreach (['web', 'admin', 'api'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }

        $this->registerDashboardWidget();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            PollRepositoryInterface::class,
            VoteRepositoryInterface::class,
            DatabasePollRepository::class,
            DatabaseVoteRepository::class,
            VoteTally::class,
            PollWriteService::class,
            VoteService::class,
            PollReadService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function pollEnabled(): bool
    {
        if (!$this->app->bound('config')) {
            return true;
        }

        return (bool) $this->app->make('config')->get('poll.enabled', true);
    }

    /** ویجت «نظرسنجی» روی پیشخوان مدیریت (DOC-078 §5). */
    private function registerDashboardWidget(): void
    {
        if (!$this->app->bound('admin')) {
            return;
        }

        try {
            $admin = $this->app->make('admin');
            if (\is_object($admin) && \method_exists($admin, 'widget')) {
                $admin->widget(\Modules\Poll\Dashboard\PollsWidget::class);
            }
        } catch (\Throwable) {
            // نبود موتور admin نباید بوت ماژول را بشکند
        }
    }
}
