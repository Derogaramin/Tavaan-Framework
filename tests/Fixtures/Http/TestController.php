<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/** Fixture: کنترلر تست. */
final class TestController
{
    public function index(): string
    {
        return 'index-ok';
    }

    public function show(int $id): string
    {
        return 'show-' . $id;
    }

    public function withRequest(Request $request, string $slug): Response
    {
        return Response::json(['slug' => $slug, 'q' => $request->query('q')]);
    }

    public static function staticAction(): string
    {
        return 'static-ok';
    }

    public function arrayOut(): array
    {
        return ['ok' => true, 'message' => 'سلام'];
    }

    public function nullOut(): null
    {
        return null;
    }

    public function throws(): never
    {
        throw new \RuntimeException('controller-boom');
    }
}
