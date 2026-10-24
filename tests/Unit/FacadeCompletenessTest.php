<?php

declare(strict_types=1);

namespace Tavaan\Tests\Unit;

use Tavaan\Core\Facade\Facades\Cookie;
use Tavaan\Core\Facade\Facades\Crypt;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Engine\Http\Cookie\Cookie as CookieValue;
use Tavaan\Testing\TestCase;

/**
 * Facadeهای مکشوف‌شده در ممیزی M0–M8 (یافتهٔ H1):
 * Crypt/Lang/Cookie در module.json و DOC-042/054/069 وعده داده شده بودند
 * ولی کلاس نداشتند — اکنون موجود و متصل‌اند.
 */
final class FacadeCompletenessTest extends TestCase
{
    protected function setUp(): void
    {
        \tavaan_test_app();
    }

    public function testCryptFacadeRoundTrip(): void
    {
        $cipher = Crypt::encrypt('سلام ممیزی');
        $this->assertNotSame('سلام ممیزی', $cipher);
        $this->assertSame('سلام ممیزی', Crypt::decrypt($cipher));

        $payload = Crypt::encryptJson(['user' => 42]);
        $this->assertSame(['user' => 42], Crypt::decryptJson($payload));
    }

    public function testLangFacadeResolvesTranslator(): void
    {
        // کلید موجود در resources/lang/fa/validation.php
        $this->assertTrue(Lang::has('validation.required'));
        $text = Lang::get('validation.required', ['attribute' => 'email']);
        $this->assertNotSame('validation.required', $text);
        $this->assertStringNotContainsString(':attribute', $text);
    }

    public function testCookieFacadeBuildsJar(): void
    {
        $cookie = Cookie::make('audit', '1', 60);
        $this->assertInstanceOf(CookieValue::class, $cookie);
        $this->assertSame('audit', $cookie->name());

        Cookie::queue(Cookie::forever('remember', 'x'));
        $this->assertTrue(Cookie::hasQueued('remember'));
    }
}
