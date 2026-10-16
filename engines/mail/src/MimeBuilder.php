<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

/**
 * تَوان | Tavaan — Mail
 * MimeBuilder — ساخت رشتهٔ MIME کامل (RFC 822/2045/2047؛ DOC-065 §6).
 *
 * ساختار:
 * ```
 * multipart/mixed            (وقتی پیوست دارد)
 * ├─ multipart/alternative   (وقتی text + html)
 * │  ├─ text/plain
 * │  └─ multipart/related    (وقتی تصویر inline دارد)
 * │     ├─ text/html
 * │     └─ image/… (Content-ID)
 * └─ application/… (پیوست‌ها، base64)
 * ```
 *
 * 🔒 هدرهای غیر-ASCII (فارسی) با RFC 2047 encode می‌شوند — هم از
 * خرابی encoding جلوگیری می‌کند هم از Header Injection.
 * 🔒 Bcc هرگز در هدرها نمی‌آید (فقط envelope RCPT) — نشت گیرندهٔ مخفی صفر.
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class MimeBuilder
{
    private const CRLF = "\r\n";

    private function __construct()
    {
    }

    public static function build(Message $message): string
    {
        $attachments = $message->attachments();
        $inline = \array_values(\array_filter($attachments, static fn (Attachment $a): bool => $a->inline));
        $regular = \array_values(\array_filter($attachments, static fn (Attachment $a): bool => !$a->inline));

        $bodyPart = self::buildBodyPart($message, $inline);

        if ($regular === []) {
            return self::buildHeaders($message, $bodyPart['headers']) . self::CRLF . $bodyPart['body'];
        }

        $mixedBoundary = self::boundary();
        $body = '--' . $mixedBoundary . self::CRLF
            . self::formatHeaders($bodyPart['headers']) . self::CRLF . self::CRLF
            . $bodyPart['body'];

        foreach ($regular as $attachment) {
            $body .= self::CRLF . '--' . $mixedBoundary . self::CRLF
                . 'Content-Type: ' . $attachment->mime . '; name="' . self::encodeQuoted($attachment->safeFilename()) . '"' . self::CRLF
                . 'Content-Transfer-Encoding: base64' . self::CRLF
                . 'Content-Disposition: attachment; filename="' . self::encodeQuoted($attachment->safeFilename()) . '"' . self::CRLF
                . self::CRLF
                . \chunk_split($attachment->contentBase64, 76, self::CRLF);
        }
        $body .= self::CRLF . '--' . $mixedBoundary . '--' . self::CRLF;

        $headers = self::buildHeaders($message, [
            'Content-Type' => 'multipart/mixed; boundary="' . $mixedBoundary . '"',
        ]);

        return $headers . self::CRLF . $body;
    }

    /**
     * هدرهای RFC 2047 — برای تست مستقیم (T-02).
     */
    public static function encodeHeader(string $value): string
    {
        if (\preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;   // ASCII خالص — دست‌نخورده
        }

        // 🔒 کلمه‌به‌کلمه encode تا هر encoded-word ≤ 75 کاراکتر بماند
        $words = [];
        $chars = \preg_split('//u', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $chunk = '';

        foreach ($chars as $char) {
            $chunk .= $char;
            if (\strlen(\base64_encode($chunk)) > 44) {   // 44 → کل کلمه ~64 کاراکتر
                $words[] = '=?UTF-8?B?' . \base64_encode($chunk) . '?=';
                $chunk = '';
            }
        }
        if ($chunk !== '') {
            $words[] = '=?UTF-8?B?' . \base64_encode($chunk) . '?=';
        }

        return \implode(' ', $words);
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $extra
     */
    private static function buildHeaders(Message $message, array $extra): string
    {
        $headers = [];

        $from = $message->getFrom();
        if ($from !== null) {
            $headers['From'] = self::formatAddress($from);
        }

        $headers['To'] = \implode(', ', \array_map(self::formatAddress(...), $message->getTo()));

        if ($message->getCc() !== []) {
            $headers['Cc'] = \implode(', ', \array_map(self::formatAddress(...), $message->getCc()));
        }

        // 🔒 Bcc عمداً در هدرها نمی‌آید

        if ($message->getReplyTo() !== []) {
            $headers['Reply-To'] = \implode(', ', \array_map(self::formatAddress(...), $message->getReplyTo()));
        }

        if ($message->getSubject() !== '') {
            $headers['Subject'] = self::encodeHeader($message->getSubject());
        }

        $headers['Date'] = \date('r');
        $headers['Message-ID'] = '<' . $message->id() . '>';
        $headers['MIME-Version'] = '1.0';

        foreach ($message->customHeaders() as $name => $value) {
            $headers[$name] = $value;
        }

        foreach ($extra as $name => $value) {
            $headers[$name] = $value;
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return \implode(self::CRLF, $lines);
    }

    /** @return array{headers: array<string, string>, body: string} */
    private static function buildBodyPart(Message $message, array $inline): array
    {
        $text = $message->getText();
        $html = $message->getHtml();

        // فقط متن
        if ($html === '') {
            return [
                'headers' => [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Content-Transfer-Encoding' => 'base64',
                ],
                'body' => \chunk_split(\base64_encode($text), 76, self::CRLF),
            ];
        }

        $htmlPart = [
            'headers' => [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Transfer-Encoding' => 'base64',
            ],
            'body' => \chunk_split(\base64_encode($html), 76, self::CRLF),
        ];

        // html + تصاویر inline → multipart/related
        if ($inline !== []) {
            $relatedBoundary = self::boundary();
            $related = '--' . $relatedBoundary . self::CRLF
                . self::formatHeaders($htmlPart['headers']) . self::CRLF . self::CRLF
                . $htmlPart['body'];

            foreach ($inline as $attachment) {
                $related .= self::CRLF . '--' . $relatedBoundary . self::CRLF
                    . 'Content-Type: ' . $attachment->mime . '; name="' . self::encodeQuoted($attachment->safeFilename()) . '"' . self::CRLF
                    . 'Content-Transfer-Encoding: base64' . self::CRLF
                    . 'Content-ID: <' . (string) $attachment->cid . '>' . self::CRLF
                    . 'Content-Disposition: inline; filename="' . self::encodeQuoted($attachment->safeFilename()) . '"' . self::CRLF
                    . self::CRLF
                    . \chunk_split($attachment->contentBase64, 76, self::CRLF);
            }
            $related .= self::CRLF . '--' . $relatedBoundary . '--' . self::CRLF;

            $htmlPart = [
                'headers' => ['Content-Type' => 'multipart/related; boundary="' . $relatedBoundary . '"'],
                'body' => $related,
            ];
        }

        // فقط html
        if ($text === '') {
            return $htmlPart;
        }

        // text + html → multipart/alternative
        $altBoundary = self::boundary();
        $alternative = '--' . $altBoundary . self::CRLF
            . 'Content-Type: text/plain; charset=UTF-8' . self::CRLF
            . 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF
            . \chunk_split(\base64_encode($text), 76, self::CRLF)
            . self::CRLF . '--' . $altBoundary . self::CRLF
            . self::formatHeaders($htmlPart['headers']) . self::CRLF . self::CRLF
            . $htmlPart['body']
            . self::CRLF . '--' . $altBoundary . '--' . self::CRLF;

        return [
            'headers' => ['Content-Type' => 'multipart/alternative; boundary="' . $altBoundary . '"'],
            'body' => $alternative,
        ];
    }

    /** @param array<string, string> $headers */
    private static function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return \implode(self::CRLF, $lines);
    }

    /** @param array{address: string, name: string} $address */
    private static function formatAddress(array $address): string
    {
        if ($address['name'] === '') {
            return $address['address'];
        }

        return self::encodeHeader($address['name']) . ' <' . $address['address'] . '>';
    }

    private static function boundary(): string
    {
        return 'tavaan_' . \bin2hex(\random_bytes(16));
    }

    /** escape `"` و backslash برای quoted-string. */
    private static function encodeQuoted(string $value): string
    {
        return \str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
