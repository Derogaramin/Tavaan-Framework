<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error\Renderers;

use Tavaan\Core\Exceptions\TavaanException;
use Tavaan\Engine\Error\ErrorCode;
use Tavaan\Engine\Error\RenderResult;

/**
 * تَوان | Tavaan — Error
 * DebugRenderer — صفحهٔ خطای غنی DEV (خودبسنده، CSS inline، بدون View Engine).
 *
 * بخش‌ها: Header، Message/Hint، Stack Trace، Code Snippet، Context، Env.
 * 🔒 همهٔ مقادیر escape می‌شوند (حتی در DEV).
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class DebugRenderer
{
    private const SNIPPET_LINES = 8;

    public function render(\Throwable $e, int $status): RenderResult
    {
        $isTavaan = $e instanceof TavaanException;
        $messageId = $isTavaan ? $e->messageId : '';
        $refCode = $isTavaan ? $e->refCode : '';
        $errorCode = ErrorCode::forException($e) ?? '';
        $hint = $isTavaan ? $e->hint : null;

        $traceRows = '';
        foreach (\array_slice($e->getTrace(), 0, 25) as $i => $frame) {
            $file = $this->esc((string) ($frame['file'] ?? '[internal]'));
            $line = (int) ($frame['line'] ?? 0);
            $fn = $this->esc(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''));
            $traceRows .= "<tr><td class=\"n\">#{$i}</td><td><code>{$fn}</code></td><td class=\"f\">{$file}:{$line}</td></tr>";
        }

        $snippet = $this->snippet($e->getFile(), $e->getLine());
        $contextSection = '';
        if ($isTavaan && $e->context !== []) {
            $contextJson = $this->esc((string) \json_encode($e->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $contextSection = "<div class=\"section\"><h2>Context</h2><pre>{$contextJson}</pre></div>";
        }

        $badges = "<span class=\"badge\">HTTP {$status}</span>";
        if ($messageId !== '') {
            $badges .= '<span class="badge">' . $this->esc($messageId) . '</span>';
        }
        if ($errorCode !== '') {
            $badges .= '<span class="badge">' . $this->esc($errorCode) . '</span>';
        }
        if ($refCode !== '') {
            $badges .= '<span class="badge">ref ' . $this->esc($refCode) . '</span>';
        }

        $hintHtml = $hint !== null ? '<div class="hint">💡 ' . $this->esc($hint) . '</div>' : '';

        $previous = '';
        if ($e->getPrevious() !== null) {
            $p = $e->getPrevious();
            $previous = '<div class="section"><h2>Caused by</h2><p><b>' . $this->esc($p::class) . '</b>: '
                . $this->esc($p->getMessage()) . ' <span class="dim">@ ' . $this->esc($p->getFile()) . ':' . $p->getLine() . '</span></p></div>';
        }

        $phpVersion = PHP_VERSION . ' (' . PHP_SAPI . ')';
        $tavaanVersion = \defined('TVN_VERSION') ? (string) \TVN_VERSION : '?';
        $memoryMb = \round(\memory_get_peak_usage(true) / 1048576, 1) . ' MB';
        $currentTime = \date('c');

        $html = <<<HTML
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->esc($e::class)}</title><style>
:root{color-scheme:dark}
body{font-family:Tahoma,Vazirmatn,monospace;background:#0b1120;color:#e2e8f0;margin:0;padding:0}
header{background:#7f1d1d;padding:24px 32px;border-bottom:4px solid #ef4444}
header h1{margin:0;font-size:18px;word-break:break-all}
.badges{margin-top:8px}
.badge{display:inline-block;background:#450a0a;border:1px solid #ef4444;border-radius:6px;padding:2px 10px;font-size:12px;margin-left:8px;font-family:monospace}
main{padding:24px 32px;max-width:1100px}
.section{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:16px 20px;margin-bottom:20px}
.section h2{margin:0 0 12px;font-size:14px;color:#f59e0b}
pre{background:#0b1120;border-radius:8px;padding:12px;overflow-x:auto;font-size:12px;direction:ltr;text-align:left}
code{direction:ltr}
table{width:100%;border-collapse:collapse;font-size:12px}
td{padding:4px 8px;border-bottom:1px solid #1f2937}
td.n{color:#64748b;width:40px}
td.f{color:#38bdf8;direction:ltr;text-align:left}
.dim{color:#64748b}
.hint{background:#14532d;border:1px solid #22c55e;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:13px}
.hl{background:#7f1d1d;display:block}
.msg{font-size:14px;line-height:1.8}
.kv td:first-child{color:#94a3b8;width:220px}
</style></head><body>
<header>
<h1>{$this->esc($e::class)}</h1>
<p class="msg">{$this->esc($e->getMessage())}</p>
<div class="badges">{$badges}</div>
{$hintHtml}
</header>
<main>
<div class="section"><h2>Code Snippet</h2><pre>{$snippet}</pre></div>
<div class="section"><h2>Stack Trace</h2><table>{$traceRows}</table></div>
{$contextSection}
{$previous}
<div class="section"><h2>Environment</h2><table class="kv">
<tr><td>PHP</td><td>{$phpVersion}</td></tr>
<tr><td>Tavaan</td><td>{$tavaanVersion}</td></tr>
<tr><td>Memory</td><td>{$memoryMb}</td></tr>
<tr><td>Time</td><td>{$currentTime}</td></tr>
</table></div>
</main></body></html>
HTML;

        return new RenderResult(
            status: $status,
            body: $html,
            headers: $refCode === '' ? [] : ['X-Tavaan-Ref' => $refCode],
        );
    }

    private function snippet(string $file, int $line): string
    {
        if (!\is_file($file) || $line <= 0) {
            return '<span class="dim">snippet unavailable</span>';
        }

        $lines = \file($file) ?: [];
        $start = \max(0, $line - self::SNIPPET_LINES - 1);
        $end = \min(\count($lines), $line + self::SNIPPET_LINES);

        $out = '';
        for ($i = $start; $i < $end; $i++) {
            $no = $i + 1;
            $text = \rtrim($lines[$i]);
            $prefix = \str_pad((string) $no, 5, ' ', STR_PAD_LEFT);
            $row = $this->esc($prefix . ' │ ' . $text);
            $out .= $no === $line ? "<span class=\"hl\">{$row}</span>\n" : $row . "\n";
        }

        return $out;
    }

    private function esc(string $s): string
    {
        return \htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
