<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessContext — زمینهٔ اجرای اکشن‌های service task/timer (DOC-098 §6).
 */

namespace Modules\Bpms\Application\UseCases;

use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Entities\Task;
use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * ProcessContext — ورودی اکشن‌های ثبت‌شده: نمونه، متغیرها، بازیگر و
 * دسترسی به container برای سرویس‌های ماژول.
 *
 * ```php
 * $actions->register('approveLeave', function (ProcessContext $ctx): void {
 *     $leaveId = (int) $ctx->get('leave_id');
 *     // …
 * });
 * ```
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ProcessContext
{
    /**
     * @param array<string, mixed> $input ورودی همان فراخوانی (موقتی)
     */
    public function __construct(
        private readonly ProcessInstance $instance,
        private readonly ContainerInterface $container,
        private readonly array $input = [],
        private readonly ?Task $task = null,
        private readonly ?int $actorId = null,
    ) {
    }

    public function instance(): ProcessInstance
    {
        return $this->instance;
    }

    public function task(): ?Task
    {
        return $this->task;
    }

    public function actorId(): ?int
    {
        return $this->actorId;
    }

    /** خواندن متغیر (ابتدا ورودی فراخوانی، سپس متغیرهای نمونه). */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $this->instance->getVariable($key, $default);
    }

    /** نوشتن متغیر پایدار نمونه. */
    public function set(string $key, mixed $value): void
    {
        $this->instance->setVariable($key, $value);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [...$this->instance->variables(), ...$this->input];
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /** ساخت سرویس از container (میان‌بر). */
    public function make(string $id): mixed
    {
        return $this->container->make($id);
    }
}
