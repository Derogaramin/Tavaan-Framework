<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n\Tests\Unit;

use Tavaan\Engine\I18n\PersianFormatter;
use Tavaan\Engine\I18n\PluralRules;
use Tavaan\Engine\I18n\Translator;
use Tavaan\Testing\TestCase;

/**
 * تست موتور I18n — DOC-069 §10.
 */
final class I18nTest extends TestCase
{
    private string $langDir;

    protected function setUp(): void
    {
        $this->langDir = \sys_get_temp_dir() . '/tvn-lang-' . \getmypid();
        @\mkdir($this->langDir . '/fa', 0775, true);
        @\mkdir($this->langDir . '/en', 0775, true);
        @\mkdir($this->langDir . '/ar', 0775, true);
        @\mkdir($this->langDir . '/ru', 0775, true);

        \file_put_contents($this->langDir . '/fa/shop.php', '<?php return ["product" => ["title" => "عنوان", "discount" => ":percent٪ تخفیف"], "cart" => ["items" => "{0}سبد خالی|{1}یک قلم|[2,*]:count قلم"]];');
        \file_put_contents($this->langDir . '/en/shop.php', '<?php return ["product" => ["title" => "Title"], "only_en" => "English only"];');
        \file_put_contents($this->langDir . '/ar/shop.php', '<?php return ["cart" => ["items" => "{0}فارغ|{1}واحد|{2}وحدتان|[3,10]:count وحدات|[11,*]:count عنصر"]];');
        \file_put_contents($this->langDir . '/ru/shop.php', '<?php return ["cart" => ["items" => ":count товар|:count товара|:count товаров"]];');
    }

    private function translator(string $locale = 'fa'): Translator
    {
        return new Translator([
            'locale' => $locale,
            'fallback_locale' => 'en',
            'paths' => [$this->langDir],
        ]);
    }

    /** T-01: trans با namespace گروه */
    public function testBasicTranslation(): void
    {
        $t = $this->translator();
        $this->assertSame('عنوان', $t->get('shop.product.title'));
    }

    /** T-02: placeholder replace */
    public function testPlaceholderReplacement(): void
    {
        $t = $this->translator();
        $this->assertSame('۲۰٪ تخفیف', $t->get('shop.product.discount', ['percent' => '۲۰']));
    }

    /** T-03: pluralization fa/en/ar/ru */
    public function testPluralization(): void
    {
        $fa = $this->translator('fa');
        $this->assertSame('سبد خالی', $fa->choice('shop.cart.items', 0));
        $this->assertSame('یک قلم', $fa->choice('shop.cart.items', 1));
        $this->assertSame('5 قلم', $fa->choice('shop.cart.items', 5));

        $ar = $this->translator('ar');
        $this->assertSame('فارغ', $ar->choice('shop.cart.items', 0));
        $this->assertSame('واحد', $ar->choice('shop.cart.items', 1));
        $this->assertSame('وحدتان', $ar->choice('shop.cart.items', 2));
        $this->assertSame('5 وحدات', $ar->choice('shop.cart.items', 5));
        $this->assertSame('11 عنصر', $ar->choice('shop.cart.items', 11));

        $ru = $this->translator('ru');
        $this->assertSame('1 товар', $ru->choice('shop.cart.items', 1));
        $this->assertSame('3 товара', $ru->choice('shop.cart.items', 3));
        $this->assertSame('5 товаров', $ru->choice('shop.cart.items', 5));
        $this->assertSame('21 товар', $ru->choice('shop.cart.items', 21));
    }

    /** T-04: fallback chain */
    public function testFallbackChain(): void
    {
        $t = $this->translator('fa');
        $this->assertSame('English only', $t->get('shop.only_en'));
        // کلید کاملاً مفقود → خود کلید + ثبت در missing
        $this->assertSame('shop.nope', $t->get('shop.nope'));
        $this->assertContains('shop.nope', $t->missingKeys());
    }

    /** fa_IR → fa */
    public function testLocaleVariantFallback(): void
    {
        $t = $this->translator('fa_IR');
        $this->assertSame('عنوان', $t->get('shop.product.title'));
    }

    /** T-05: Accept-Language negotiation */
    public function testNegotiation(): void
    {
        $t = $this->translator();
        $this->assertSame('fa', $t->negotiate('fa-IR,fa;q=0.9,en;q=0.8', ['fa', 'en'], 'en'));
        $this->assertSame('en', $t->negotiate('en-US,en;q=0.9', ['fa', 'en'], 'fa'));
        $this->assertSame('fa', $t->negotiate('de-DE,de;q=0.9', ['fa', 'en'], 'fa'));
        $this->assertSame('en', $t->negotiate('fa;q=0.5,en;q=0.9', ['fa', 'en'], 'fa'));
    }

    /** T-06/T-07: PersianFormatter */
    public function testPersianFormatter(): void
    {
        $this->assertSame('۱۲۳۴۵۶۷', PersianFormatter::digits(1234567));
        $this->assertSame('۱٬۲۳۴٬۵۶۷', PersianFormatter::number(1234567));
        $this->assertSame('۱٬۲۳۴٬۵۶۷٫۸۹', PersianFormatter::number(1234567.891, 2));
        $this->assertSame('1234567', PersianFormatter::toLatinDigits('۱۲۳۴۵۶۷'));
        $this->assertSame('1405', PersianFormatter::toLatinDigits('۱۴۰۵'));
    }

    public function testMinorUnitsMoney(): void
    {
        $this->assertSame('۱٬۰۰۰ ریال', PersianFormatter::minorUnits(1000, 0, 'ریال'));
    }

    /** T-09: RTL */
    public function testRtlDetection(): void
    {
        $this->assertTrue($this->translator('fa')->isRtl());
        $this->assertTrue($this->translator('ar')->isRtl());
        $this->assertFalse($this->translator('en')->isRtl());
    }

    /** PluralRules index */
    public function testPluralRuleIndexes(): void
    {
        $rules = new PluralRules();
        $this->assertSame(0, $rules->indexFor(1, 'fa'));
        $this->assertSame(1, $rules->indexFor(2, 'fa'));
        $this->assertSame(2, $rules->indexFor(2, 'ar'));
        $this->assertSame(3, $rules->indexFor(5, 'ar'));
        $this->assertSame(4, $rules->indexFor(15, 'ar'));
        $this->assertSame(0, $rules->indexFor(1, 'ru'));
        $this->assertSame(1, $rules->indexFor(2, 'ru'));
        $this->assertSame(2, $rules->indexFor(5, 'ru'));
    }

    /** namespace module */
    public function testModuleNamespace(): void
    {
        $moduleDir = \sys_get_temp_dir() . '/tvn-lang-mod-' . \getmypid();
        @\mkdir($moduleDir . '/fa', 0775, true);
        \file_put_contents($moduleDir . '/fa/cart.php', '<?php return ["title" => "سبد خرید"];');

        $t = $this->translator();
        $t->addNamespace('shop', $moduleDir);
        $this->assertSame('سبد خرید', $t->get('shop::cart.title'));
    }
}
