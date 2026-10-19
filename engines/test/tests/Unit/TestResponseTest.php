<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Unit;

use Tavaan\Engine\Http\Cookie\Cookie;
use Tavaan\Engine\Http\Response;
use Tavaan\Testing\AssertionFailed;
use Tavaan\Testing\Http\TestResponse;
use Tavaan\Testing\TestCase;

/**
 * تست Assertionهای TestResponse (DOC-084E §8).
 */
final class TestResponseTest extends TestCase
{
    // ─── وضعیت ────────────────────────────────────────────────────────────

    public function testStatusAssertions_MatchCodes(): void
    {
        $this->wrap(200)->assertOk()->assertSuccessful();
        $this->wrap(201)->assertCreated()->assertSuccessful();
        $this->wrap(401)->assertUnauthorized();
        $this->wrap(403)->assertForbidden();
        $this->wrap(404)->assertNotFound();
        $this->wrap(418)->assertStatus(418);
    }

    public function testAssertStatus_Mismatch_Fails(): void
    {
        $this->assertFailsWith('Expected response status [200] but received [500]', fn () => $this->wrap(500)->assertOk());
    }

    public function testAssertSuccessful_OnRedirect_Fails(): void
    {
        $this->assertFailsWith('Expected successful status', fn () => $this->wrap(302)->assertSuccessful());
    }

    // ─── JSON ─────────────────────────────────────────────────────────────

    public function testAssertJson_MatchesSubsetRecursively(): void
    {
        $response = $this->json(['data' => ['id' => 7, 'title' => 'لپ تاپ', 'meta' => ['stock' => 3, 'x' => 1]]]);

        $response->assertJson(['data' => ['id' => 7]]);
        $response->assertJson(['data' => ['meta' => ['stock' => 3]]]);
        $response->assertJson(['data' => ['title' => 'لپ تاپ']]);
    }

    public function testAssertJson_MissingKey_Fails(): void
    {
        $this->assertFailsWith('does not contain expected subset', fn () => $this->json(['a' => 1])->assertJson(['b' => 1]));
    }

    public function testAssertJson_ValueMismatch_Fails(): void
    {
        $this->assertFailsWith('does not contain expected subset', fn () => $this->json(['a' => 1])->assertJson(['a' => 2]));
    }

    public function testAssertJsonStructure_DocShapes(): void
    {
        $response = $this->json([
            'data' => [
                ['id' => 1, 'title' => 'a', 'price' => 10],
                ['id' => 2, 'title' => 'b', 'price' => 20],
            ],
            'meta' => ['total' => 2],
        ]);

        // شکل DOC-084E §5: هر عضو data باید id/title/price داشته باشد
        $response->assertJsonStructure(['data' => [['id', 'title', 'price']]]);
        $response->assertJsonStructure(['meta' => ['total']]);
        $response->assertJsonStructure(['data', 'meta']);
        // wildcard صریح
        $response->assertJsonStructure(['data' => ['*' => ['id']]]);
    }

    public function testAssertJsonStructure_MissingKey_FailsWithPath(): void
    {
        $response = $this->json(['data' => [['id' => 1]]]);
        $this->assertFailsWith('structure mismatch at [$.data[0].title]', fn () => $response->assertJsonStructure(['data' => [['id', 'title']]]));
    }

    public function testAssertJsonCount_Works(): void
    {
        $response = $this->json(['data' => [1, 2, 3]]);
        $response->assertJsonCount(3, 'data');
        $this->assertFailsWith('to contain 5 item(s), got 3', fn () => $response->assertJsonCount(5, 'data'));
    }

    public function testAssertJsonValidationErrors_SupportsFrameworkShapes(): void
    {
        // شکل RFC 9457 فریمورک (JsonRenderer در debug): context.fields
        $problem = $this->json([
            'type' => 'about:blank',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'messageId' => 'validation.failed',
            'context' => ['fields' => ['title', 'price']],
        ]);
        $problem->assertJsonValidationErrors(['title']);
        $problem->assertJsonValidationErrors('price');

        // شکل کلاسیک: errors به‌صورت map
        $classic = $this->json(['errors' => ['email' => ['required']]]);
        $classic->assertJsonValidationErrors(['email']);
    }

    public function testAssertJsonValidationErrors_MissingField_Fails(): void
    {
        $response = $this->json(['context' => ['fields' => ['title']]]);
        $this->assertFailsWith('Expected validation errors for [price]', fn () => $response->assertJsonValidationErrors(['price']));
    }

    public function testJsonAccessor_DotPathAndInvalidBody(): void
    {
        $response = $this->json(['data' => ['id' => 7]]);
        self::assertSame(['id' => 7], $response->json('data'));
        self::assertNull($response->json('missing.key'));

        $html = new TestResponse(new Response('<html></html>', 200));
        self::assertNull($html->json());
        $this->assertFailsWith('not valid JSON', fn () => $html->assertJson([]));
    }

    // ─── redirect / body / cookie / header ────────────────────────────────

    public function testAssertRedirect_MatchesLocationPath(): void
    {
        $redirect = new TestResponse(
            (new Response('', 302))->withHeader('Location', 'http://localhost/login?next=1'),
        );
        $redirect->assertRedirect();
        $redirect->assertRedirect('/login?next=1');

        $this->assertFailsWith('Expected redirect status', fn () => $this->wrap(200)->assertRedirect());
        $this->assertFailsWith('Expected redirect to [/dash]', fn () => $redirect->assertRedirect('/dash'));
    }

    public function testSeeAndDontSee(): void
    {
        $response = new TestResponse(new Response('<p>سلام دنیا</p>', 200));
        $response->assertSee('سلام دنیا')->assertDontSee('Goodbye');

        $this->assertFailsWith('does not contain', fn () => $response->assertSee('Goodbye'));
        $this->assertFailsWith('unexpectedly contains', fn () => $response->assertDontSee('سلام'));
    }

    public function testAssertCookie_Works(): void
    {
        $response = new TestResponse((new Response('ok'))->cookie(new Cookie('tvn_x', '1')));
        $response->assertCookie('tvn_x');

        $this->assertFailsWith("cookie [nope]", fn () => $response->assertCookie('nope'));
    }

    public function testAssertHeader_CaseInsensitive(): void
    {
        $response = new TestResponse((new Response('', 200))->withHeader('X-Tavaan-Ref', 'AB12'));
        $response->assertHeader('X-Tavaan-Ref');
        $response->assertHeader('x-tavaan-ref');
        self::assertSame('AB12', $response->header('x-tavaan-ref'));
        self::assertNull($response->header('X-Nothing'));
        self::assertSame('AB12', $response->header('X-Tavaan-Ref'));

        $this->assertFailsWith("header [X-Missing]", fn () => $response->assertHeader('X-Missing'));
    }

    public function testAccessors_ExposeBaseResponse(): void
    {
        $base = new Response('body', 201, ['X-A' => 'b']);
        $response = new TestResponse($base);

        self::assertSame($base, $response->baseResponse());
        self::assertSame(201, $response->status());
        self::assertSame('body', $response->content());
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    private function wrap(int $status, string $body = ''): TestResponse
    {
        return new TestResponse(new Response($body, $status));
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): TestResponse
    {
        return new TestResponse(Response::json($data, $status));
    }

    private function assertFailsWith(string $needle, \Closure $action): void
    {
        try {
            $action();
        } catch (AssertionFailed $e) {
            self::assertStringContainsString($needle, $e->getMessage());

            return;
        }

        self::fail('Expected AssertionFailed containing [' . $needle . '] was not thrown.');
    }
}
