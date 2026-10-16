<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Authorization;

/**
 * تَوان | Tavaan — Contracts
 * GateInterface — قرارداد دروازهٔ کنترل دسترسی (DOC-014 §2.6).
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
interface GateInterface
{
    /** @param callable|class-string $callback */
    public function define(string $ability, callable|string $callback): static;

    /** @param class-string $model @param class-string|object $policy */
    public function policy(string $model, string|object $policy): static;

    public function before(callable $callback): static;

    public function after(callable $callback): static;

    public function allows(string $ability, mixed ...$arguments): bool;

    public function denies(string $ability, mixed ...$arguments): bool;

    /** @param iterable<string>|string $abilities */
    public function check(iterable|string $abilities, mixed ...$arguments): bool;

    /** @param iterable<string>|string $abilities */
    public function any(iterable|string $abilities, mixed ...$arguments): bool;

    /**
     * @throws \Tavaan\Core\Exceptions\Authorization\AuthorizationException در صورت عدم مجوز (۴۰۳)
     */
    public function authorize(string $ability, mixed ...$arguments): ResponseInterface;

    public function forUser(mixed $user): static;

    public function getPolicyFor(object|string $class): ?object;

    /** @return array<string, callable|class-string> */
    public function abilities(): array;
}
