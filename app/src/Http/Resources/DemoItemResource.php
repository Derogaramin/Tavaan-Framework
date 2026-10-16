<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Tavaan\Engine\Api\Resources\JsonResource;
use Tavaan\Engine\Http\Request;

/**
 * DemoItemResource — Resource نمونهٔ لایهٔ API (DOC-044 §2).
 *
 * خروجی: `{"data": {"id":…, "title":…, "links": {…}}}`
 * فیلد `secret_note` فقط برای درخواست‌های دارای کاربر ظاهر می‌شود (when).
 *
 * @api internal (نمونهٔ توسعه)
 */
final class DemoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'title' => (string) $this->resource['title'],
            // 🔒 فیلد حساس فقط برای کاربر احراز‌شده (DOC-044 §9 Over-fetching)
            'secret_note' => $this->when(
                $request->attribute('user') !== null,
                fn (): string => 'internal-' . $this->resource['id'],
            ),
            'links' => ['self' => '/api/v1/items/' . $this->resource['id']],
        ];
    }
}
