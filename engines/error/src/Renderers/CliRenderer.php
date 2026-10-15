<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error\Renderers;

use Tavaan\Core\Exceptions\TavaanException;
use Tavaan\Engine\Error\ErrorCode;
use Tavaan\Engine\Error\RenderResult;

/**
 * تَوان | Tavaan — Error
 * CliRenderer — خروجی کنسول خطا (رنگی؛ trace کامل در debug).
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class CliRenderer
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function render(\Throwable $e, int $status = 500): RenderResult
    {
        $red = "\033[31m";
        $yellow = "\033[33m";
        $gray = "\033[90m";
        $reset = "\033[0m";

        $out = PHP_EOL . $red . '  [' . $e::class . ']' . $reset . PHP_EOL;

        if ($e instanceof TavaanException) {
            $out .= '  messageId: ' . $yellow . $e->messageId . $reset . PHP_EOL;
            $code = ErrorCode::forException($e);
            if ($code !== null) {
                $out .= '  code:      ' . $code . PHP_EOL;
            }
            $out .= '  refCode:   ' . $e->refCode . PHP_EOL;
            if ($e->hint !== null) {
                $out .= '  hint:      ' . $e->hint . PHP_EOL;
            }
            if ($e->context !== []) {
                $out .= '  context:   ' . (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            }
        }

        $out .= '  message:   ' . $e->getMessage() . PHP_EOL;
        $out .= '  at:        ' . $gray . $e->getFile() . ':' . $e->getLine() . $reset . PHP_EOL;

        if ($this->debug) {
            $out .= PHP_EOL . $gray . '  Stack trace:' . $reset . PHP_EOL;
            foreach (\array_slice($e->getTrace(), 0, 15) as $i => $frame) {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? 0;
                $fn = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
                $out .= \sprintf("  #%d %s%s:%d%s", $i, $gray, $file, $line, $reset) . ' ' . $fn . PHP_EOL;
            }
        }

        $previous = $e->getPrevious();
        if ($previous !== null) {
            $out .= PHP_EOL . $yellow . '  Caused by: [' . $previous::class . '] ' . $previous->getMessage()
                . ' at ' . $previous->getFile() . ':' . $previous->getLine() . $reset . PHP_EOL;
        }

        return new RenderResult(status: $status, body: $out, contentType: 'text/plain');
    }
}
