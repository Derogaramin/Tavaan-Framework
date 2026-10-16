<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Validation\RuleInterface;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Validation\MessageBag;
use Tavaan\Engine\Validation\Testing\CreateUserDto;
use Tavaan\Engine\Validation\Testing\StoreOrderFormRequest;
use Tavaan\Engine\Validation\Rules\Rule;
use Tavaan\Engine\Validation\ValidatorFactory;
use Tavaan\Testing\TestCase;

/**
 * تست Feature ولیدیشن — DOC-051 §11 (T-04..T-12).
 */
final class ValidatorFeatureTest extends TestCase
{
    private Application $app;

    private ValidatorFactory $factory;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);

        // جدول users برای قواعد دیتابیس
        $this->app->make('db')->connection()->statement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, name TEXT)',
        );
        $this->app->make('db')->connection()->table('users')->insert([
            ['email' => 'taken@x.io', 'name' => 'Taken'],
        ]);

        $this->factory = $this->app->make('validator');
    }

    public function testWildcardRules(): void
    {
        // T-05
        $v = $this->factory->make(
            data: ['items' => [
                ['product_id' => 1, 'qty' => 5],
                ['product_id' => 2, 'qty' => 0],
                ['product_id' => 3, 'qty' => 'x'],
            ]],
            rules: [
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer'],
                'items.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
            ],
        );

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('items.1.qty'));
        $this->assertTrue($v->errors()->has('items.2.qty'));
        $this->assertFalse($v->errors()->has('items.0.qty'));
    }

    public function testWildcardWithExists(): void
    {
        $v = $this->factory->make(
            data: ['items' => [['product_id' => 1]]],
            rules: ['items.*.product_id' => ['required', 'exists:users,id']],
        );
        $this->assertTrue($v->passes());

        $v2 = $this->factory->make(
            data: ['items' => [['product_id' => 999]]],
            rules: ['items.*.product_id' => ['required', 'exists:users,id']],
        );
        $this->assertTrue($v2->fails());
    }

    public function testUniqueWithIgnore(): void
    {
        // T-04
        $v = $this->factory->make(['email' => 'taken@x.io'], ['email' => 'required|email|unique:users,email']);
        $this->assertTrue($v->fails());

        // ignore رکورد فعلی (ویرایش)
        $v2 = $this->factory->make(['email' => 'taken@x.io'], ['email' => 'required|email|unique:users,email,1,id']);
        $this->assertTrue($v2->passes());

        // Rule object
        $v3 = $this->factory->make(['email' => 'taken@x.io'], ['email' => ['required', Rule::unique('users', 'email')]]);
        $this->assertTrue($v3->fails());
    }

    public function testExistsRule(): void
    {
        $v = $this->factory->make(['email' => 'taken@x.io'], ['email' => 'exists:users,email']);
        $this->assertTrue($v->passes());

        $v2 = $this->factory->make(['email' => 'nope@x.io'], ['email' => 'exists:users,email']);
        $this->assertTrue($v2->fails());
    }

    public function testDbIdentifierInjectionRejected(): void
    {
        // 🔒 نام جدول/ستون با allowlist — injection رد می‌شود
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->make(['a' => 'x'], ['a' => 'unique:users; DROP TABLE users--'])->passes();
    }

    public function testCustomMessagesAndAttributes(): void
    {
        // T-07
        $v = $this->factory->make(
            data: ['email' => 'taken@x.io', 'age' => 5],
            rules: ['email' => 'unique:users,email', 'age' => 'min:18'],
            messages: ['email.unique' => 'این ایمیل قبلاً ثبت شده است.'],
            attributes: ['email' => 'ایمیل', 'age' => 'سن'],
        );

        $this->assertTrue($v->fails());
        $this->assertSame('این ایمیل قبلاً ثبت شده است.', $v->errors()->first('email'));
        $this->assertStringContainsString('سن', $v->errors()->first('age'));
    }

    public function testValidatedOnlyRuledFields(): void
    {
        // T-08
        $v = $this->factory->make(
            data: ['email' => 'a@b.cd', 'password' => 'x', 'admin' => 1],
            rules: ['email' => 'required|email', 'password' => 'required|string'],
        );

        $this->assertTrue($v->passes());
        $validated = $v->validated();
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayHasKey('password', $validated);
        $this->assertArrayNotHasKey('admin', $validated);   // 🔒 Mass Validation Bypass
    }

    public function testValidateThrowsValidationException(): void
    {
        try {
            $this->factory->validate(['email' => 'bad'], ['email' => 'required|email']);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertArrayHasKey('email', $e->errors);
        }
    }

    public function testCustomRuleObject(): void
    {
        // T-09
        $rule = new class implements RuleInterface {
            public function passes(string $attribute, mixed $value): bool
            {
                return \is_string($value) && \str_starts_with($value, 'tvn-');
            }

            public function message(): string
            {
                return 'validation.custom_prefix';
            }

            public function implicit(): bool
            {
                return false;
            }
        };

        $v = $this->factory->make(['sku' => 'tvn-123'], ['sku' => ['required', $rule]]);
        $this->assertTrue($v->passes());

        $v2 = $this->factory->make(['sku' => 'abc'], ['sku' => ['required', $rule]]);
        $this->assertTrue($v2->fails());
        $this->assertStringContainsString('validation.custom_prefix', $v2->errors()->first('sku'));
    }

    public function testExtendCustomRule(): void
    {
        $this->factory->extend('uppercase', static fn (string $attr, mixed $value): bool => \is_string($value) && $value === \strtoupper($value), 'validation.uppercase');

        $v = $this->factory->make(['code' => 'ABC'], ['code' => 'uppercase']);
        $this->assertTrue($v->passes());

        $v2 = $this->factory->make(['code' => 'abc'], ['code' => 'uppercase']);
        $this->assertTrue($v2->fails());
    }

    public function testTranslationsFaAndEn(): void
    {
        // T-12
        $fa = $this->factory->make(['email' => 'bad'], ['email' => 'required|email']);
        $fa->fails();
        $this->assertStringContainsString('ایمیل معتبر', $fa->errors()->first('email'));

        $translator = $this->app->make('translator');
        $translator->setLocale('en');
        $en = $this->factory->make(['email' => 'bad'], ['email' => 'required|email']);
        $en->fails();
        $this->assertStringContainsString('valid email', $en->errors()->first('email'));
        $translator->setLocale('fa');
    }

    public function testMessageBagApi(): void
    {
        $bag = new MessageBag(['email' => ['خطای اول', 'خطای دوم'], 'age' => ['خطای سن']]);

        $this->assertTrue($bag->has('email'));
        $this->assertFalse($bag->has('nope'));
        $this->assertSame('خطای اول', $bag->first('email'));
        $this->assertCount(3, $bag->all());
        $this->assertSame(['email', 'age'], $bag->keys());
        $this->assertStringContainsString('email', $bag->toJson());
    }

    public function testReDoSRegexRejected(): void
    {
        // T-11: nested quantifier → رد
        try {
            $this->factory->make(['a' => 'aaaaaaaaaaaaaaaaX'], ['a' => 'regex:/^(a+)+$/'])->passes();
            self::fail('nested quantifier must be rejected');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ReDoS', $e->getMessage());
        }

        // الگوی خیلی بلند → رد
        $long = '/^' . \str_repeat('a', 600) . '$/';
        try {
            $this->factory->make(['a' => 'x'], ['a' => 'regex:' . $long])->passes();
            self::fail('long pattern must be rejected');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('too long', $e->getMessage());
        }
    }

    public function testFormRequestLifecycle(): void
    {
        // T-06
        $request = Request::create('/orders', 'POST', ['items' => [], 'note' => 'x']);

        $form = StoreOrderFormRequest::createFrom($request);

        // authorize fail → 403
        StoreOrderFormRequest::$authorizeResult = false;
        try {
            $form->validateResolved();
            self::fail('expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame('authorization.denied', $e->messageId);   // → 403 توسط ExceptionHandler
        }

        // authorize pass + validation fail → 422
        StoreOrderFormRequest::$authorizeResult = true;
        try {
            $form->validateResolved();
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertArrayHasKey('items', $e->errors);
        }

        // دادهٔ معتبر → validated
        $ok = Request::create('/orders', 'POST', [
            'items' => [['product_id' => 1, 'qty' => 2]],
            'extra' => 'ignored',
        ]);
        $form2 = StoreOrderFormRequest::createFrom($ok);
        $form2->validateResolved();

        $validated = $form2->validated();
        $this->assertArrayHasKey('items', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
        // prepareForValidation merge انجام شد (بدون خطا)
    }

    public function testDtoAttributeValidation(): void
    {
        // T-10
        $ok = $this->factory->forDto(CreateUserDto::class, ['email' => 'a@b.cd', 'age' => 20, 'mobile' => '09121234567']);
        $this->assertFalse($ok->fails());

        $bad = $this->factory->forDto(CreateUserDto::class, ['email' => 'nope', 'age' => 5, 'mobile' => '123']);
        $this->assertTrue($bad->fails());
        $this->assertTrue($bad->errors()->has('email'));
        $this->assertTrue($bad->errors()->has('age'));
        $this->assertTrue($bad->errors()->has('mobile'));
    }

    public function testExcludeIf(): void
    {
        $v = $this->factory->make(
            ['type' => 'free', 'price' => 100],
            ['type' => 'required|in:free,paid', 'price' => 'exclude_if:type,free|integer'],
        );
        $this->assertTrue($v->passes());
        $this->assertArrayNotHasKey('price', $v->validated());
    }
}
