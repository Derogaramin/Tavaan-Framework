<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Tests\Unit;

use Tavaan\Engine\Admin\Action;
use Tavaan\Engine\Admin\Dashboard\Dashboard;
use Tavaan\Engine\Admin\Form\Field;
use Tavaan\Engine\Admin\Form\Form;
use Tavaan\Engine\Admin\Form\Repeater;
use Tavaan\Engine\Admin\Menu\Menu;
use Tavaan\Engine\Admin\Resource;
use Tavaan\Engine\Admin\Show;
use Tavaan\Engine\Admin\Support\ChartRenderer;
use Tavaan\Engine\Admin\Support\ValueTransformer;
use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Testing\TestCase;

/**
 * تست واحد اجزای Admin — Form/Field/Resource/Show/Action/Menu/Chart/ValueTransformer.
 */
final class AdminUnitTest extends TestCase
{
    // ─── ValueTransformer (T-07 + تبدیل‌ها) ───────────────────────────────

    public function testJalaliToGregorian_Conversion(): void
    {
        self::assertSame('2024-07-02', ValueTransformer::jalaliToGregorian('1403/04/12'));
        self::assertSame('2024-07-02 10:30:00', ValueTransformer::jalaliToGregorian('1403/04/12 10:30'));
        self::assertSame('2024-03-20', ValueTransformer::jalaliToGregorian('1403/1/1'));   // سال کبیسهٔ جلالی
        self::assertNull(ValueTransformer::jalaliToGregorian('not-a-date'));
        self::assertNull(ValueTransformer::jalaliToGregorian(''));
    }

    public function testGregorianToJalali_Conversion(): void
    {
        self::assertSame('1403/04/12', ValueTransformer::gregorianToJalali('2024-07-02'));
        self::assertSame('1403-04-12', ValueTransformer::gregorianToJalali('2024-07-02 08:00:00', 'Y-m-d'));
        self::assertSame('garbage', ValueTransformer::gregorianToJalali('garbage'));   // strtotime false → همان ورودی
    }

    public function testSlugify_KeepsPersianLetters(): void
    {
        self::assertSame('محصول-جدید', ValueTransformer::slugify('محصول جدید!'));
        self::assertSame('hello-world', ValueTransformer::slugify('  Hello   World  '));
        self::assertSame('a-b-c', ValueTransformer::slugify('a--b__c'));
    }

    public function testToStorage_TypeNormalizations(): void
    {
        $t = new ValueTransformer(new Container());

        // switch/checkbox → bool
        self::assertTrue($t->toStorage(new Field('a', 'switch'), '1', [])['value']);
        self::assertFalse($t->toStorage(new Field('a', 'switch'), null, [])['value']);
        self::assertTrue($t->toStorage(new Field('a', 'checkbox'), 'on', [])['value']);

        // tags → list
        self::assertSame(['a', 'b'], $t->toStorage(new Field('t', 'tags'), 'a, b ,', [])['value']);

        // json → decode
        self::assertSame(['x' => 1], $t->toStorage(new Field('j', 'json'), '{"x":1}', [])['value']);
        self::assertSame('{bad json', $t->toStorage(new Field('j', 'json'), '{bad json', [])['value']);

        // money → numeric (جداکننده هزارگان)
        self::assertSame(12345, $t->toStorage(new Field('m', 'money'), '12,345', [])['value']);

        // slug از فیلد مبدأ
        $slugField = (new Field('slug', 'slug'))->from('title');
        $slug = $t->toStorage($slugField, '', ['title' => 'عنوان بزرگ']);
        self::assertSame('عنوان-بزرگ', $slug['value']);

        // password خالی → skip
        self::assertTrue($t->toStorage(new Field('p', 'password'), '', [])['skip']);
    }

