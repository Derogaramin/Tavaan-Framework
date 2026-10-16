<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Messages;

/**
 * تَوان | Tavaan — Notification
 * TelegramMessage — پیام پیام‌رسان‌ها (telegram/bale/eitaa — Bot API مشترک؛ DOC-066 §2).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class TelegramMessage
{
    private ?string $actionText = null;

    private ?string $actionUrl = null;

    private string $parseMode = 'HTML';

    private function __construct(public readonly string $content)
    {
    }

    public static function create(string $content): self
    {
        return new self($content);
    }

    /** دکمهٔ inline. */
    public function action(string $text, string $url): self
    {
        $this->actionText = $text;
        $this->actionUrl = $url;

        return $this;
    }

    public function parseMode(string $mode): self
    {
        $this->parseMode = $mode;

        return $this;
    }

    public function actionText(): ?string
    {
        return $this->actionText;
    }

    public function actionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function getParseMode(): string
    {
        return $this->parseMode;
    }

    /** @return array<string, mixed>|null reply_markup برای Bot API */
    public function replyMarkup(): ?array
    {
        if ($this->actionText === null || $this->actionUrl === null) {
            return null;
        }

        return [
            'inline_keyboard' => [[
                ['text' => $this->actionText, 'url' => $this->actionUrl],
            ]],
        ];
    }
}
