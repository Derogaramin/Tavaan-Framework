<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * TrieNode — گره Compiled Trie (DOC-040 §5.2).
 *
 * ساختار حافظه‌بهینه (DOC-040 §12 — ۱۰۰۰ روت ≤ ۲MB). هر آرایهٔ PHP
 * حداقل ~۱۸۴ بایت (list) یا ~۳۴۴ بایت (hashed) هزینه دارد؛ بنابراین:
 * - تک‌فرزند static به‌صورت inline (بیشتر گره‌ها زنجیرهٔ خطی‌اند)؛
 * - یال‌های پارامتریک: p1 + pMore (بدون list برای حالت تک‌یالی)؛
 * - هندلرها: h1/h2 + hMore (برگ‌ها معمولاً GET+HEAD یک روت‌اند)؛
 * - wildcard: سه پراپرتی inline (بدون آبجکت/آرایهٔ جدا).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/router.md
 */
final class TrieNode
{
    /** @var array<string, self> segment ثابت → گره (فقط وقتی بیش از یک فرزند) */
    public array $static = [];

    /** کلید تک‌فرزند static (inline — جای map حداقل‌۳۴۴بایتی) */
    public ?string $singleKey = null;

    /** گره تک‌فرزند static */
    public ?self $singleChild = null;

    /** اولین یال پارامتریک (همیشه مرتب: specificity DESC، order ASC) */
    public ?ParamEdge $p1 = null;

    /** @var list<ParamEdge>|null بقیهٔ یال‌های پارامتریک (مرتب) */
    public ?array $pMore = null;

    /** نام پارامتر wildcard یا null (یال .*) */
    public ?string $wildcardName = null;

    /** regex یال wildcard (همراه wildcardName) */
    public ?string $wildcardRegex = null;

    /** گره مقصد یال wildcard */
    public ?self $wildcardNode = null;

    /** هندلرهای برگ — مرتب (specificity DESC، order ASC)؛ ≤۲ مورد inline */
    public ?HandlerEntry $h1 = null;

    public ?HandlerEntry $h2 = null;

    /** @var list<HandlerEntry>|null */
    public ?array $hMore = null;

    /** بازیابی فرزند static (بدون ساخت). */
    public function lookupStatic(string $key): ?self
    {
        // ثابت ساختار: وقتی map پر است، single* تهی است (پس از مهاجرت) و برعکس.
        if ($this->static !== []) {
            return $this->static[$key] ?? null;
        }

        return $this->singleKey === $key ? $this->singleChild : null;
    }

    /** بازیابی یا ساخت فرزند static (مهاجرت single→map در صورت نیاز). */
    public function staticChild(string $key): self
    {
        $child = $this->static[$key] ?? null;
        if ($child !== null) {
            return $child;
        }

        if ($this->singleKey === $key) {
            /** @var self */
            return $this->singleChild;
        }

        if ($this->singleKey === null) {
            if ($this->static !== []) {
                // map از قبل برقرار است (≥۲ فرزند) — فرزند جدید هم در map
                return $this->static[$key] = new self();
            }
            $this->singleKey = $key;

            return $this->singleChild = new self();
        }

        // فرزند دوم: مهاجرت single به map
        $single = $this->singleChild;
        if ($single !== null) {
            $this->static[$this->singleKey] = $single;
        }
        $this->singleKey = null;
        $this->singleChild = null;

        return $this->static[$key] = new self();
    }

    /** افزودن یال پارامتریک با درج مرتب (قطعیت: specificity DESC، order ASC، تساوی → ثبت‌شدهٔ اول). */
    public function addParam(ParamEdge $edge): void
    {
        if ($this->p1 === null) {
            $this->p1 = $edge;

            return;
        }

        if (self::compare($edge->specificity, $edge->order, $this->p1->specificity, $this->p1->order) < 0) {
            if ($this->pMore === null) {
                $this->pMore = [$this->p1];
            } else {
                \array_unshift($this->pMore, $this->p1);
            }
            $this->p1 = $edge;

            return;
        }

        $this->pMore ??= [];
        $pos = \count($this->pMore);
        foreach ($this->pMore as $i => $x) {
            if (self::compare($edge->specificity, $edge->order, $x->specificity, $x->order) < 0) {
                $pos = $i;
                break;
            }
        }
        \array_splice($this->pMore, $pos, 0, [$edge]);
    }

    /** فهرست کامل یال‌های پارامتریک (materialize — فقط زمان compile). @return list<ParamEdge> */
    public function params(): array
    {
        if ($this->p1 === null) {
            return [];
        }

        return $this->pMore === null ? [$this->p1] : [$this->p1, ...$this->pMore];
    }

    /** افزودن هندلر با درج مرتب (مشابه addParam). */
    public function addHandler(HandlerEntry $entry): void
    {
        if ($this->h1 === null) {
            $this->h1 = $entry;

            return;
        }

        if ($this->h2 === null) {
            if (self::compare($entry->specificity, $entry->order, $this->h1->specificity, $this->h1->order) < 0) {
                $this->h2 = $this->h1;
                $this->h1 = $entry;
            } else {
                $this->h2 = $entry;
            }

            return;
        }

        $this->hMore ??= [];

        if (self::compare($entry->specificity, $entry->order, $this->h1->specificity, $this->h1->order) < 0) {
            \array_unshift($this->hMore, $this->h2);
            $this->h2 = $this->h1;
            $this->h1 = $entry;

            return;
        }

        if (self::compare($entry->specificity, $entry->order, $this->h2->specificity, $this->h2->order) < 0) {
            \array_unshift($this->hMore, $this->h2);
            $this->h2 = $entry;

            return;
        }

        $pos = \count($this->hMore);
        foreach ($this->hMore as $i => $x) {
            if (self::compare($entry->specificity, $entry->order, $x->specificity, $x->order) < 0) {
                $pos = $i;
                break;
            }
        }
        \array_splice($this->hMore, $pos, 0, [$entry]);
    }

    public function isEmpty(): bool
    {
        return $this->static === [] && $this->singleKey === null && $this->p1 === null
            && $this->wildcardNode === null && $this->h1 === null;
    }

    /** مقایسهٔ ترتیب هندلر/یال: negativity یعنی A قبل از B. */
    private static function compare(int $specA, int $orderA, int $specB, int $orderB): int
    {
        return $specB <=> $specA ?: $orderA <=> $orderB;
    }
}
