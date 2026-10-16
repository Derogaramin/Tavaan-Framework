<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Container;

/**
 * قرارداد کانتینر DI (DOC-014 §2.1).
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * ثبت یک binding (Transient به‌طور پیش‌فرض).
     *
     * @param \Closure|class-string|null $concrete
     */
    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void;

    /** ثبت Singleton (یک نمونه برای کل فرایند). */
    public function singleton(string $abstract, \Closure|string|null $concrete = null): void;

    /** ثبت Scoped (یک نمونه به ازای هر Scope فعال: Request/Job/Tenant). */
    public function scoped(string $abstract, \Closure|string|null $concrete = null): void;

    /** ثبت Factory (هر make = فراخوانی closure). */
    public function factory(string $abstract, \Closure $factory): void;

    /** ثبت نمونهٔ آماده. */
    public function instance(string $abstract, object $instance): object;

    /** نام مستعار برای abstract. */
    public function alias(string $abstract, string $alias): void;

    /** برچسب‌گذاری گروهی. @param list<string>|string $abstracts */
    public function tag(array|string $abstracts, string ...$tags): void;

    /** Resolve تنبل همهٔ اعضای یک تگ. @return iterable<object> */
    public function tagged(string $tag): iterable;

    public function bound(string $abstract): bool;

    public function resolved(string $abstract): bool;

    /**
     * ساخت/بازیابی سرویس.
     *
     * @template T
     * @param class-string<T>|string $abstract
     * @param array<string, mixed>   $parameters
     *
     * @return T|mixed
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * فراخوانی callable با Method Injection.
     *
     * @param callable|array{0: object|class-string, 1: string}|string $callback
     * @param array<string, mixed>                                     $parameters
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed;

    /** Contextual Binding: when(Consumer)->needs(X)->give(Y). */
    public function when(string $consumer): ContextualBindingBuilder;

    /** Decorator روی نمونهٔ ساخته‌شده. */
    public function extend(string $abstract, \Closure $extender): void;

    /** شروع scope جدید برای سرویس‌های Scoped (هر درخواست — AC-CON-04). */
    public function beginScope(string $id): void;

    public function endScope(string $id): void;

    public function currentScope(): ?string;

    /** پاک‌کردن سرویس‌های Scoped (پایان درخواست در حالت Resident — AC-CON-04). */
    public function flushScoped(): void;

    /** پاک‌کردن همهٔ bindingها/نمونه‌ها (تست). */
    public function flush(): void;
}
