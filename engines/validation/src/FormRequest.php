<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\Http\Request;

/**
 * تَوان | Tavaan — Validation
 * FormRequest — درخواست خود-اعتبارسنج (DOC-051 §5).
 *
 * چرخهٔ زندگی (در Controller Resolution):
 * ```
 * 1. createFrom(request جاری)
 * 2. prepareForValidation()
 * 3. authorize() → false = AuthorizationException (403)
 * 4. Validator::make(all(), rules(), messages(), attributes())
 * 5. fail → ValidationException (422)
 * 6. validated() → DTO
 * 7. passedValidation()
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
abstract class FormRequest extends Request
{
    /** @var array<string, mixed>|null */
    private ?array $validatedData = null;

    private ?Validator $validatorInstance = null;

    /** ساخت از درخواست جاری (توسط ControllerResolver فراخوانی می‌شود). */
    public static function createFrom(Request $request): static
    {
        /** @var static $instance */
        $instance = self::fromRequestInto(static::class, $request);

        return $instance;
    }

    /** authorization — پیش‌فرض: مجاز. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد اعتبارسنجی.
     *
     * @return array<string, string|list<mixed>>
     */
    abstract public function rules(): array;

    /** @return array<string, string> */
    public function messages(): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [];
    }

    /** نرمال‌سازی داده قبل از اعتبارسنجی (merge/replace). */
    protected function prepareForValidation(): void
    {
    }

    /** پس از موفقیت اعتبارسنجی. */
    protected function passedValidation(): void
    {
    }

    /** قلاب افزودن قاعدهٔ پویا به Validator. */
    public function withValidator(Validator $validator): void
    {
    }

    /** اجرای کامل چرخهٔ اعتبارسنجی. */
    public function validateResolved(): void
    {
        $this->prepareForValidation();

        if (!$this->authorize()) {
            throw new AuthorizationException();
        }

        $factory = Facade::getFacadeApplication()->make('validator');
        \assert($factory instanceof ValidatorFactory);

        $validator = $factory->make($this->all(), $this->rules(), $this->messages(), $this->attributes());
        $this->withValidator($validator);
        $this->validatorInstance = $validator;

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->toArray());
        }

        $this->validatedData = $validator->validated();
        $this->passedValidation();
    }

    /**
     * دادهٔ معتبرشده (فقط فیلدهای قاعده‌دار).
     *
     * @return mixed
     */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->validatedData ??= [];

        if ($key === null) {
            return $data;
        }

        return \Tavaan\Support\Arr::get($data, $key, $default);
    }

    public function validator(): ?Validator
    {
        return $this->validatorInstance;
    }

    /** ادغام ورودی (برای prepareForValidation). */
    public function merge(array $input): void
    {
        foreach ($input as $key => $value) {
            $this->postBagMutable()->set((string) $key, $value);
        }
    }

    /** جایگزینی کامل ورودی. */
    public function replace(array $input): void
    {
        $this->postBagMutable()->replace($input);
    }

    private function postBagMutable(): \Tavaan\Engine\Http\ParameterBag
    {
        return $this->postBag();
    }

    /** کاربر جاری (در صورت حضور موتور auth — M4). */
    public function user(): ?Authenticatable
    {
        try {
            if (\class_exists(\Tavaan\Core\Facade\Facades\Auth::class)) {
                $user = \Tavaan\Core\Facade\Facades\Auth::user();

                return $user instanceof Authenticatable ? $user : null;
            }
        } catch (\Throwable) {
            // auth در دسترس نیست
        }

        return null;
    }
}
