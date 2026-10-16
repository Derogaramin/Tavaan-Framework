<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit;

use Tavaan\Core\Contracts\ORM\ObserverInterface;
use Tavaan\Engine\Database\ORM\Model;

/**
 * تَوان | Tavaan — Audit
 * AuditObserver — ممیزی خودکار رویدادهای مدل (DOC-058 §4).
 *
 * ثبت با `Model::observe(new AuditObserver($auditor))` یا از طریق
 * Attribute `#[Observed(fields: [...])]` برای فیلتر فیلدها.
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class AuditObserver implements ObserverInterface
{
    /** @var \Closure(): ?int */
    private readonly \Closure $userIdResolver;

    /** @var \Closure(): ?string */
    private readonly \Closure $ipResolver;

    public function __construct(
        private readonly Auditor $auditor,
        ?\Closure $userIdResolver = null,
        ?\Closure $ipResolver = null,
    ) {
        $this->userIdResolver = $userIdResolver ?? static function (): ?int {
            try {
                if (\class_exists(\Tavaan\Core\Facade\Facades\Auth::class)
                    && \Tavaan\Core\Facade\Facade::getFacadeApplication()->bound('auth')) {
                    $id = \Tavaan\Core\Facade\Facades\Auth::id();

                    return $id === null ? null : (int) $id;
                }
            } catch (\Throwable) {
                // auth در دسترس نیست
            }

            return null;
        };

        $this->ipResolver = $ipResolver ?? static function (): ?string {
            try {
                if (\Tavaan\Core\Facade\Facade::getFacadeApplication()->bound(\Tavaan\Core\Context\RequestStack::class)) {
                    $request = \Tavaan\Core\Facade\Facade::getFacadeApplication()
                        ->make(\Tavaan\Core\Context\RequestStack::class)->current();
                    if ($request !== null && \method_exists($request, 'ip')) {
                        return $request->ip();
                    }
                }
            } catch (\Throwable) {
                // request در دسترس نیست
            }

            return null;
        };
    }

    public function created(Model $model): void
    {
        $this->auditor->record($this->event(
            'created',
            $model,
            [],
            $this->relevant($model, $model->getChanges() !== [] ? $model->getChanges() : $this->rawAttributes($model)),
        ));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if ($changes === []) {
            return;
        }

        // 🔒 مقادیر پیشین (پیش از syncOriginal) — برای diff دقیق
        $original = \method_exists($model, 'getPreviousOriginal') && $model->getPreviousOriginal() !== []
            ? $model->getPreviousOriginal()
            : $model->getOriginal();
        $old = [];
        foreach (\array_keys($changes) as $key) {
            $old[(string) $key] = $original[(string) $key] ?? null;
        }

        $this->auditor->record($this->event('updated', $model, $this->relevant($model, $old), $this->relevant($model, $changes)));
    }

    public function deleted(Model $model): void
    {
        $this->auditor->record($this->event('deleted', $model, $this->relevant($model, $this->rawAttributes($model)), []));
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function event(string $name, Model $model, array $old, array $new): AuditEvent
    {
        return new AuditEvent(
            $name,
            $model::class,
            (string) ($model->getKey() ?? ''),
            ($this->userIdResolver)(),
            oldValues: $old,
            newValues: $new,
            ip: ($this->ipResolver)(),
        );
    }

    /**
     * فیلتر فیلدها بر اساس #[Observed] (در صورت وجود).
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function relevant(Model $model, array $values): array
    {
        $attributes = (new \ReflectionClass($model::class))->getAttributes(Observed::class);
        if ($attributes === []) {
            return $values;
        }

        /** @var Observed $observed */
        $observed = $attributes[0]->newInstance();
        $fields = $observed->fields;
        if ($fields === []) {
            return $values;
        }

        return \array_intersect_key($values, \array_flip($fields));
    }

    /** @return array<string, mixed> */
    private function rawAttributes(Model $model): array
    {
        $array = $model->toArray();

        return \is_array($array) ? $array : [];
    }
}
