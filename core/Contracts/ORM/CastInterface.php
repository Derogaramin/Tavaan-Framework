<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\ORM;

/**
 * تَوان | Tavaan — Contracts
 * CastInterface — قرارداد Cast سفارشی (DOC-050 §5.1).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
interface CastInterface
{
    /**
     * مقدار خام DB → مقدار دامنه.
     *
     * @param object                $model نمونهٔ Model
     * @param array<string, mixed>  $attrs همهٔ صفات خام
     */
    public function get(object $model, string $key, mixed $value, array $attrs): mixed;

    /**
     * مقدار دامنه → مقدار قابل ذخیره در DB.
     *
     * @param object               $model نمونهٔ Model
     * @param array<string, mixed> $attrs همهٔ صفات خام
     */
    public function set(object $model, string $key, mixed $value, array $attrs): mixed;
}
