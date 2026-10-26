<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * BpmsServiceProvider — bindingها + routes/lang + listenerها + schedule.
 */

namespace Modules\Bpms\Providers;

use Modules\Bpms\Application\UseCases\ActionRegistry;
use Modules\Bpms\Application\UseCases\BpmsAuditRecorder;
use Modules\Bpms\Application\UseCases\DesignerService;
use Modules\Bpms\Application\UseCases\MonitoringService;
use Modules\Bpms\Application\UseCases\ProcessContext;
use Modules\Bpms\Application\UseCases\ProcessEngine;
use Modules\Bpms\Application\UseCases\TimerService;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\HistoryRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\RoleReaderInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\Bpms\Domain\Services\DiagramGenerator;
use Modules\Bpms\Domain\Services\ExpressionEvaluator;
use Modules\Bpms\Domain\Services\ModelValidator;
use Modules\Bpms\Infrastructure\Notifications\BpmsNotifier;
use Modules\Bpms\Infrastructure\Persistence\DatabaseDefinitionRepository;
use Modules\Bpms\Infrastructure\Persistence\DatabaseHistoryRepository;
use Modules\Bpms\Infrastructure\Persistence\DatabaseInstanceRepository;
use Modules\Bpms\Infrastructure\Persistence\DatabaseRoleReader;
use Modules\Bpms\Infrastructure\Persistence\DatabaseTaskRepository;
use Modules\Bpms\Infrastructure\Realtime\TaskBroadcaster;
use Modules\Bpms\Infrastructure\Workflow\WorkflowBridge;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — ماژول BPMS
 *
 * 🔒 ARCH-003: فقط Core + Facadeها + Contracts ماژول‌های هم‌خانواده +
 * SDK مجاز (Console\Command، Audit\AuditEvent). ارتباط با موتور workflow
 * فقط از راه Facade (WorkflowBridge).
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class BpmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = fn (string $key = 'bpms'): array => $this->app->bound('config')
            ? (array) $this->app->make('config')->get($key, [])
            : [];

        // ── Infrastructure: مخزن‌ها ──────────────────────────────────────
        $this->app->singleton(DefinitionRepositoryInterface::class, static fn (): DefinitionRepositoryInterface => new DatabaseDefinitionRepository());
        $this->app->singleton(InstanceRepositoryInterface::class, static fn (): InstanceRepositoryInterface => new DatabaseInstanceRepository());
        $this->app->singleton(TaskRepositoryInterface::class, static fn (): TaskRepositoryInterface => new DatabaseTaskRepository());
        $this->app->singleton(HistoryRepositoryInterface::class, static fn (): HistoryRepositoryInterface => new DatabaseHistoryRepository());
        $this->app->singleton(RoleReaderInterface::class, static fn (): RoleReaderInterface => new DatabaseRoleReader());

        // ── Domain Services (خالص) ────────────────────────────────────────
        $this->app->singleton(ExpressionEvaluator::class, static fn (): ExpressionEvaluator => new ExpressionEvaluator());
        $this->app->singleton(ModelValidator::class, static fn (): ModelValidator => new ModelValidator());
        $this->app->singleton(DiagramGenerator::class, static fn (): DiagramGenerator => new DiagramGenerator());

        // ── Infrastructure: پل‌ها ─────────────────────────────────────────
        $this->app->singleton(WorkflowBridge::class, fn (): WorkflowBridge => new WorkflowBridge(
            (array) ($config()['workflow_mirror'] ?? []),
        ));
        $this->app->singleton(BpmsAuditRecorder::class, static fn (): BpmsAuditRecorder => new BpmsAuditRecorder());

        // ── Application: UseCaseها ────────────────────────────────────────
        $this->app->singleton(ActionRegistry::class, static fn (): ActionRegistry => new ActionRegistry());

        $this->app->singleton(ProcessEngine::class, fn (ContainerInterface $c): ProcessEngine => new ProcessEngine(
            definitions: $c->make(DefinitionRepositoryInterface::class),
            instances: $c->make(InstanceRepositoryInterface::class),
            tasks: $c->make(TaskRepositoryInterface::class),
            history: $c->make(HistoryRepositoryInterface::class),
            roles: $c->make(RoleReaderInterface::class),
            actions: $c->make(ActionRegistry::class),
            evaluator: $c->make(ExpressionEvaluator::class),
            bridge: $c->make(WorkflowBridge::class),
            container: $c,
            config: $config(),
        ));

        $this->app->singleton(DesignerService::class, fn (ContainerInterface $c): DesignerService => new DesignerService(
            definitions: $c->make(DefinitionRepositoryInterface::class),
            validator: $c->make(ModelValidator::class),
            bridge: $c->make(WorkflowBridge::class),
            audit: $c->make(BpmsAuditRecorder::class),
        ));

        $this->app->singleton(TimerService::class, fn (ContainerInterface $c): TimerService => new TimerService(
            tasks: $c->make(TaskRepositoryInterface::class),
            instances: $c->make(InstanceRepositoryInterface::class),
            definitions: $c->make(DefinitionRepositoryInterface::class),
            actions: $c->make(ActionRegistry::class),
            container: $c,
            config: $config(),
        ));

        $this->app->singleton(MonitoringService::class, fn (ContainerInterface $c): MonitoringService => new MonitoringService(
            instances: $c->make(InstanceRepositoryInterface::class),
            tasks: $c->make(TaskRepositoryInterface::class),
            history: $c->make(HistoryRepositoryInterface::class),
            config: (array) ($config()['monitoring'] ?? []),
        ));

        // ── Infrastructure: شنونده‌ها ─────────────────────────────────────
        $this->app->singleton(BpmsNotifier::class, fn (ContainerInterface $c): BpmsNotifier => new BpmsNotifier(
            users: $c->make(UserReaderInterface::class),
            roles: $c->make(RoleReaderInterface::class),
            container: $c,
            config: $config(),
        ));

        $this->app->singleton(TaskBroadcaster::class, fn (): TaskBroadcaster => new TaskBroadcaster(
            (array) ($config()['realtime'] ?? []),
        ));

        $this->registerBuiltinActions();
    }

    public function boot(): void
    {
        $moduleRoot = \dirname(__DIR__, 2);

        $this->registerLang($moduleRoot);
        $this->registerRoutes($moduleRoot);
        $this->registerListeners();
        $this->registerSchedule();
        $this->extendAuditEventAllowlist();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [
            DefinitionRepositoryInterface::class,
            InstanceRepositoryInterface::class,
            TaskRepositoryInterface::class,
            HistoryRepositoryInterface::class,
            RoleReaderInterface::class,
            ExpressionEvaluator::class,
            ModelValidator::class,
            DiagramGenerator::class,
            WorkflowBridge::class,
            BpmsAuditRecorder::class,
            ActionRegistry::class,
            ProcessEngine::class,
            DesignerService::class,
            TimerService::class,
            MonitoringService::class,
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * اکشن‌های داخلی service task (§6 — از پیش ثبت‌شده، نه از ورودی کاربر).
     * اپلیکیشن می‌تواند اکشن‌های دامنهٔ خود را افزود کند:
     * `$app->make(ActionRegistry::class)->register('approveLeave', …)`.
     */
    private function registerBuiltinActions(): void
    {
        /** @var ActionRegistry $actions */
        $actions = $this->app->make(ActionRegistry::class);

        // no-op — نقطهٔ پایانی خنثی (تست/الگو)
        $actions->register('noop', static function (ProcessContext $context): void {
            // بی‌اثر
        });

        // log — ثبت متغیرها در لاگ (🔒 mask کلیدهای حساس)
        $actions->register('log', static function (ProcessContext $context): void {
            Log::info('bpms.action.log', [
                'instance' => $context->instance()->id(),
                'key' => $context->instance()->definitionKey(),
            ]);
        });

        // mark_done — مهر پایانی در متغیرها (نمایش/گزارش)
        $actions->register('mark_done', static function (ProcessContext $context): void {
            $context->set('service_done', true);
        });

        // notify_initiator — اطلاع به آغازگر (serviceTask عمومی دمو/سرویس)
        $actions->register('notify_initiator', static function (ProcessContext $context): void {
            $instance = $context->instance();
            Event::dispatch('bpms.service.notify_initiator', [
                'instance_id' => $instance->id(),
                'definition_key' => $instance->definitionKey(),
                'initiator_id' => $instance->initiatorId(),
                'business_key' => $instance->businessKey(),
            ]);
        });
    }

    private function registerLang(string $moduleRoot): void
    {
        if (!$this->app->bound('lang')) {
            return;
        }
        $translator = $this->app->make('lang');
        if (\is_object($translator) && \method_exists($translator, 'addNamespace')) {
            $translator->addNamespace('bpms', $moduleRoot . '/lang');
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

    /** سیم‌کشی رویدادها (DOC-098 §10). */
    private function registerListeners(): void
    {
        if (!$this->app->bound('events')) {
            return;
        }

        $app = $this->app;

        Event::listen('bpms.process.started', static function (string $event, mixed $payload) use ($app): void {
            $app->make(BpmsNotifier::class)->onProcessStarted($event, $payload);
        });
        Event::listen('bpms.task.created', static function (string $event, mixed $payload) use ($app): void {
            $app->make(BpmsNotifier::class)->onTaskCreated($event, $payload);
            $app->make(TaskBroadcaster::class)->onTaskCreated($event, $payload);
        });
        Event::listen('bpms.task.completed', static function (string $event, mixed $payload) use ($app): void {
            $app->make(TaskBroadcaster::class)->onTaskCompleted($event, $payload);
        });
        Event::listen('bpms.process.completed', static function (string $event, mixed $payload) use ($app): void {
            $app->make(BpmsNotifier::class)->onProcessCompleted($event, $payload);
            $app->make(TaskBroadcaster::class)->onProcessCompleted($event, $payload);
        });
        Event::listen('bpms.task.escalated', static function (string $event, mixed $payload) use ($app): void {
            $app->make(BpmsNotifier::class)->onTaskEscalated($event, $payload);
            $app->make(TaskBroadcaster::class)->onTaskEscalated($event, $payload);
        });
    }

    /** ثبت cron تایمرها (§9 — در حالت testing گارد می‌شود؛ الگوی crm). */
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
            $event = $schedule->command('bpms:check-timers');
            if (\is_object($event) && \method_exists($event, 'everyMinute')) {
                $event->everyMinute();
            }
        }
    }

    /**
     * ادغام نام رویدادهای مدیریتی BPMS در allowlist موتور audit
     * (idempotent — الگوی ERP؛ بدون تغییر config سراسری در ریپو).
     */
    private function extendAuditEventAllowlist(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        $config = $this->app->make('config');
        $existing = (array) $config->get('audit.events', []);

        $bpmsEvents = [
            'definition.created', 'definition.updated', 'definition.published',
            'definition.activated', 'definition.retired',
            'instance.terminated', 'instance.suspended', 'instance.resumed',
            'task.force_completed', 'task.assigned',
        ];

        $merged = \array_values(\array_unique(\array_merge($existing, $bpmsEvents)));
        $config->set('audit.events', $merged);
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
