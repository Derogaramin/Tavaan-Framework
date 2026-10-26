<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Unit;

use Modules\Bpms\Domain\Enums\FieldType;
use Modules\Bpms\Domain\ValueObjects\FormSchema;
use Tavaan\Testing\TestCase;

/**
 * تست واحد FormSchema — فرم‌ساز پویا (DOC-098 §4):
 * پارس + compile به قواعد validation + نرمال‌سازی.
 */
final class FormSchemaTest extends TestCase
{
    /** فرم مرخصی مستند §4. @return array<string, mixed> */
    private function leaveFormJson(): array
    {
        return [
            'id' => 'leave_form',
            'fields' => [
                ['name' => 'leave_type', 'type' => 'select', 'label' => 'نوع مرخصی',
                 'options' => ['استحقاقی', 'استعلاجی', 'بدون حقوق'], 'required' => true],
                ['name' => 'days', 'type' => 'number', 'label' => 'تعداد روز', 'min' => 1, 'max' => 30, 'required' => true],
                ['name' => 'from_date', 'type' => 'jalali_date', 'label' => 'از تاریخ', 'required' => true],
                ['name' => 'to_date', 'type' => 'jalali_date', 'label' => 'تا تاریخ', 'required' => true],
                ['name' => 'reason', 'type' => 'textarea', 'label' => 'دلیل'],
            ],
        ];
    }

    public function testDocumentedFormParses(): void
    {
        $schema = FormSchema::fromArray($this->leaveFormJson());

        self::assertSame('leave_form', $schema->id);
        self::assertCount(5, $schema->fields);

        $days = $schema->field('days');
        self::assertNotNull($days);
        self::assertSame(FieldType::Number, $days->type);
        self::assertSame(1, $days->min);
        self::assertSame(30, $days->max);
        self::assertTrue($days->required);

        $reason = $schema->field('reason');
        self::assertNotNull($reason);
        self::assertFalse($reason->required);
        self::assertSame(FieldType::Textarea, $reason->type);
    }

    public function testValidationRulesCompiledFromSchema(): void
    {
        $rules = FormSchema::fromArray($this->leaveFormJson())->toValidationRules();

        self::assertSame(['required', 'in:استحقاقی,استعلاجی,بدون حقوق'], $rules['leave_type']);
        self::assertSame(['required', 'numeric', 'min:1', 'max:30'], $rules['days']);
        self::assertSame(['required', 'jalali_date'], $rules['from_date']);
        self::assertSame(['required', 'jalali_date'], $rules['to_date']);
        self::assertSame(['nullable', 'string'], $rules['reason']);
    }

    public function testValidationRulesForAllFieldTypes(): void
    {
        $schema = FormSchema::fromArray([
            'id' => 'all_types',
            'fields' => [
                ['name' => 'f_text', 'type' => 'text', 'label' => 't', 'required' => true, 'max' => 100],
                ['name' => 'f_email', 'type' => 'email', 'label' => 'e'],
                ['name' => 'f_mobile', 'type' => 'mobile', 'label' => 'm'],
                ['name' => 'f_check', 'type' => 'checkbox', 'label' => 'c'],
                ['name' => 'f_date', 'type' => 'date', 'label' => 'd'],
                ['name' => 'f_hidden', 'type' => 'hidden', 'label' => 'h'],
            ],
        ]);

        $rules = $schema->toValidationRules();
        self::assertSame(['required', 'string', 'max:100'], $rules['f_text']);
        self::assertSame(['nullable', 'email'], $rules['f_email']);
        self::assertSame(['nullable', 'iran_mobile'], $rules['f_mobile']);
        self::assertSame(['nullable', 'boolean'], $rules['f_check']);
        self::assertSame(['nullable', 'date'], $rules['f_date']);
        self::assertArrayNotHasKey('f_hidden', $rules);   // hidden بدون قاعده
    }

    public function testNormalizeConvertsTypes(): void
    {
        $schema = FormSchema::fromArray($this->leaveFormJson());

        $normalized = $schema->normalize([
            'leave_type' => ' استحقاقی ',
            'days' => '5',
            'from_date' => '1405/08/01',
            'to_date' => '1405/08/05',
            'reason' => "  سفر  \n",
            'extra_evil_key' => 'injected',   // 🔒 کلید خارج از schema حذف می‌شود
        ]);

        self::assertSame('استحقاقی', $normalized['leave_type']);
        self::assertSame(5, $normalized['days']);
        self::assertSame('سفر', $normalized['reason']);
        self::assertArrayNotHasKey('extra_evil_key', $normalized);
    }

    public function testNormalizeCheckboxSemantics(): void
    {
        $schema = FormSchema::fromArray([
            'id' => 'f',
            'fields' => [['name' => 'approved', 'type' => 'checkbox', 'label' => 'تأیید']],
        ]);

        self::assertSame(['approved' => true], $schema->normalize(['approved' => '1']));
        self::assertSame(['approved' => true], $schema->normalize(['approved' => 'on']));
        self::assertSame(['approved' => false], $schema->normalize(['approved' => '0']));
        // checkbox ارسال‌نشده = خاموش
        self::assertSame(['approved' => false], $schema->normalize([]));
    }

    public function testInvalidFieldDefinitionsRejected(): void
    {
        // نام فیلد نامعتبر
        try {
            FormSchema::fromArray(['id' => 'f', 'fields' => [['name' => 'bad-name', 'type' => 'text', 'label' => 'x']]]);
            self::fail('باید خطا می‌داد');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }

        // نوع ناشناخته
        try {
            FormSchema::fromArray(['id' => 'f', 'fields' => [['name' => 'ok', 'type' => 'password_stealer', 'label' => 'x']]]);
            self::fail('باید خطا می‌داد');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }

        // نام تکراری
        try {
            FormSchema::fromArray(['id' => 'f', 'fields' => [
                ['name' => 'x', 'type' => 'text', 'label' => 'a'],
                ['name' => 'x', 'type' => 'text', 'label' => 'b'],
            ]]);
            self::fail('باید خطا می‌داد');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }

        // id خالی
        try {
            FormSchema::fromArray(['id' => '', 'fields' => []]);
            self::fail('باید خطا می‌داد');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function testSelectOptionsWithSeparatorAreFilteredFromRules(): void
    {
        $schema = FormSchema::fromArray([
            'id' => 'f',
            'fields' => [['name' => 'sel', 'type' => 'select', 'label' => 's',
                          'options' => ['a', 'evil,option', 'b']]],
        ]);

        $rules = $schema->toValidationRules();
        self::assertSame(['nullable', 'in:a,b'], $rules['sel']);   // گزینهٔ دارای ',' حذف می‌شود
    }

    public function testToArrayRoundTrip(): void
    {
        $schema = FormSchema::fromArray($this->leaveFormJson());
        $again = FormSchema::fromArray($schema->toArray());

        self::assertSame($schema->id, $again->id);
        self::assertCount(\count($schema->fields), $again->fields);
        self::assertSame($schema->toValidationRules(), $again->toValidationRules());
    }
}
