<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Testing;

use Tavaan\Engine\Validation\FormRequest;

/**
 * تَوان | Tavaan — Validation
 * StoreOrderFormRequest — Fixture تست FormRequest (DOC-051 §5).
 *
 * @api internal
 * @see docs/03-engines/validation.md
 */
final class StoreOrderFormRequest extends FormRequest
{
    public static bool $authorizeResult = true;

    public function authorize(): bool
    {
        return self::$authorizeResult;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'سبد خرید خالی است.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => 'prepared']);
    }
}
