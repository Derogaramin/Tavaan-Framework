<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions;

use Tavaan\Core\Error\LogMasker;
use Tavaan\Core\Error\RefCodeGenerator;

/**
 * تَوان | Tavaan — Core
 * پایهٔ همهٔ استثناهای فریمورک با messageId، context، hint و refCode.
 *
 * هر خطا «قابل توضیح» است (Fail Explainable): کلید ترجمه به‌جای متن خام،
 * دادهٔ زمینه با Masking خودکار، راهنمای رفع و کد ارجاع پشتیبانی.
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/01-standards/error-handling.md
 */
abstract class TavaanException extends \RuntimeException implements
    RenderableException,
    ContextualException,
    ReportableException
{
    /** کد ارجاع ۸ کاراکتری برای پشتیبانی (بدون نشت جزئیات) */
    public readonly string $refCode;

    /**
     * @param string               $messageId کلید ترجمه (هرگز متن خام)
     * @param array<string, mixed> $context   دادهٔ زمینه (Masking خودکار در گزارش)
     * @param string|null          $hint      راهنمای رفع (برای توسعه‌دهنده)
     * @param string|null          $docsUrl   لینک مستندات مرتبط
     * @param int                  $code      کد عددی دامنه‌ای
     * @param \Throwable|null      $previous  خطای قبلی
     */
    public function __construct(
        public readonly string $messageId,
        public readonly array $context = [],
        public readonly ?string $hint = null,
        public readonly ?string $docsUrl = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $code, $previous);
        $this->refCode = RefCodeGenerator::generate();
    }

    public function render(): mixed
    {
        return null;
    }

    public function shouldReport(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function reportContext(): array
    {
        return [
            'exception' => static::class,
            'messageId' => $this->messageId,
            'code'      => $this->code,
            'refCode'   => $this->refCode,
            'context'   => LogMasker::mask($this->context),
            'hint'      => $this->hint,
        ];
    }
}
