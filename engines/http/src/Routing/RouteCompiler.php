<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

use Tavaan\Engine\Http\Routing\Exceptions\RouteCompilationException;

/**
 * تَوان | Tavaan — Routing
 * RouteCompiler — ساخت Compiled Trie از RouteCollection (DOC-040 §5).
 *
 * 🔒 ReDoS guard: طول regex ≤ ۲۰۰ و بدون quantifier تودرتو.
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteCompiler
{
    public function __construct(private readonly bool $caseSensitive = false)
    {
    }

    public function compile(RouteCollection $routes): TrieNode
    {
        $root = new TrieNode();

        foreach ($routes->all() as $route) {
            $this->insert($root, $route);
        }

        return $root;
    }

    private function insert(TrieNode $root, Route $route): void
    {
        $node = $root;
        $segments = $route->segments();

        foreach ($segments as $segment) {
            if (isset($segment['static'])) {
                $key = $this->normalize($segment['static']);
                $node = $node->static[$key] ??= new TrieNode();
                continue;
            }

            /** @var string $name */
            $name = $segment['name'];
            $regex = $segment['regex'] ?? '[^/]+';
            $this->assertSafeRegex($name, $regex);

            if (($segment['wildcard'] ?? false) === true) {
                $child = $node->wildcard['node'] ?? new TrieNode();
                $node->wildcard = ['name' => $name, 'regex' => $regex, 'node' => $child];
                $node = $child;
                break;   // wildcard بقیه را می‌بلعد
            }

            $existing = null;
            foreach ($node->params as $i => $edge) {
                if ($edge['name'] === $name && $edge['regex'] === $regex) {
                    $existing = $i;
                    break;
                }
            }

            if ($existing !== null) {
                $node = $node->params[$existing]['node'];
                continue;
            }

            $child = new TrieNode();
            $node->params[] = [
                'edge' => $name,
                'name' => $name,
                'regex' => $regex,
                'node' => $child,
                'specificity' => ($segment['regex'] ?? null) !== null ? 100 : 10,
                'order' => $route->order(),
            ];
            // مرتب‌سازی: constrained (specificity بالا) اول؛ ترتیب قطعی
            \usort($node->params, static fn (array $a, array $b): int
                => $b['specificity'] <=> $a['specificity'] ?: $a['order'] <=> $b['order']);
            $node = $child;
        }

        foreach ($route->methods() as $method) {
            $node->handlers[$method][] = [
                'route' => $route,
                'specificity' => $route->specificity(),
                'order' => $route->order(),
            ];
            \usort($node->handlers[$method], static fn (array $a, array $b): int
                => $b['specificity'] <=> $a['specificity'] ?: $a['order'] <=> $b['order']);
        }
    }

    private function normalize(string $segment): string
    {
        return $this->caseSensitive ? $segment : \strtolower($segment);
    }

    /**
     * 🔒 ReDoS guard (DOC-040 §11).
     */
    private function assertSafeRegex(string $param, string $regex): void
    {
        if (\strlen($regex) > 200) {
            throw RouteCompilationException::regexTooLong($param);
        }
        // 🔍 forms like (a+)+ / (\d+)* / ([a-z]+)+ / (.*)* → classic ReDoS.
        //    safe shapes like (?:-[a-z0-9]+)* (literal anchor inside) are allowed.
        if (\preg_match('/\((?:\?[:<>=!])?\s*(?:\\\\[wWdDsS]|\[[^\]]*\]|\.|[^\[\]()|*+?])\s*[+*]\s*\)[+*]|\{\d+,\}?\s*\)[+*]/', $regex) === 1
            || \preg_match('/\((?:\?[:<>=!])?[^()]*[+*]\)[+*]\)/', $regex) === 1) {
            throw RouteCompilationException::potentialRedos($regex);
        }
        // اعتبارسنجی خود regex
        if (@\preg_match('#^' . $regex . '$#u', '') === false) {
            throw RouteCompilationException::potentialRedos($regex);
        }
    }
}
