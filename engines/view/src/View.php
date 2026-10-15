<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

/**
 * تَوان | Tavaan — View
 * View — یک قالب + داده؛ render با پشتیبانی @extends تودرتو (حداکثر ۱۰ سطح).
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class View implements \Stringable
{
    private const MAX_LAYOUT_DEPTH = 10;

    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly ViewFactory $factory,
        private readonly string $template,
        array $data = [],
    ) {
        $this->data = $data;
    }

    /** @param array<string, mixed>|string $key */
    public function with(array|string $key, mixed $value = null): self
    {
        if (\is_array($key)) {
            $this->data = [...$this->data, ...$key];

            return $this;
        }

        $this->data[$key] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getData(?string $key = null): array
    {
        return $this->data;
    }

    public function name(): string
    {
        return $this->template;
    }

    /**
     * رندر کامل (با چرخهٔ layoutها).
     */
    public function render(): string
    {
        $content = $this->factory->evaluateTemplate($this->template, $this->data);

        $depth = 0;
        while (($parent = $this->factory->popExtends()) !== null) {
            if (++$depth > self::MAX_LAYOUT_DEPTH) {
                throw Exceptions\ViewException::maxDepth($parent, self::MAX_LAYOUT_DEPTH);
            }

            $content = $this->factory->evaluateTemplate($parent, [...$this->data, '__content' => $content]);
        }

        return $content;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
