<?php

declare(strict_types=1);

/*
 * فهرست ServiceProviderها — ترتیب = ترتیب register (DOC-027 §5: providers.php → list).
 */

return [
    \Tavaan\Engine\Container\ContainerServiceProvider::class,
    \Tavaan\Engine\Config\ConfigServiceProvider::class,
    \Tavaan\Engine\Log\LogServiceProvider::class,
    \Tavaan\Engine\Event\EventServiceProvider::class,
    \Tavaan\Engine\Error\ErrorServiceProvider::class,
    \Tavaan\Engine\Pipeline\PipelineServiceProvider::class,
    \Tavaan\Engine\Compat\CompatServiceProvider::class,
    \Tavaan\Engine\Module\ModuleServiceProvider::class,
    \Tavaan\Engine\Optimizer\OptimizerServiceProvider::class,
    \Tavaan\Engine\Console\ConsoleServiceProvider::class,
    \Tavaan\Engine\Crypt\CryptServiceProvider::class,
    \Tavaan\Engine\Http\HttpServiceProvider::class,
    \Tavaan\Engine\I18n\I18nServiceProvider::class,
    \Tavaan\Engine\Session\SessionServiceProvider::class,
    \Tavaan\Engine\View\ViewServiceProvider::class,
    \Tavaan\Engine\Database\DatabaseServiceProvider::class,
    \Tavaan\Engine\Database\Schema\SchemaServiceProvider::class,
    \Tavaan\Engine\Database\Migration\MigrationServiceProvider::class,
    \Tavaan\Engine\Validation\ValidationServiceProvider::class,
    \Tavaan\Engine\Database\ORM\OrmServiceProvider::class,
    \Tavaan\Engine\Hash\HashServiceProvider::class,
    \Tavaan\Engine\Cache\CacheServiceProvider::class,
    \Tavaan\Engine\Auth\AuthServiceProvider::class,
    \Tavaan\Engine\Rbac\RbacServiceProvider::class,
    \Tavaan\Engine\Security\SecurityServiceProvider::class,
    \Tavaan\Engine\Audit\AuditServiceProvider::class,
    \Tavaan\Engine\Queue\QueueServiceProvider::class,
    \Tavaan\Engine\Schedule\ScheduleServiceProvider::class,
    \Tavaan\Engine\Filesystem\FilesystemServiceProvider::class,
    \Tavaan\Engine\HttpClient\HttpClientServiceProvider::class,
    \Tavaan\Engine\Mail\MailServiceProvider::class,
    \Tavaan\Engine\Notification\NotificationServiceProvider::class,
    \Tavaan\Engine\Media\MediaServiceProvider::class,
    \Tavaan\Engine\Backup\BackupServiceProvider::class,
    \Tavaan\Engine\Observability\ObservabilityServiceProvider::class,
    \Tavaan\Engine\Search\SearchServiceProvider::class,
    \Tavaan\Engine\Api\ApiServiceProvider::class,
    \Tavaan\Engine\Tenancy\TenancyServiceProvider::class,
    \Tavaan\Engine\FeatureFlag\FeatureFlagServiceProvider::class,
    \Tavaan\Engine\Realtime\RealtimeServiceProvider::class,
    \Tavaan\Engine\Workflow\WorkflowServiceProvider::class,
    \Tavaan\Engine\Report\ReportServiceProvider::class,
    \Tavaan\Engine\Payment\PaymentServiceProvider::class,
];
