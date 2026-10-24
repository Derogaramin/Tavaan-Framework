<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module;

use Tavaan\Engine\Module\Exceptions\CircularModuleDependencyException;

/**
 * تَوان | Tavaan — Module
 * TopologicalSorter — مرتب‌سازی Kahn با خروجی قطعی (DOC-038 §5).
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class TopologicalSorter
{
    /**
     * @param array<string, Module> $modules کلید = شناسهٔ کامل (یا کوتاه در تست‌های واحد)
     *
     * @return list<Module>
     */
    public function sort(array $modules): array
    {
        $inDegree = [];
        $graph = [];

        // نگاشت نام کوتاه → کلید: ابتدا کلید دقیق، بعد کوتاه (بخش انتهایی).
        // در برخورد کوتاه‌ها (مثلاً موتور media و ماژول tavaan.media) اولویت با
        // `tavaan.engine.*` است — requires.engines باید به موتور برسد، نه ماژول هم‌نام.
        $shortToKey = [];
        foreach ($modules as $key => $module) {
            $parts = \explode('.', (string) $key);
            $short = \end($parts) ?: (string) $key;
            $existing = $shortToKey[$short] ?? null;
            if ($existing === null
                || (\str_starts_with((string) $key, 'tavaan.engine.') && !\str_starts_with($existing, 'tavaan.engine.'))) {
                $shortToKey[$short] = (string) $key;
            }
        }

        foreach ($modules as $id => $module) {
            $inDegree[$id] ??= 0;
            foreach ($module->dependencies() as $dep) {
                $dep = (string) $dep;
                if (isset($modules[$dep])) {
                    $target = $dep;                                  // کلید دقیق (کامل یا کوتاه)
                } elseif (!\str_contains($dep, '.') && isset($shortToKey[$dep])) {
                    $target = $shortToKey[$dep];                     // نام کوتاه → resolve
                } else {
                    continue;   // وابستگی به ماژول ناشناخته/خارجی — نادیده
                }
                if ($target === $id) {
                    continue;   // خودیال (هم‌نامی کوتاه) — نادیده
                }
                $graph[$target][] = $id;
                $inDegree[$id] = ($inDegree[$id] ?? 0) + 1;
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }
        \sort($queue);   // 🔒 قطعیت

        $result = [];
        while ($queue !== []) {
            $node = \array_shift($queue);
            $result[] = $modules[$node];

            foreach ($graph[$node] ?? [] as $next) {
                if (--$inDegree[$next] === 0) {
                    $queue[] = $next;
                    \sort($queue);   // 🔒 حفظ ترتیب قطعی
                }
            }
        }

        if (\count($result) !== \count($modules)) {
            $done = [];
            foreach ($result as $m) {
                $done[$m->id()] = true;
            }
            $cycle = [];
            foreach ($modules as $m) {
                if (!isset($done[$m->id()])) {
                    $cycle[] = $m->id();
                }
            }
            throw CircularModuleDependencyException::for($cycle);
        }

        return $result;
    }
}
