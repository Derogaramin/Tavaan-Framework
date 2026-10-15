<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

/**
 * قرارداد Kernel وب (DOC-014 §2.3).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
interface KernelInterface
{
    public function handle(RequestInterface $request): ResponseInterface;

    public function terminate(RequestInterface $request, ResponseInterface $response): void;
}
