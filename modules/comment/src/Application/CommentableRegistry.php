<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentableRegistry — ثبت انواع subjectهای نظرپذیر.
 */

namespace Modules\Comment\Application;

/**
 * CommentableRegistry — رجیستری انواع نظرپذیر (commentable types).
 *
 * هر نوع (مثلاً `page`، `poll`، `faq_item`) با برچسب، الگوی URL و سیاست
 * ثبت نظر مهمان معرفی می‌شود. ماژول‌ها/اپلیکیشن‌ها در boot خود انواع
 * خویش را ثبت می‌کنند؛ فهرست پیش‌فرض از config('comment.types') خوانده
 * می‌شود.
 *
 * 🔒 ثبت نوع = allowlist — نوع ثبت‌نشده هرگز نظر نمی‌پذیرد (ضد اسپم/سوءاستفاده).
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class CommentableRegistry
{
    /** @var array<string, array{label: string, url: string, guest_allowed: bool, open: bool}> */
    private array $types = [];

    /**
     * @param array<string, array<string, mixed>> $configTypes انواع از config('comment.types')
     */
    public function __construct(array $configTypes = [])
    {
        foreach ($configTypes as $type => $options) {
            if (!\is_string($type) || !\is_array($options)) {
                continue;
            }
            $this->register(
                type: $type,
                label: (string) ($options['label'] ?? $type),
                url: (string) ($options['url'] ?? ''),
                guestAllowed: (bool) ($options['guest_allowed'] ?? true),
                open: (bool) ($options['open'] ?? true),
            );
        }
    }

    /**
     * ثبت یک نوع نظرپذیر.
     *
     * @param string $url الگوی URL موضوع با placeholder {id} (برای لینک پنل)
     */
    public function register(string $type, string $label, string $url = '', bool $guestAllowed = true, bool $open = true): void
    {
        if (!$this->validType($type)) {
            throw new \InvalidArgumentException('Invalid commentable type: ' . $type);
        }

        $this->types[$type] = [
            'label' => $label !== '' ? $label : $type,
            'url' => $url,
            'guest_allowed' => $guestAllowed,
            'open' => $open,
        ];
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]) && $this->validType($type);
    }

    /** آیا ثبت نظر (عموماً) برای این نوع باز است؟ */
    public function isOpen(string $type): bool
    {
        return (bool) ($this->types[$type]['open'] ?? false);
    }

    public function guestAllowed(string $type): bool
    {
        return (bool) ($this->types[$type]['guest_allowed'] ?? false);
    }

    public function label(string $type): string
    {
        return (string) ($this->types[$type]['label'] ?? $type);
    }

    /** URL موضوع (با جایگزینی {id}) — خالی اگر الگو ثبت نشده باشد. */
    public function url(string $type, string $id): string
    {
        $pattern = (string) ($this->types[$type]['url'] ?? '');

        return $pattern === '' ? '' : \str_replace('{id}', $id, $pattern);
    }

    /** @return list<string> */
    public function types(): array
    {
        return \array_keys($this->types);
    }

    /**
     * 🔒 شناسهٔ نوع فقط [a-z0-9_] — از ورودی مسیر می‌آید (سانیتایز صریح).
     */
    private function validType(string $type): bool
    {
        return \preg_match('/^[a-z][a-z0-9_]{0,63}$/', $type) === 1;
    }
}
