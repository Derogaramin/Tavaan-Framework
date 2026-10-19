<?php

declare(strict_types=1);

namespace Tavaan\Testing\Mock;

/**
 * تَوان | Tavaan — Testing
 * MockBuilder — Builder تعریف انتظارات و ساخت Mock (DOC-084E §4.1).
 *
 * ```php
 * $store = Mock::of(StoreInterface::class)
 *     ->shouldReceive('get')->with('k')->andReturn('v')
 *     ->getMock();
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class MockBuilder
{
    private readonly MockHandler $handler;

    /** @param class-string $target */
    public function __construct(private readonly string $target)
    {
        $this->handler = new MockHandler();
    }

    /**
     * تعریف انتظار برای یک متد (نام متد با Reflection اعتبارسنجی می‌شود).
     */
    public function shouldReceive(string $method): Expectation
    {
        $this->assertMethodExists($method);

        $expectation = new Expectation($method, $this);
        $this->handler->expect($method, $expectation);

        return $expectation;
    }

    /**
     * ساخت Proxy نهایی و ثبت Handler برای verify خودکار پایان تست.
     */
    public function getMock(): object
    {
        $proxyClass = ProxyGenerator::generate($this->target);
        $mock = new $proxyClass($this->handler);
        Mock::register($this->handler);

        return $mock;
    }

    public function handler(): MockHandler
    {
        return $this->handler;
    }

    private function assertMethodExists(string $method): void
    {
        if (!\interface_exists($this->target) && !\class_exists($this->target)) {
            throw new \InvalidArgumentException(\sprintf('Mock target [%s] does not exist.', $this->target));
        }

        if (!\method_exists($this->target, $method)) {
            throw new \InvalidArgumentException(\sprintf(
                'Mock: method [%s] does not exist on [%s].',
                $method,
                $this->target,
            ));
        }
    }
}
