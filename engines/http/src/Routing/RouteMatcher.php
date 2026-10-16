<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

use Tavaan\Core\Exceptions\Http\MethodNotAllowedException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;

/**
 * تَوان | Tavaan — Routing
 * RouteMatcher — تطبیق مسیر روی Compiled Trie (DOC-040 §5.3).
 *
 * ترتیب اولویت: Static → Parametric (constrained اول) → Wildcard.
 * انتخاب نهایی با specificity score؛ تساوی → زودتر ثبت‌شده (قطعیت).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteMatcher
{
    private TrieNode $root;

    public function __construct(
        private readonly RouteCompiler $compiler = new RouteCompiler(),
        private readonly bool $caseSensitive = false,
    ) {
        $this->root = new TrieNode();
    }

    public function setRoutes(RouteCollection $routes): void
    {
        $this->root = $this->compiler->compile($routes);
    }

    /**
     * تطبیق درخواست.
     *
     * @throws RouteNotFoundException
     * @throws MethodNotAllowedException با Allow header
     */
    public function match(string $method, string $path, string $host = ''): RouteMatch
    {
        $method = \strtoupper($method);
        $segments = $this->splitPath($path);

        $results = [];
        $allowedMethods = [];
        $this->walk($this->root, $segments, 0, [], $results, $allowedMethods, $host, $method);

        if ($results === []) {
            if ($allowedMethods !== []) {
                \sort($allowedMethods);
                throw new MethodNotAllowedException(
                    messageId: 'http.method_not_allowed',
                    context: ['method' => $method, 'path' => $path, 'allowed' => $allowedMethods],
                    headers: ['Allow' => \implode(', ', $allowedMethods)],
                );
            }

            throw RouteNotFoundException::forPath($method, $path);
        }

        // بهترین: specificity DESC، سپس order ASC (قطعیت)
        \usort($results, static fn (array $a, array $b): int
            => $b['specificity'] <=> $a['specificity'] ?: $a['order'] <=> $b['order']);

        $best = $results[0];

        return new RouteMatch($best['route'], $best['params']);
    }

    /**
     * @param list<string>                      $segments
     * @param array<string, string>             $params
     * @param list<array{route: Route, params: array<string, string>, specificity: int, order: int}> $results
     * @param list<string>                      $allowedMethods
     */
    private function walk(TrieNode $node, array $segments, int $i, array $params, array &$results, array &$allowedMethods, string $host, string $method): void
    {
        if ($i === \count($segments)) {
            $this->collectHandlers($node, $params, $results, $allowedMethods, $host, $method);

            return;
        }

        $seg = $segments[$i];
        $key = $this->caseSensitive ? $seg : \strtolower($seg);

        // 1) Static — بالاترین اولویت
        $child = $node->lookupStatic($key);
        if ($child !== null) {
            $this->walk($child, $segments, $i + 1, $params, $results, $allowedMethods, $host, $method);
        }

        // 2) Parametric (مرتب: constrained اول)
        $edge = $node->p1;
        if ($edge !== null) {
            if (\preg_match('#^' . $edge->regex . '$#u', $seg) === 1) {
                $this->walk($edge->node, $segments, $i + 1, [...$params, $edge->name => $seg], $results, $allowedMethods, $host, $method);
            }
            if ($node->pMore !== null) {
                foreach ($node->pMore as $edge) {
                    if (\preg_match('#^' . $edge->regex . '$#u', $seg) === 1) {
                        $this->walk($edge->node, $segments, $i + 1, [...$params, $edge->name => $seg], $results, $allowedMethods, $host, $method);
                    }
                }
            }
        }

        // 3) Wildcard — بقیهٔ مسیر را می‌بلعد
        if ($node->wildcardNode !== null) {
            $rest = \implode('/', \array_slice($segments, $i));
            if (\preg_match('#^' . $node->wildcardRegex . '$#u', $rest) === 1) {
                $this->walk($node->wildcardNode, $segments, \count($segments), [...$params, $node->wildcardName => $rest], $results, $allowedMethods, $host, $method);
            }
        }
    }

    /**
     * @param array<string, string> $params
     * @param list<array{route: Route, params: array<string, string>, specificity: int, order: int}> $results
     * @param list<string>          $allowedMethods
     */
    private function collectHandlers(TrieNode $node, array $params, array &$results, array &$allowedMethods, string $host, string $method): void
    {
        $h1 = $node->h1;
        if ($h1 !== null) {
            $this->considerHandler($h1, $params, $results, $allowedMethods, $host, $method);
            $h2 = $node->h2;
            if ($h2 !== null) {
                $this->considerHandler($h2, $params, $results, $allowedMethods, $host, $method);
                if ($node->hMore !== null) {
                    foreach ($node->hMore as $handler) {
                        $this->considerHandler($handler, $params, $results, $allowedMethods, $host, $method);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, string> $params
     * @param list<array{route: Route, params: array<string, string>, specificity: int, order: int}> $results
     * @param list<string>          $allowedMethods
     */
    private function considerHandler(HandlerEntry $handler, array $params, array &$results, array &$allowedMethods, string $host, string $method): void
    {
        if (!\in_array($handler->method, $allowedMethods, true)) {
            $allowedMethods[] = $handler->method;
        }
        // 🔒 فقط متد درخواست‌شده کاندید است؛ بقیه فقط در Allow گزارش می‌شوند
        if ($handler->method !== $method) {
            return;
        }
        $route = $handler->route;
        if (!$this->domainMatches($route, $host)) {
            return;
        }
        $results[] = [
            'route' => $route,
            'params' => $params,
            'specificity' => $handler->specificity,
            'order' => $handler->order,
        ];
    }

    private function domainMatches(Route $route, string $host): bool
    {
        $domain = $route->getDomain();
        if ($domain === '' || $host === '') {
            return true;
        }

        $host = \strtolower(\preg_replace('/:\d+$/', '', $host) ?? $host);
        $pattern = \strtolower($domain);

        if ($pattern === $host) {
            return true;
        }

        // wildcard: *.example.com یا {tenant}.example.com
        return @\preg_match($this->domainToRegex($pattern), $host) === 1;
    }

    /**
     * ساخت regex دامنه کاراکتر‌به‌کاراکتر (بدون تداخل escape):
     * `{tenant}` → named group؛ `*` → `[^.]+`؛ بقیه quote.
     */
    private function domainToRegex(string $pattern): string
    {
        $regex = '';
        $len = \strlen($pattern);

        for ($i = 0; $i < $len; $i++) {
            $ch = $pattern[$i];

            if ($ch === '{') {
                $close = \strpos($pattern, '}', $i);
                if ($close !== false) {
                    $name = (string) \preg_replace('/[^A-Za-z0-9_]/', '', \substr($pattern, $i + 1, $close - $i - 1));
                    $regex .= $name !== '' ? '(?P<' . $name . '>[^.]+)' : '[^.]+';
                    $i = $close;
                    continue;
                }
            }

            if ($ch === '*') {
                $regex .= '[^.]+';
                continue;
            }

            $regex .= \preg_quote($ch, '#');
        }

        return '#^' . $regex . '$#';
    }

    /** @return list<string> */
    private function splitPath(string $path): array
    {
        $path = \trim($path, '/');
        if ($path === '') {
            return [];
        }

        // normalize: حذف duplicate slash
        $path = (string) \preg_replace('#/+#', '/', $path);

        return \explode('/', \trim($path, '/'));
    }
}
