<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Queue\Exceptions\QueueException;
use Tavaan\Engine\Queue\Exceptions\UnsafeJobException;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Queue
 * Serializer — سریال‌سازی **فقط JSON** + Allowlist کلاس (DOC-061 §7).
 *
 * 🔒 امنیت:
 * - هرگز `unserialize()` با allowed_classes باز استفاده نمی‌شود.
 * - بازسازی کلاس فقط پس از بررسی allowlist (پیشوند/کلاس صریح).
 * - مدل‌های ORM فقط با «کلاس + کلید» ذخیره می‌شوند (نه کل آبجکت).
 * - Closure/Resource → استثنا (قابل صف‌رفتن نیستند).
 *
 * قالب payload:
 * ```json
 * {"uuid":"…","displayName":"…","job":"Tavaan\\Engine\\Queue\\Jobs\\CallQueuedHandler",
 *  "maxTries":3,"timeout":60,"backoff":10,"retryUntil":null,"rateLimit":null,
 *  "batchId":null,"chain":null,"data":{"commandName":"…","command":{…}}}
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class Serializer
{
    /** کلید marker برای مقادیر ویژه در JSON */
    private const M_CLASS = '@class';

    private const M_MODEL = '@model';

    private const M_KEY = '@key';

    private const M_DATETIME = '@datetime';

    private const M_TZ = '@tz';

    /** @var list<string> پیشوندهای مجاز (مثل `App\`) */
    private array $prefixes;

    /** @var list<class-string> کلاس‌های مجاز صریح */
    private array $classes;

    /**
     * @param array{prefixes?: list<string>, classes?: list<string>} $allowlist
     */
    public function __construct(
        private readonly ?ContainerInterface $container = null,
        array $allowlist = [],
    ) {
        $this->prefixes = $allowlist['prefixes'] ?? ['App\\', 'Tavaan\\', 'Modules\\', 'Kits\\', 'Plugins\\'];
        $this->classes = $allowlist['classes'] ?? [];
    }

    /** افزودن کلاس مجاز در زمان اجرا (برای تست/کیت‌ها). */
    public function allow(string $class): void
    {
        if (\str_ends_with($class, '\\')) {
            $this->prefixes[] = $class;

            return;
        }
        $this->classes[] = $class;
    }

    // ─── payload ──────────────────────────────────────────────────────────

    /**
     * ساخت payload کامل JSON برای یک job.
     *
     * @param object|string        $job  شیء job یا class-string
     * @param array<string, mixed> $data آرگومان‌های سازنده (وقتی $job رشته است)
     */
    public function createJobPayload(object|string $job, array $data = [], string $queue = 'default'): string
    {
        if (\is_string($job)) {
            $this->assertAllowed($job);

            return $this->encode([
                'uuid' => (string) Ulid::generate(),
                'displayName' => $job,
                'job' => Jobs\CallQueuedHandler::class,
                'queue' => $queue,
                'maxTries' => null,
                'timeout' => null,
                'backoff' => null,
                'retryUntil' => null,
                'rateLimit' => null,
                'batchId' => null,
                'chain' => null,
                'createdAt' => Clock::timestamp(),
                'data' => ['commandName' => $job, 'command' => $this->prepare($data)],
            ]);
        }

        $class = $job::class;
        $this->assertAllowed($class);

        $retryUntil = null;
        if (\method_exists($job, 'retryUntil')) {
            $until = $job->retryUntil();
            $retryUntil = $until instanceof \DateTimeInterface ? $until->getTimestamp() : null;
        }

        return $this->encode([
            'uuid' => (string) Ulid::generate(),
            'displayName' => $class,
            'job' => Jobs\CallQueuedHandler::class,
            'queue' => $queue,
            'maxTries' => $this->intProp($job, 'tries'),
            'timeout' => $this->intProp($job, 'timeout'),
            'backoff' => $this->backoffProp($job),
            'retryUntil' => $retryUntil,
            'rateLimit' => $this->rateLimitProp($job),
            'batchId' => null,
            'chain' => $this->chainProp($job, $queue),
            'createdAt' => Clock::timestamp(),
            'data' => ['commandName' => $class, 'command' => $this->serializeCommand($job)],
        ]);
    }

    /** payload برای رویداد صف‌روند (Contracts\Events\ShouldQueue). */
    public function createEventPayload(object $event, mixed $payload = [], string $queue = 'default'): string
    {
        $class = $event::class;
        $this->assertAllowed($class);

        return $this->encode([
            'uuid' => (string) Ulid::generate(),
            'displayName' => 'event:' . $class,
            'job' => Jobs\CallQueuedEvent::class,
            'queue' => $queue,
            'maxTries' => $this->intProp($event, 'tries'),
            'timeout' => $this->intProp($event, 'timeout'),
            'backoff' => null,
            'retryUntil' => null,
            'rateLimit' => null,
            'batchId' => null,
            'chain' => null,
            'createdAt' => Clock::timestamp(),
            'data' => [
                'eventName' => $class,
                'event' => $this->serializeCommand($event),
                'payload' => $this->prepare($payload),
            ],
        ]);
    }

    /**
     * بازسازی payload از JSON — 🔒 با بررسی allowlist.
     *
     * @return array<string, mixed>
     */
    public function decodePayload(string $payload): array
    {
        $data = \json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw QueueException::for('queue.invalid_payload', [], 'payload باید یک JSON object باشد.');
        }

        $wrapper = (string) ($data['job'] ?? '');
        if ($wrapper !== '' && !\in_array($wrapper, [Jobs\CallQueuedHandler::class, Jobs\CallQueuedEvent::class], true)) {
            // wrapper داخلی هم باید allowlist شود (جلوگیری از اجرای کلاس دلخواه)
            $this->assertAllowed($wrapper);
        }

        $command = (string) ($data['data']['commandName'] ?? $data['data']['eventName'] ?? '');
        if ($command !== '') {
            $this->assertAllowed($command);
        }

        return $data;
    }

    /** بازسازی شیء دستور (job/event) از بخش data. */
    public function restoreCommand(array $data): object
    {
        $class = (string) ($data['commandName'] ?? $data['eventName'] ?? '');
        $this->assertAllowed($class);

        $props = $this->restore($data['command'] ?? []);

        return $this->reconstruct($class, $props);
    }

    /** بازسازی payload رویداد. */
    public function restoreEventPayload(array $data): mixed
    {
        return $this->restore($data['payload'] ?? []);
    }

    // ─── prepare / restore بازگشتی ────────────────────────────────────────

    /**
     * تبدیل مقدار به شکل JSON-امن (بازگشتی).
     *
     * @return mixed
     */
    public function prepare(mixed $value): mixed
    {
        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \Closure) {
            throw QueueException::unserializable('Closure');
        }

        if (\is_resource($value)) {
            throw QueueException::unserializable('resource');
        }

        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->prepare($v);
            }

            return $out;
        }

        // مدل ORM → فقط کلاس + کلید (DOC-061 §7)
        if ($this->isModel($value)) {
            /** @var \Tavaan\Engine\Database\ORM\Model $value */
            return [self::M_MODEL => $value::class, self::M_KEY => $value->getKey()];
        }

        if ($value instanceof \DateTimeInterface) {
            return [
                self::M_DATETIME => $value->format('Y-m-d\TH:i:s.uP'),
                self::M_TZ => $value->getTimezone()->getName(),
            ];
        }

        if ($value instanceof \UnitEnum) {
            return [self::M_CLASS => $value::class, 'name' => $value->name];
        }

        if (\is_object($value)) {
            $class = $value::class;
            $this->assertAllowed($class);

            // SerializesModels trait → __serialize
            if (\method_exists($value, '__serialize')) {
                return [self::M_CLASS => $class, 'props' => $this->prepare($value->__serialize())];
            }

            $props = [];
            foreach ((new \ReflectionObject($value))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic() || !$prop->isInitialized($value)) {
                    continue;
                }
                $props[$prop->getName()] = $this->prepare($prop->getValue($value));
            }

            return [self::M_CLASS => $class, 'props' => $props];
        }

        throw QueueException::unserializable(\get_debug_type($value));
    }

    /**
     * بازگردانی مقدار prepare‌شده.
     *
     * @return mixed
     */
    public function restore(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (isset($value[self::M_DATETIME])) {
            return new \DateTimeImmutable((string) $value[self::M_DATETIME]);
        }

        if (isset($value[self::M_MODEL])) {
            $class = (string) $value[self::M_MODEL];
            $this->assertAllowed($class);

            return $this->restoreModel($class, $value[self::M_KEY] ?? null);
        }

        if (isset($value[self::M_CLASS])) {
            $class = (string) $value[self::M_CLASS];
            $this->assertAllowed($class);

            if (\is_a($class, \UnitEnum::class, true)) {
                return \constant($class . '::' . (string) ($value['name'] ?? ''));
            }

            $props = $this->restore($value['props'] ?? []);

            return $this->reconstruct($class, \is_array($props) ? $props : []);
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $this->restore($v);
        }

        return $out;
    }

    /** سریال‌سازی شیء job/event به props (با پشتیبانی __serialize). */
    public function serializeCommand(object $command): array
    {
        if (\method_exists($command, '__serialize')) {
            $props = $command->__serialize();
        } else {
            $props = [];
            foreach ((new \ReflectionObject($command))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic() || !$prop->isInitialized($command)) {
                    continue;
                }
                $props[$prop->getName()] = $prop->getValue($command);
            }
        }

        // metadata صف به payload منتقل شده — در props دستور تکرار نشود
        unset($props['chained']);

        $prepared = $this->prepare($props);

        return \is_array($prepared) ? $prepared : [];
    }

    /**
     * زنجیرهٔ declare‌شده روی job (trait Queueable::chain) → payloadهای آماده.
     *
     * @return list<string>|null
     */
    private function chainProp(object $job, string $queue): ?array
    {
        if (!\property_exists($job, 'chained')) {
            return null;
        }

        $chained = $job->chained ?? [];
        if (!\is_array($chained) || $chained === []) {
            return null;
        }

        $payloads = [];
        foreach ($chained as $member) {
            if ($member instanceof \Closure) {
                throw QueueException::unserializable('Closure در chain');
            }
            $payloads[] = $this->createJobPayload($member, [], $queue);
        }

        return $payloads;
    }

    // ─── Allowlist ────────────────────────────────────────────────────────

    /** 🔒 بررسی allowlist — در غیر این صورت UnsafeJobException. */
    public function assertAllowed(string $class): void
    {
        if ($class === '' || !\class_exists($class) && !\interface_exists($class) && !\enum_exists($class)) {
            throw new UnsafeJobException($class, 'کلاس یافت نشد؛ payload ممکن است دستکاری‌شده باشد.');
        }

        if (\in_array($class, $this->classes, true)) {
            return;
        }

        foreach ($this->prefixes as $prefix) {
            if (\str_starts_with($class, $prefix)) {
                return;
            }
        }

        throw new UnsafeJobException($class);
    }

    public function isAllowed(string $class): bool
    {
        try {
            $this->assertAllowed($class);

            return true;
        } catch (UnsafeJobException) {
            return false;
        }
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * بازسازی شیء: آرگومان‌های سازنده (named) از props؛ باقی props عمومی
     * پس از ساخت ست می‌شوند.
     *
     * @param array<string, mixed> $props
     */
    private function reconstruct(string $class, array $props): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        $ctorArgs = [];
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if (\array_key_exists($param->getName(), $props)) {
                    $ctorArgs[$param->getName()] = $props[$param->getName()];
                }
            }
        }

        $instance = $this->container !== null
            ? $this->container->make($class, $ctorArgs)
            : ($ctorArgs === [] ? $reflection->newInstance() : $reflection->newInstanceArgs($ctorArgs));

        // props باقی‌مانده (غیر readonly) روی نمونه ست می‌شوند
        foreach ($props as $name => $value) {
            if (\array_key_exists($name, $ctorArgs) || !$reflection->hasProperty((string) $name)) {
                continue;
            }
            $prop = $reflection->getProperty((string) $name);
            if ($prop->isStatic() || $prop->isReadOnly() || !$prop->isPublic()) {
                continue;
            }
            $prop->setValue($instance, $value);
        }

        return $instance;
    }

    private function restoreModel(string $class, mixed $key): ?object
    {
        if (!$this->isModelClass($class)) {
            return null;
        }

        if ($key === null) {
            return null;
        }

        /** @var class-string<\Tavaan\Engine\Database\ORM\Model> $class */
        return $class::find($key);
    }

    private function isModel(object $value): bool
    {
        return \class_exists(\Tavaan\Engine\Database\ORM\Model::class)
            && $value instanceof \Tavaan\Engine\Database\ORM\Model;
    }

    private function isModelClass(string $class): bool
    {
        return \class_exists(\Tavaan\Engine\Database\ORM\Model::class)
            && \is_a($class, \Tavaan\Engine\Database\ORM\Model::class, true);
    }

    private function intProp(object $job, string $prop): ?int
    {
        if (!\property_exists($job, $prop)) {
            return null;
        }
        $value = $job->{$prop} ?? null;

        return \is_int($value) ? $value : null;
    }

    /** @return int|list<int>|null */
    private function backoffProp(object $job): int|array|null
    {
        if (!\property_exists($job, 'backoff')) {
            return null;
        }
        $value = $job->backoff ?? null;

        return \is_int($value) || \is_array($value) ? $value : null;
    }

    /** @return array{key: string, max: int, seconds: int}|null */
    private function rateLimitProp(object $job): ?array
    {
        if (!\property_exists($job, 'rateLimit')) {
            return null;
        }
        $value = $job->rateLimit ?? null;

        return \is_array($value) && isset($value['key']) ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        return \json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
