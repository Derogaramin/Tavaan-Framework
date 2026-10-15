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
     * @param array<string, Module> $modules کلید = شناسهٔ کوتاه (بدون vendor)
     *
     * @return list<Module>
     */
    public function sort(array $modules): array
    {
        $inDegree = [];
        $graph = [];

        foreach ($modules as $id => $module) {
            $inDegree[$id] ??= 0;
            foreach ($module->dependencies() as $dep) {
                if (!isset($modules[$dep])) {
                    continue;   // وابستگی به ماژول ناشناخته/خارجی — نادیده
                }
                $graph[$dep][] = $id;
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
