<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CrmServiceProvider — bindingها + routes/lang + listenerها + schedule.
 */

namespace Modules\Crm\Providers;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\ActivityService;
use Modules\Crm\Application\UseCases\CampaignService;
use Modules\Crm\Application\UseCases\ContactService;
use Modules\Crm\Application\UseCases\CrmReportService;
use Modules\Crm\Application\UseCases\LeadService;
use Modules\Crm\Application\UseCases\PipelineService;
use Modules\Crm\Application\UseCases\SlaMonitorService;
use Modules\Crm\Application\UseCases\TicketService;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Modules\Crm\Domain\Repositories\CampaignRepositoryInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Modules\Crm\Domain\Services\AudienceResolver;
use Modules\Crm\Domain\Services\ForecastCalculator;
use Modules\Crm\Domain\Services\LeadScorer;
use Modules\Crm\Domain\Services\SlaPolicy;
use Modules\Crm\Domain\Services\TicketNumberGenerator;
use Modules\Crm\Infrastructure\Integration\ShopContactSync;
use Modules\Crm\Infrastructure\Notifications\CrmNotifier;
use Modules\Crm\Infrastructure\Persistence\DatabaseActivityRepository;
use Modules\Crm\Infrastructure\Persistence\DatabaseCampaignRepository;
use Modules\Crm\Infrastructure\Persistence\DatabaseContactRepository;
use Modules\Crm\Infrastructure\Persistence\DatabaseLeadRepository;
use Modules\Crm\Infrastructure\Persistence\DatabaseOpportunityRepository;
use Modules\Crm\Infrastructure\Persistence\DatabaseTicketRepository;
use Modules\Crm\Infrastructure\Search\ContactSearchIndexer;
use Modules\Crm\Infrastructure\Workflow\PipelineWorkflow;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول CRM
 *
 * 🔒 ARCH-003: فقط Core + Contracts ماژول‌های هم‌خانواده + SDK مجاز.
 * Policyها از راه config اعلامی (modules/crm/config/rbac.php).
 * یکپارچگی shop فقط از راه رویداد رشته‌ای `shop.order.paid` (بدون ارجاع کلاسی).
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = fn (string $key = 'crm'): array => $this->app->bound('config')
            ? (array) $this->app->make('config')->get($key, [])
            : [];

        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(ContactRepositoryInterface::class, fn (): ContactRepositoryInterface => new DatabaseContactRepository(
            (bool) ($config()['encryption']['national_id'] ?? true),
        ));
        $this->app->singleton(LeadRepositoryInterface::class, static fn (): LeadRepositoryInterface => new DatabaseLeadRepository());
        $this->app->singleton(OpportunityRepositoryInterface::class, static fn (): OpportunityRepositoryInterface => new DatabaseOpportunityRepository());
        $this->app->singleton(ActivityRepositoryInterface::class, static fn (): ActivityRepositoryInterface => new DatabaseActivityRepository());
        $this->app->singleton(TicketRepositoryInterface::class, static fn (): TicketRepositoryInterface => new DatabaseTicketRepository());
        $this->app->singleton(CampaignRepositoryInterface::class, static fn (): CampaignRepositoryInterface => new DatabaseCampaignRepository());

        // ── Domain Services (خالص) ────────────────────────────────────────
        $this->app->singleton(LeadScorer::class, fn (): LeadScorer => new LeadScorer((array) ($config()['scoring'] ?? [])));
        $this->app->singleton(SlaPolicy::class, fn (): SlaPolicy => new SlaPolicy((array) ($config()['sla'] ?? [])));
        $this->app->singleton(TicketNumberGenerator::class, fn (): TicketNumberGenerator => new TicketNumberGenerator(
            (string) ($config()['tickets']['number_prefix'] ?? 'TKT'),
        ));
        $this->app->singleton(ForecastCalculator::class, static fn (): ForecastCalculator => new ForecastCalculator());
        $this->app->singleton(AudienceResolver::class, static fn (): AudienceResolver => new AudienceResolver());

        // ── Workflow pipeline ─────────────────────────────────────────────
        $this->app->singleton(PipelineWorkflow::class, fn (): PipelineWorkflow => new PipelineWorkflow(
            (string) ($config()['pipeline']['workflow'] ?? 'crm-sales-pipeline'),
        ));

        // ── Application: UseCaseها ────────────────────────────────────────
        $this->app->singleton(ContactService::class, fn (ContainerInterface $c): ContactService => new ContactService(
            contacts: $c->make(ContactRepositoryInterface::class),
            config: $config(),
        ));

        $this->app->singleton(PipelineService::class, fn (ContainerInterface $c): PipelineService => new PipelineService(
            opportunities: $c->make(OpportunityRepositoryInterface::class),
            workflow: $c->make(PipelineWorkflow::class),
            forecast: $c->make(ForecastCalculator::class),
            config: $config(),
        ));

        $this->app->singleton(LeadService::class, fn (ContainerInterface $c): LeadService => new LeadService(
            leads: $c->make(LeadRepositoryInterface::class),
            contacts: $c->make(ContactRepositoryInterface::class),
            activities: $c->make(ActivityRepositoryInterface::class),
            scorer: $c->make(LeadScorer::class),
            pipeline: $c->make(PipelineService::class),
            config: $config(),
        ));

        $this->app->singleton(ActivityService::class, fn (ContainerInterface $c): ActivityService => new ActivityService(
            activities: $c->make(ActivityRepositoryInterface::class),
            leads: $c->make(LeadRepositoryInterface::class),
            leadService: $c->make(LeadService::class),
        ));

        $this->app->singleton(TicketService::class, fn (ContainerInterface $c): TicketService => new TicketService(
            tickets: $c->make(TicketRepositoryInterface::class),
            contacts: $c->make(ContactRepositoryInterface::class),
            sla: $c->make(SlaPolicy::class),
            numbers: $c->make(TicketNumberGenerator::class),
            config: $config(),
        ));

        $this->app->singleton(SlaMonitorService::class, fn (ContainerInterface $c): SlaMonitorService => new SlaMonitorService(
            tickets: $c->make(TicketRepositoryInterface::class),
            config: $config(),
        ));

        $this->app->singleton(CampaignService::class, fn (ContainerInterface $c): CampaignService => new CampaignService(
            campaigns: $c->make(CampaignRepositoryInterface::class),
            contacts: $c->make(ContactRepositoryInterface::class),
            audience: $c->make(AudienceResolver::class),
            config: (array) ($config()['campaigns'] ?? []),
        ));

        $this->app->singleton(CrmReportService::class, fn (ContainerInterface $c): CrmReportService => new CrmReportService(
            opportunities: $c->make(OpportunityRepositoryInterface::class),
            leads: $c->make(LeadRepositoryInterface::class),
            tickets: $c->make(TicketRepositoryInterface::class),
            pipeline: $c->make(PipelineService::class),
        ));

        // ── Infrastructure: شنونده‌ها ─────────────────────────────────────
        $this->app->singleton(CrmNotifier::class, fn (ContainerInterface $c): CrmNotifier => new CrmNotifier(
            users: $c->make(UserReaderInterface::class),
            contacts: $c->make(ContactRepositoryInterface::class),
            container: $c,
            config: $config(),
        ));

        $this->app->singleton(ContactSearchIndexer::class, fn (ContainerInterface $c): ContactSearchIndexer => new ContactSearchIndexer(
            contacts: $c->make(ContactRepositoryInterface::class),
            config: (array) ($config()['search'] ?? []),
        ));

        $this->app->singleton(ShopContactSync::class, fn (ContainerInterface $c): ShopContactSync => new ShopContactSync(
            contacts: $c->make(ContactService::class),
            users: $c->make(UserReaderInterface::class),
            config: (array) ($config()['sync'] ?? []),
        ));
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
        $this->registerSchedule();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            ContactRepositoryInterface::class,
            LeadRepositoryInterface::class,
            OpportunityRepositoryInterface::class,
            ActivityRepositoryInterface::class,
            TicketRepositoryInterface::class,
            CampaignRepositoryInterface::class,
            PipelineWorkflow::class,
            ContactService::class,
            LeadService::class,
            PipelineService::class,
            ActivityService::class,
            TicketService::class,
            SlaMonitorService::class,
            CampaignService::class,
            CrmReportService::class,
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
            $translator->addNamespace('crm', $moduleRoot . '/lang');
        }
    }

    private function registerRoutes(string $moduleRoot): void
    {
        foreach (['web', 'admin', 'api'] as $channel) {
            $file = $moduleRoot . '/routes/' . $channel . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }
    }

    /** سیم‌کشی رویدادها (DOC-096 §5 + §7). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        // سرنخ — scoring/assignment/notification
        Event::listen('crm.lead.created', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CrmNotifier::class)->onLeadCreated($event, $payload);
        });

        // فرصت — won (جشن/فاکتور/گزارش)
        Event::listen('crm.opportunity.won', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CrmNotifier::class)->onOpportunityWon($event, $payload);
        });

        // تیکت — SLA timer/assignment/notification
        Event::listen('crm.ticket.created', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CrmNotifier::class)->onTicketCreated($event, $payload);
        });
        Event::listen('crm.ticket.assigned', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CrmNotifier::class)->onTicketCreated($event, $payload);
        });
        Event::listen('crm.ticket.sla_breached', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CrmNotifier::class)->onSlaBreached($event, $payload);
        });
        Event::listen('crm.ticket.resolved', static function (string $event, mixed $payload) use ($app): void {
            $app->make(CrmNotifier::class)->onTicketResolved($event, $payload);
        });

        // مخاطب — ایندکس جست‌وجو
        Event::listen('crm.contact.synced', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ContactSearchIndexer::class)->indexContact((string) ((array) $payload)['contact_id'] ?? '');
        });

        // یکپارچگی shop (DOC-096 §7) — customer → contact sync
        Event::listen('shop.order.paid', static function (string $event, mixed $payload) use ($app): void {
            $app->make(ShopContactSync::class)->onShopOrderPaid($event, $payload);
        });
    }

    /** ثبت cron — الگوی news (در حالت testing گارد می‌شود). */
    private function registerSchedule(): void
    {
        if (!$this->app->bound('schedule')) {
            return;
        }
        if ($this->runningInTests()) {
            return;
        }

        $schedule = $this->app->make('schedule');
        if (\is_object($schedule) && \method_exists($schedule, 'command')) {
            $event = $schedule->command('crm:check-sla');
            if (\is_object($event) && \method_exists($event, 'everyMinute')) {
                $event->everyMinute();
            }
            $campaigns = $schedule->command('crm:send-campaigns');
            if (\is_object($campaigns) && \method_exists($campaigns, 'everyMinute')) {
                $campaigns->everyMinute();
            }
        }
    }

    /**
     * آیا در حالت تست هستیم؟ — `$this->app` در Providerها Container است
     * (نه Application)؛ از helper سراسری `app()` و $_SERVER استفاده می‌شود.
     */
    private function runningInTests(): bool
    {
        if ((string) ($_SERVER['TVN_APP_ENV'] ?? '') === 'testing') {
            return true;
        }

        try {
            if (\function_exists('app')) {
                $app = \app();
                if (\is_object($app) && \method_exists($app, 'environment')) {
                    return (string) $app->environment() === 'testing';
                }
                if (\is_object($app) && $app->bound('config')) {
                    return (string) $app->make('config')->get('app.env', '') === 'testing';
                }
            }
        } catch (\Throwable) {
            // بی‌اثر
        }

        return false;
    }
}
