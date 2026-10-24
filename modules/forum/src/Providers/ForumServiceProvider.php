<?php

declare(strict_types=1);

namespace Modules\Forum\Providers;

use Modules\Content\Domain\Services\Markdown;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Forum\Application\UseCases\ForumReadService;
use Modules\Forum\Application\UseCases\ForumWriteService;
use Modules\Forum\Application\UseCases\ModerationService;
use Modules\Forum\Application\UseCases\PostService;
use Modules\Forum\Application\UseCases\ProfileService;
use Modules\Forum\Application\UseCases\ThreadService;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ProfileRepositoryInterface;
use Modules\Forum\Domain\Repositories\SubscriptionRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Modules\Forum\Domain\Repositories\VoteRepositoryInterface;
use Modules\Forum\Domain\Services\FloodControl;
use Modules\Forum\Domain\Services\MentionExtractor;
use Modules\Forum\Domain\Services\QuoteBuilder;
use Modules\Forum\Domain\Services\ReputationCalculator;
use Modules\Forum\Domain\Services\SpamFilter;
use Modules\Forum\Infrastructure\Notifications\ForumNotifier;
use Modules\Forum\Infrastructure\Notifications\ModeratorFinder;
use Modules\Forum\Infrastructure\Persistence\DatabaseFlagRepository;
use Modules\Forum\Infrastructure\Persistence\DatabaseForumRepository;
use Modules\Forum\Infrastructure\Persistence\DatabasePostRepository;
use Modules\Forum\Infrastructure\Persistence\DatabaseProfileRepository;
use Modules\Forum\Infrastructure\Persistence\DatabaseSubscriptionRepository;
use Modules\Forum\Infrastructure\Persistence\DatabaseThreadRepository;
use Modules\Forum\Infrastructure\Persistence\DatabaseVoteRepository;
use Modules\Forum\Infrastructure\Realtime\ForumBroadcaster;
use Modules\Forum\Infrastructure\Search\ForumSearchIndexer;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumServiceProvider — bindingها + lang/routes + listenerها.
 *
 * 🔒 ARCH-003: فقط Core (Contracts/Facades/Support/Exceptions) + Contracts
 * ماژول‌های هم‌خانواده + SDK مجاز. Policy از راه config اعلامی (rbac.policies).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(ForumRepositoryInterface::class, static fn (): ForumRepositoryInterface => new DatabaseForumRepository());
        $this->app->singleton(ThreadRepositoryInterface::class, static fn (): ThreadRepositoryInterface => new DatabaseThreadRepository());
        $this->app->singleton(PostRepositoryInterface::class, static fn (): PostRepositoryInterface => new DatabasePostRepository());
        $this->app->singleton(VoteRepositoryInterface::class, static fn (): VoteRepositoryInterface => new DatabaseVoteRepository());
        $this->app->singleton(SubscriptionRepositoryInterface::class, static fn (): SubscriptionRepositoryInterface => new DatabaseSubscriptionRepository());
        $this->app->singleton(FlagRepositoryInterface::class, static fn (): FlagRepositoryInterface => new DatabaseFlagRepository());
        $this->app->singleton(ProfileRepositoryInterface::class, static fn (): ProfileRepositoryInterface => new DatabaseProfileRepository());

        // ── Domain: سرویس‌های خالص ───────────────────────────────────────
        $this->app->singleton(FloodControl::class, static fn (ContainerInterface $c): FloodControl => new FloodControl($c->bound('config') ? (array) $c->make('config')->get('forum.flood', []) : []));
        $this->app->singleton(SpamFilter::class, static fn (ContainerInterface $c): SpamFilter => new SpamFilter($c->bound('config') ? (array) $c->make('config')->get('forum.spam', []) : []));
        $this->app->singleton(MentionExtractor::class, static fn (ContainerInterface $c): MentionExtractor => new MentionExtractor((int) ($c->bound('config') ? $c->make('config')->get('forum.mentions.max_per_post', 10) : 10)));
        $this->app->singleton(QuoteBuilder::class, static fn (ContainerInterface $c): QuoteBuilder => new QuoteBuilder((int) ($c->bound('config') ? $c->make('config')->get('forum.quote.max_length', 800) : 800)));
        $this->app->singleton(ReputationCalculator::class, static fn (ContainerInterface $c): ReputationCalculator => new ReputationCalculator($c->bound('config') ? (array) $c->make('config')->get('forum.karma', []) : []));

        // ── Application: UseCaseها ───────────────────────────────────────
        $this->app->singleton(ProfileService::class, static function (ContainerInterface $c): ProfileService {
            return new ProfileService(
                profiles: $c->make(ProfileRepositoryInterface::class),
                users: $c->make(UserReaderInterface::class),
                reputation: $c->make(ReputationCalculator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum', []) : [],
            );
        });

        $this->app->singleton(PostService::class, static function (ContainerInterface $c): PostService {
            return new PostService(
                posts: $c->make(PostRepositoryInterface::class),
                threads: $c->make(ThreadRepositoryInterface::class),
                forums: $c->make(ForumRepositoryInterface::class),
                votes: $c->make(VoteRepositoryInterface::class),
                flags: $c->make(FlagRepositoryInterface::class),
                profiles: $c->make(ProfileService::class),
                markdown: $c->make(Markdown::class),
                flood: $c->make(FloodControl::class),
                spam: $c->make(SpamFilter::class),
                mentions: $c->make(MentionExtractor::class),
                quotes: $c->make(QuoteBuilder::class),
                reputation: $c->make(ReputationCalculator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum', []) : [],
            );
        });

        $this->app->singleton(ThreadService::class, static function (ContainerInterface $c): ThreadService {
            return new ThreadService(
                threads: $c->make(ThreadRepositoryInterface::class),
                posts: $c->make(PostRepositoryInterface::class),
                forums: $c->make(ForumRepositoryInterface::class),
                subscriptions: $c->make(SubscriptionRepositoryInterface::class),
                votes: $c->make(VoteRepositoryInterface::class),
                flags: $c->make(FlagRepositoryInterface::class),
                profiles: $c->make(ProfileService::class),
                postService: $c->make(PostService::class),
                reputation: $c->make(ReputationCalculator::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum', []) : [],
            );
        });

        $this->app->singleton(ForumReadService::class, static function (ContainerInterface $c): ForumReadService {
            return new ForumReadService(
                forums: $c->make(ForumRepositoryInterface::class),
                threads: $c->make(ThreadRepositoryInterface::class),
                posts: $c->make(PostRepositoryInterface::class),
                subscriptions: $c->make(SubscriptionRepositoryInterface::class),
                profiles: $c->make(ProfileService::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum', []) : [],
            );
        });

        $this->app->singleton(ForumWriteService::class, static function (ContainerInterface $c): ForumWriteService {
            return new ForumWriteService(
                forums: $c->make(ForumRepositoryInterface::class),
                threads: $c->make(ThreadRepositoryInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum', []) : [],
            );
        });

        $this->app->singleton(ModerationService::class, static function (ContainerInterface $c): ModerationService {
            return new ModerationService(
                flags: $c->make(FlagRepositoryInterface::class),
                posts: $c->make(PostRepositoryInterface::class),
                postService: $c->make(PostService::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum.moderation', []) : [],
            );
        });

        // ── Infrastructure: شنونده‌ها ────────────────────────────────────
        $this->app->singleton(ForumSearchIndexer::class, static function (ContainerInterface $c): ForumSearchIndexer {
            return new ForumSearchIndexer(
                threads: $c->make(ThreadRepositoryInterface::class),
                posts: $c->make(PostRepositoryInterface::class),
                config: $c->bound('config') ? (array) $c->make('config')->get('forum.search', []) : [],
            );
        });

        $this->app->singleton(ForumBroadcaster::class, static function (ContainerInterface $c): ForumBroadcaster {
            return new ForumBroadcaster(
                config: $c->bound('config') ? (array) $c->make('config')->get('forum.realtime', []) : [],
            );
        });

        $this->app->singleton(ModeratorFinder::class, static function (ContainerInterface $c): ModeratorFinder {
            return new ModeratorFinder(
                roles: $c->bound('config')
                    ? (array) $c->make('config')->get('forum.notifications.moderator_roles', ['moderator', 'admin'])
                    : ['moderator', 'admin'],
            );
        });

        $this->app->singleton(ForumNotifier::class, static function (ContainerInterface $c): ForumNotifier {
            return new ForumNotifier(
                threads: $c->make(ThreadRepositoryInterface::class),
                subscriptions: $c->make(SubscriptionRepositoryInterface::class),
                users: $c->make(UserReaderInterface::class),
                profiles: $c->make(ProfileService::class),
                moderators: $c->make(ModeratorFinder::class),
                container: $c,
                config: $c->bound('config') ? (array) $c->make('config')->get('forum.notifications', []) : [],
            );
        });
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        // ویوها: namespace `forum::` توسط ViewServiceProvider از manifest ثبت
        // می‌شود؛ override پوسته از راه `resources/views/vendor/forum`
        // (کپی‌شده توسط `tvn kit:install forum`).
        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            ForumRepositoryInterface::class,
            ThreadRepositoryInterface::class,
            PostRepositoryInterface::class,
            VoteRepositoryInterface::class,
            SubscriptionRepositoryInterface::class,
            FlagRepositoryInterface::class,
            ProfileRepositoryInterface::class,
            ProfileService::class,
            PostService::class,
            ThreadService::class,
            ForumReadService::class,
            ForumWriteService::class,
            ModerationService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function registerLang(string $moduleRoot): void
    {
        if (!$this->app->bound('lang')) {
            return;
        }
        $translator = $this->app->make('lang');
        if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
            $translator->addNamespace('forum', $moduleRoot . '/lang');
        }
    }

    private function registerRoutes(string $moduleRoot): void
    {
        foreach (['web', 'admin'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }
    }

    /** سیم‌کشی رویدادها (DOC-094 §5) — قرارداد ($eventName, $payload). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        Event::listen('forum.thread.created', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onThreadCreated($event, $payload);
            $app->make(ForumBroadcaster::class)->onThreadCreated($event, $payload);
            $app->make(ForumNotifier::class)->onThreadCreated($event, $payload);
        });
        Event::listen('forum.thread.updated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onThreadUpdated($event, $payload);
        });
        Event::listen('forum.post.created', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onPostCreated($event, $payload);
            $app->make(ForumBroadcaster::class)->onPostCreated($event, $payload);
            $app->make(ForumNotifier::class)->onPostCreated($event, $payload);
        });
        Event::listen('forum.post.updated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onPostUpdated($event, $payload);
        });
        Event::listen('forum.post.hidden', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onPostHidden($event, $payload);
        });
        Event::listen('forum.post.deleted', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onPostDeleted($event, $payload);
        });
        Event::listen('forum.post.flagged', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumNotifier::class)->onPostFlagged($event, $payload);
        });
        Event::listen('forum.post.solved', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ForumSearchIndexer::class)->onPostSolved($event, $payload);
            $app->make(ForumNotifier::class)->onPostSolved($event, $payload);
        });
    }
}