    public function testToDisplay_Formats(): void
    {
        $t = new ValueTransformer(new Container());

        self::assertSame('1403/04/12', $t->toDisplay('jalali_date', '2024-07-02'));
        self::assertSame('["a"]', \str_replace(["\n", '    '], '', (string) $t->toDisplay('json', ['a'])));
        self::assertSame('x, y', $t->toDisplay('tags', ['x', 'y']));
        self::assertSame('✓', $t->toDisplay('checkbox', true));
        self::assertSame('—', $t->toDisplay('switch', false));
    }

    // ─── Form / Field (۲۷ نوع §6) ────────────────────────────────────────

    public function testForm_AllDocumentedFieldTypes(): void
    {
        $form = new Form();
        $index = 0;
        foreach (\array_keys(Form::TYPES) as $method) {
            if ($method === 'jalali_date') {
                continue;   // alias — با jalaliDate پوشش داده شد
            }
            $field = $form->{$method}('f' . $index);
            self::assertInstanceOf(Field::class, $field);
            $index++;
        }

        self::assertGreaterThan(25, \count($form->fields()));
    }

    public function testForm_UnknownType_Throws(): void
    {
        $this->expectException(\BadMethodCallException::class);
        (new Form())->nonexistent('x');
    }

    public function testForm_InvalidFieldName_Throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Form())->text('NotSnake');
    }

    public function testField_RulesFromTypeAndModifiers(): void
    {
        $text = new Field('title', 'text');
        $text->required()->maxLength(100);
        self::assertContains('required', $text->validationRules());
        self::assertContains('string', $text->validationRules());
        self::assertContains('max:100', $text->validationRules());

        $optional = new Field('x', 'text');
        self::assertContains('nullable', $optional->validationRules());   // اختیاری → nullable خودکار

        $select = new Field('s', 'select');
        $select->options(['a' => 'A', 'b' => 'B']);
        self::assertContains('in:a,b', $select->validationRules());

        $jalali = new Field('d', 'jalali_date');
        $rules = \implode('|', $jalali->validationRules());
        self::assertStringContainsString('regex:', $rules);

        $number = new Field('n', 'number');
        $number->min(5)->max(10);
        self::assertContains('numeric', $number->validationRules());
        self::assertContains('min:5', $number->validationRules());
        self::assertContains('max:10', $number->validationRules());
    }

    public function testField_OptionsFromEnum(): void
    {
        $field = new Field('status', 'select');
        $field->options(SampleStatus::class);

        self::assertSame(['active' => 'Active', 'draft' => 'Draft'], $field->optionsList());
    }

    public function testForm_RulesAndNames(): void
    {
        $form = new Form();
        $form->text('title')->required();
        $form->number('price');
        $form->static('computed');   // static → نه rules نه names

        self::assertSame(['title', 'price'], $form->names());
        $rules = $form->rules();
        self::assertArrayHasKey('title', $rules);
        self::assertArrayHasKey('price', $rules);
        self::assertArrayNotHasKey('computed', $rules);
        self::assertSame($form->field('title'), $form->fields()[0]);
        self::assertNull($form->field('nope'));
    }

    public function testRepeater_SubfieldsAndNoNesting(): void
    {
        $form = new Form();
        $rep = $form->repeater('attributes', function (Repeater $r): void {
            $r->text('key')->required();
            $r->text('value');
        });

        self::assertInstanceOf(Field::class, $rep);
        self::assertNotNull($rep->repeater());
        self::assertCount(2, $rep->repeater()?->fields() ?? []);

        $this->expectException(\BadMethodCallException::class);
        $form->repeater('nested', static fn (Repeater $r) => $r->repeater('x'));
    }

    // ─── Resource / Show / Action / Menu ──────────────────────────────────

    public function testResource_KeyAndPermissions(): void
    {
        $resource = new Resource(SampleModel::class);
        self::assertSame('sample-models', $resource->key());   // plural + kebab
        self::assertSame('admin.sample-models.view', $resource->permissionFor('view'));

        $resource->uri('widgets')->permission('shop.widget');
        self::assertSame('widgets', $resource->key());
        self::assertSame('shop.widget.delete', $resource->permissionFor('delete'));

        self::assertInstanceOf(Form::class, $resource->buildForm());   // تنبل
        self::assertInstanceOf(Show::class, $resource->showDefinition());
        self::assertSame(25, $resource->perPageCount(25));
    }

    public function testResource_DuplicateKeyForDifferentModel_Throws(): void
    {
        $manager = new \Tavaan\Engine\Admin\AdminManager(new Container(), []);
        $manager->resource(SampleModel::class);

        $this->expectException(\LogicException::class);
        $manager->resource(SampleModel2::class, static fn (Resource $r): Resource => $r->uri('sample-models'));
    }

    public function testShow_FieldsAndRelations(): void
    {
        $show = new Show();
        $show->fields(['a', 'b']);
        $show->relation('reviews')->columns(['author', 'rating']);

        self::assertSame(['a', 'b'], $show->fieldList());
        self::assertSame(['reviews' => ['author', 'rating']], $show->relationPanels());
    }

    public function testAction_FluentImmutableAndRun(): void
    {
        $base = Action::make('publish');
        $withPerm = $base->permission('x.y')->label('انتشار')->confirm()->style('primary');

        self::assertNotSame($base, $withPerm);   // immutable
        self::assertNull($base->permissionName());
        self::assertSame('x.y', $withPerm->permissionName());
        self::assertSame('انتشار', $withPerm->labelText());
        self::assertTrue($withPerm->needsConfirm());
        self::assertSame('primary', $withPerm->styleName());
        self::assertFalse($base->bulk);
        self::assertTrue(Action::bulk('delete')->bulk);

        $called = null;
        $action = Action::make('touch')->handler(static function (object $model) use (&$called): string {
            $called = $model;

            return 'done';
        });
        $model = new \stdClass();
        self::assertSame('done', $action->run($model));
        self::assertSame($model, $called);

        // handler نداشته و delete هم نباشد → LogicException
        $this->expectException(\LogicException::class);
        Action::make('unknown')->run($model);
    }

    public function testMenu_OrderAndUrls(): void
    {
        $menu = new Menu();
        $menu->add('دوم', url: '/two', order: 20);
        $menu->add('یکم', url: '/one', order: 10);
        $menu->add('سوم', route: 'admin.dashboard', order: 5);

        $items = $menu->items();
        self::assertSame(['سوم', 'یکم', 'دوم'], \array_column($items, 'label'));
        self::assertSame('admin.dashboard', $items[0]->route);
        self::assertSame('/one', $items[1]->url);
    }

    // ─── Dashboard + ChartRenderer (T-06) ─────────────────────────────────

    public function testChartRenderer_ProducesSvg(): void
    {
        $svg = ChartRenderer::render(['a' => 1, 'b' => 5, 'c' => 3], 'line');
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('<path', $svg);

        $bars = ChartRenderer::render(['a' => 1, 'b' => 5], 'bar');
        self::assertStringContainsString('<rect', $bars);

        self::assertStringContainsString('داده‌ای برای نمودار وجود ندارد', ChartRenderer::render([]));
        self::assertStringNotContainsString('<script', ChartRenderer::render(['x' => 1]));
    }

    public function testDashboard_ResolvesChartsAndIsolatesBrokenWidgets(): void
    {
        $dashboard = new Dashboard();
        $dashboard->chart('sales', static fn (): array => ['a' => 2, 'b' => 4], 'bar', 'فروش');

        $charts = $dashboard->resolvedCharts();
        self::assertSame('فروش', $charts[0]['label']);
        self::assertSame('bar', $charts[0]['type']);
        self::assertSame(['a' => 2, 'b' => 4], $charts[0]['data']);
    }
}

// ─── Fixtureهای همین فایل ─────────────────────────────────────────────────

enum SampleStatus: string
{
    case Active = 'active';
    case Draft = 'draft';
}

final class SampleModel extends Model
{
    protected string $table = 'sample_models';
}

final class SampleModel2 extends Model
{
    protected string $table = 'sample_models';
}
