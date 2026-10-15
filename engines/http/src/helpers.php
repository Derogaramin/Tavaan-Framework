<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Http
 * helpers.php — response()/redirect()/route()/url()/asset()/view()/back()/session().
 */

use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Http\ResponseFactory;

if (!\function_exists('response')) {
    /** ResponseFactory (یا ساخت پاسخ با body). */
    function response(?string $body = null, int $status = 200, array $headers = []): Response|ResponseFactory
    {
        $factory = Facade::getFacadeApplication()->make(ResponseFactory::class);

        return $body === null ? $factory : $factory->make($body, $status, $headers);
    }
}

if (!\function_exists('redirect')) {
    function redirect(?string $to = null, int $status = 302): mixed
    {
        $redirector = Facade::getFacadeApplication()->make('redirect');

        return $to === null ? $redirector : $redirector->to($to, $status);
    }
}

if (!\function_exists('back')) {
    function back(int $status = 302): Response
    {
        return Facade::getFacadeApplication()->make('redirect')->back($status);
    }
}

if (!\function_exists('route')) {
    /** @param array<string, mixed> $parameters */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return Facade::getFacadeApplication()->make('url')->route($name, $parameters, $absolute);
    }
}

if (!\function_exists('url')) {
    function url(string $path = '/'): string
    {
        return Facade::getFacadeApplication()->make('url')->url($path);
    }
}

if (!\function_exists('asset')) {
    function asset(string $path): string
    {
        return Facade::getFacadeApplication()->make('url')->asset($path);
    }
}

if (!\function_exists('view')) {
    /** @param array<string, mixed> $data */
    function view(string $template, array $data = []): mixed
    {
        return Facade::getFacadeApplication()->make('view')->make($template, $data);
    }
}

if (!\function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        $app = Facade::getFacadeApplication();
        if (!$app->bound('session')) {
            return $default;
        }
        $session = $app->make('session');

        return $key === null ? $session : $session->get($key, $default);
    }
}

if (!\function_exists('old')) {
    /** ورودی flash شدهٔ درخواست قبل (برای فرم‌ها). */
    function old(string $key, mixed $default = null): mixed
    {
        $app = Facade::getFacadeApplication();
        if (!$app->bound('session')) {
            return $default;
        }

        return $app->make('session')->getOldInput($key, $default);
    }
}

if (!\function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $app = Facade::getFacadeApplication();

        return $app->bound('session') ? (string) $app->make('session')->token() : '';
    }
}

if (!\function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . \htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!\function_exists('e')) {
    /** escape برای HTML (میان‌بر Escaper::html). */
    function e(mixed $value): string
    {
        return \Tavaan\Engine\View\Escaper::html($value === null ? '' : (string) $value);
    }
}
