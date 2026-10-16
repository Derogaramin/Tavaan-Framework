<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

/**
 * تَوان | Tavaan — Observability
 * Metric — پایهٔ مشترک سنجه‌ها (نام، help، نمونه‌ها برچسب‌دار).
 *
 * هر سنجه مجموعه‌ای از «نمونه»هاست؛ هر نمونه یک series زمانی متمایز
 * (ترکیب یکتای برچسب‌ها) با مقادیر مخصوص نوع خود.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
abstract class Metric
{
    /** @var array<string, array<string, mixed>> labelKey → نمونه */
    protected array $samples = [];

    /** @var \Closure(): void|null قلاب پایستگی (توسط Registry تنظیم می‌شود) */
    protected ?\Closure $onMutate = null;

    public function __construct(
        public readonly string $name,
        protected string $help = '',
    ) {
    }

    abstract public function type(): string;

    public function help(): string
    {
        return $this->help;
    }

    public function setHelp(string $help): void
    {
        if ($this->help === '') {
            $this->help = $help;
        }
    }

    /** قلاب write-through برای پایستگی (cache). */
    public function setOnMutate(?\Closure $fn): void
    {
        $this->onMutate = $fn;
    }

    /** @return array<string, array<string, mixed>> */
    public function samples(): array
    {
        return $this->samples;
    }

    /** @return array<string, mixed> وضعیت قابل‌ذخیره (پایستگی) */
    public function state(): array
    {
        return ['type' => $this->type(), 'help' => $this->help, 'samples' => $this->samples];
    }

    /** @param array<string, mixed> $state */
    public function hydrate(array $state): void
    {
        if (($state['type'] ?? null) !== $this->type()) {
            return;   // نوع تغییر کرده — وضعیت کهنه نادیده گرفته می‌شود
        }
        $this->samples = (array) ($state['samples'] ?? []);
        if ($this->help === '') {
            $this->help = (string) ($state['help'] ?? '');
        }
    }

    /**
     * کلید یکتای series برای مجموعهٔ برچسب‌ها (ترتیب مستقل).
     *
     * @param array<string, string|int|float|bool> $labels
     */
    protected function labelKey(array $labels): string
    {
        $normalized = $this->normalizeLabels($labels);

        return $normalized === [] ? '' : \json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string|int|float|bool> $labels
     *
     * @return array<string, string>
     */
    protected function normalizeLabels(array $labels): array
    {
        $out = [];
        foreach ($labels as $k => $v) {
            $out[(string) $k] = \is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        \ksort($out);

        return $out;
    }

    protected function mutate(): void
    {
        if ($this->onMutate !== null) {
            ($this->onMutate)();
        }
    }
}
