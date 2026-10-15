<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error\Renderers;

use Tavaan\Core\Error\LogMasker;
use Tavaan\Core\Exceptions\TavaanException;
use Tavaan\Engine\Error\ErrorCode;
use Tavaan\Engine\Error\RenderResult;

/**
 * تَوان | Tavaan — Error
 * JsonRenderer — پاسخ خطای RFC 9457 (application/problem+json).
 *
 * در PROD فقط messageId/refCode/عنوان عمومی؛ در DEV پیام واقعی و hint.
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class JsonRenderer
{
    /** @var array<int, string> */
    private const TITLES = [
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
        404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict',
        419 => 'Page Expired', 422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 503 => 'Service Unavailable',
    ];

    public function __construct(private readonly bool $debug = false, private readonly bool $rfc9457 = true)
    {
    }

    public function render(\Throwable $e, int $status): RenderResult
    {
        $title = self::TITLES[$status] ?? 'Error';

        if ($this->rfc9457) {
            $body = [
                'type'   => $e instanceof TavaanException && $e->docsUrl !== null
                    ? 'https://docs.tavaan.dev/' . $e->docsUrl
                    : 'about:blank',
                'title'  => $title,
                'status' => $status,
            ];

            if ($e instanceof TavaanException) {
                $body['messageId'] = $e->messageId;
                $body['refCode'] = $e->refCode;
                $code = ErrorCode::forException($e);
                if ($code !== null) {
                    $body['errorCode'] = $code;
                }
                if ($this->debug) {
                    $body['detail'] = $e->getMessage();
                    if ($e->hint !== null) {
                        $body['hint'] = $e->hint;
                    }
                    $body['context'] = LogMasker::mask($e->context);
                } else {
                    $body['detail'] = $status >= 500 ? 'خطای داخلی سرور. کد ارجاع را به پشتیبانی بدهید.' : $title;
                }
            } else {
                $body['detail'] = $this->debug ? $e->getMessage() : ($status >= 500 ? 'خطای داخلی سرور.' : $title);
                if ($this->debug) {
                    $body['exception'] = $e::class;
                    $body['file'] = $e->getFile() . ':' . $e->getLine();
                }
            }
        } else {
            $body = [
                'error' => [
                    'status' => $status,
                    'title' => $title,
                    'message' => $this->debug ? $e->getMessage() : ($status >= 500 ? 'server error' : $title),
                    'ref' => $e instanceof TavaanException ? $e->refCode : null,
                ],
            ];
        }

        $ref = $e instanceof TavaanException ? $e->refCode : '';

        return new RenderResult(
            status: $status,
            body: (string) \json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            contentType: 'application/problem+json; charset=utf-8',
            headers: $ref === '' ? [] : ['X-Tavaan-Ref' => $ref],
        );
    }
}
