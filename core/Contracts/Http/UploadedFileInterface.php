<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

/**
 * قرارداد فایل آپلود‌شده.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
interface UploadedFileInterface
{
    public function name(): string;

    public function originalName(): string;

    public function size(): int;

    public function mimeType(): string;

    /** پسوند فایل (بدون نقطه، lowercase — از originalName یا mime). */
    public function extension(): string;

    public function error(): int;

    public function isValid(): bool;

    /** انتقال امن به مسیر مقصد (sandbox). */
    public function move(string $directory, ?string $name = null): string;
}
