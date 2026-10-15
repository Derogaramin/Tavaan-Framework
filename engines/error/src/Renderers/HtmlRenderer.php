<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error\Renderers;

use Tavaan\Core\Exceptions\TavaanException;
use Tavaan\Engine\Error\RenderResult;

/**
 * تَوان | Tavaan — Error
 * HtmlRenderer — صفحهٔ خطای امن PROD (بدون نشت جزئیات فنی).
 *
 * فقط: messageId ترجمه‌شده/عنوان + refCode + لینک پشتیبانی + نسخهٔ app.
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class HtmlRenderer
{
    /** @var array<int, string> */
    private const TITLES = [
        400 => 'درخواست نامعتبر', 401 => 'احراز هویت لازم است', 403 => 'دسترسی مجاز نیست',
        404 => 'یافت نشد', 405 => 'متد مجاز نیست', 409 => 'تعارض داده',
        419 => 'نشست منقضی شده', 422 => 'داده نامعتبر', 429 => 'تعداد درخواست بیش از حد',
        500 => 'خطای داخلی سرور', 503 => 'سرویس موقتاً در دسترس نیست',
    ];

    public function __construct(
        private readonly bool $showRefCode = true,
        private readonly ?string $supportUrl = null,
    ) {
    }

    public function render(\Throwable $e, int $status): RenderResult
    {
        $title = self::TITLES[$status] ?? 'خطا';
        $ref = $e instanceof TavaanException ? $e->refCode : '';
        $version = \defined('TVN_VERSION') ? (string) \TVN_VERSION : '';

        $html = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->esc($title) . ' (' . $status . ')</title><style>'
            . 'body{font-family:Tahoma,Vazirmatn,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;'
            . 'align-items:center;justify-content:center;min-height:100vh;margin:0}'
            . '.card{background:#1e293b;border-radius:16px;padding:48px 56px;text-align:center;max-width:480px;'
            . 'box-shadow:0 25px 50px -12px rgba(0,0,0,.5)}'
            . '.code{font-size:64px;font-weight:800;color:#f59e0b;margin:0}'
            . 'h1{font-size:20px;margin:8px 0 16px}'
            . '.ref{font-family:monospace;background:#0f172a;padding:6px 14px;border-radius:8px;display:inline-block;'
            . 'margin-top:16px;letter-spacing:2px;color:#38bdf8}'
            . 'a{color:#38bdf8}.ver{color:#64748b;font-size:11px;margin-top:24px}'
            . '</style></head><body><div class="card">'
            . '<p class="code">' . $status . '</p>'
            . '<h1>' . $this->esc($title) . '</h1>'
            . '<p>در صورت تکرار، این کد را به پشتیبانی اطلاع دهید.</p>'
            . ($this->showRefCode && $ref !== '' ? '<div class="ref">' . $this->esc($ref) . '</div>' : '')
            . ($this->supportUrl !== null ? '<p><a href="' . $this->esc($this->supportUrl) . '">پشتیبانی</a></p>' : '')
            . '<p class="ver">Tavaan' . ($version !== '' ? ' v' . $this->esc($version) : '') . '</p>'
            . '</div></body></html>';

        return new RenderResult(
            status: $status,
            body: $html,
            headers: $ref === '' ? [] : ['X-Tavaan-Ref' => $ref],
        );
    }

    private function esc(string $s): string
    {
        return \htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
