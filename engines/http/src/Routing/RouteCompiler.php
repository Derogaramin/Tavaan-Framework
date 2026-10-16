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
    /** @var array<string, string> memo برای normalize (اشتراک رشته‌های کلید تکراری) */
    private array $lowerMemo = [];

    public function __construct(private readonly bool $caseSensitive = false)
    {
    }

    public function compile(RouteCollection $routes): TrieNode
    {
        $root = new TrieNode();
        $this->lowerMemo = [];

        try {
            foreach ($routes->all() as $route) {
                $this->insert($root, $route);
            }
        } finally {
            $this->lowerMemo = [];   // memo فقط طول compile — جلوگیری از نگهداشت حافظه
        }

        return $root;
    }

    private function insert(TrieNode $root, Route $route): void
    {
        $node = $root;

        foreach ($route->rawSegments() as $segment) {
            if (\is_string($segment)) {
                $node = $node->staticChild($this->normalize($segment));
                continue;
            }

            $name = $segment->name;
            $regex = $segment->regex ?? '[^/]+';
            $this->assertSafeRegex($name, $regex);

            if ($segment->wildcard) {
                $child = $node->wildcardNode ?? new TrieNode();
                $node->wildcardName = $name;
                $node->wildcardRegex = $regex;
                $node->wildcardNode = $child;
                $node = $child;
                break;   // wildcard بقیه را می‌بلعد
            }

            $existing = null;
            foreach ($node->params() as $edge) {
                if ($edge->name === $name && $edge->regex === $regex) {
                    $existing = $edge;
                    break;
                }
            }

            if ($existing !== null) {
                $node = $existing->node;
                continue;
            }

            $child = new TrieNode();
            $node->addParam(new ParamEdge(
                name: $name,
                regex: $regex,
                node: $child,
                specificity: $segment->regex !== null ? 100 : 10,
                order: $route->order(),
            ));
            $node = $child;
        }

        $specificity = $route->specificity();
        $order = $route->order();
        foreach ($route->methods() as $method) {
            $node->addHandler(new HandlerEntry($method, $route, $specificity, $order));
        }
    }

    private function normalize(string $segment): string
    {
        if ($this->caseSensitive) {
            return $segment;
        }

        // memo: کلیدهای تکراری (مثل page ×۴۰۰) یک zend_string مشترک می‌شوند
        return $this->lowerMemo[$segment] ??= \strtolower($segment);
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
