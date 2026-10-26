<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ProcessModel — گراف عناصر تعریف فرایند (DOC-098 §3) — تغییرناپذیر.
 */

namespace Modules\Bpms\Domain\ValueObjects;

use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\Exceptions\BpmsException;

/**
 * ProcessModel — پارس‌شدهٔ بخش `definition` (startEvent + elements + timers).
 * ساخت فقط با `fromArray` (اعتبارسنجی ساختاری عمیق در ModelValidator).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class ProcessModel
{
    /**
     * @param array<string, Element>   $elements  elementId → Element
     * @param list<TimerSpec>          $timers
     * @param array<string, int>       $incoming  elementId → تعداد یال‌های ورودی
     * @param array<string, list<string>> $outgoing elementId → فهرست یال‌های خروجی
     */
    private function __construct(
        public string $startEvent,
        public array $elements,
        public array $timers,
        private array $incoming,
        private array $outgoing,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws BpmsException ساختار نامعتبر (reason=invalid_definition)
     */
    public static function fromArray(array $data): self
    {
        $elements = [];
        foreach ((array) ($data['elements'] ?? []) as $raw) {
            if (!\is_array($raw)) {
                continue;
            }
            $element = Element::fromArray($raw);
            if ($element->id === '') {
                throw BpmsException::invalidDefinition(['عنصر بدون شناسه']);
            }
            if (isset($elements[$element->id])) {
                throw BpmsException::invalidDefinition(['شناسهٔ تکراری عنصر: ' . $element->id]);
            }
            $elements[$element->id] = $element;
        }

        $startEvent = (string) ($data['startEvent'] ?? '');
        if ($startEvent === '') {
            foreach ($elements as $element) {
                if ($element->type === ElementType::StartEvent) {
                    $startEvent = $element->id;
                    break;
                }
            }
        }
        if ($startEvent === '' || !isset($elements[$startEvent])) {
            throw BpmsException::invalidDefinition(['startEvent موجود یا معتبر نیست']);
        }

        $timers = [];
        foreach ((array) ($data['timers'] ?? []) as $raw) {
            if (\is_array($raw)) {
                $timers[] = TimerSpec::fromArray($raw);
            }
        }

        // ایندکس یال‌ها
        $incoming = [];
        $outgoing = [];
        foreach ($elements as $id => $element) {
            $incoming[$id] ??= 0;
            $outgoing[$id] = $element->outgoingTargets();
        }
        foreach ($elements as $element) {
            foreach ($element->outgoingTargets() as $target) {
                if (isset($incoming[$target])) {
                    $incoming[$target]++;
                }
            }
        }

        return new self($startEvent, $elements, $timers, $incoming, $outgoing);
    }

    public function element(string $id): ?Element
    {
        return $this->elements[$id] ?? null;
    }

    /** @throws BpmsException */
    public function elementOrFail(string $id): Element
    {
        return $this->elements[$id] ?? throw BpmsException::unknownElement($id);
    }

    /** @return array<string, Element> */
    public function elements(): array
    {
        return $this->elements;
    }

    public function startElement(): Element
    {
        return $this->elementOrFail($this->startEvent);
    }

    /** @return list<TimerSpec> */
    public function timersFor(string $elementId): array
    {
        return \array_values(\array_filter(
            $this->timers,
            static fn (TimerSpec $t): bool => $t->on === $elementId,
        ));
    }

    public function incomingCount(string $elementId): int
    {
        return $this->incoming[$elementId] ?? 0;
    }

    /** @return list<string> */
    public function outgoingTargets(string $elementId): array
    {
        return $this->outgoing[$elementId] ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $elements = [];
        foreach ($this->elements as $element) {
            $elements[] = $element->toArray();
        }
        $timers = [];
        foreach ($this->timers as $timer) {
            $timers[] = $timer->toArray();
        }

        return \array_filter([
            'startEvent' => $this->startEvent,
            'elements' => $elements,
            'timers' => $timers === [] ? null : $timers,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
