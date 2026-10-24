<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * InventoryException — خطاهای موجودی (Fail Explainable — DOC-013).
 */

namespace Modules\Shop\Domain\Services;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * @api 1.0
 */
final class InventoryException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function outOfStock(string $productId, string $title, int $requested, int $available, array $context = []): self
    {
        return new self(
            messageId: 'shop::messages.out_of_stock',
            context: ['product_id' => $productId, 'title' => $title, 'requested' => $requested, 'available' => $available, ...$context],
            hint: 'موجودی کافی نیست؛ quantity را کم کنید یا موجودی را افزایش دهید.',
        );
    }
}
