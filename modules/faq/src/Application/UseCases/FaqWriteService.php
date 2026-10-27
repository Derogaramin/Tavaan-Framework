<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqWriteService — مسیر نوشتن پرسش‌ها و دسته‌ها (create/update/publish/trash).
 */

namespace Modules\Faq\Application\UseCases;

use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\Slug;
use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Entities\FaqItem;
use Modules\Faq\Domain\Repositories\FaqCategoryRepositoryInterface;
use Modules\Faq\Domain\Repositories\FaqItemRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * FaqWriteService — UseCase نوشتن پرسش‌های متداول.
 *
 * زنجیرهٔ پاسخ: markdown → HTML (escape-first) → Security::sanitize (لایهٔ
 * دوم — هم‌راستا با content/blog). اسلاگ دسته یونیک خودکار از عنوان.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqWriteService
{
    /** @param array<string, mixed> $config کلید config('faq') */
    public function __construct(
        private readonly FaqCategoryRepositoryInterface $categories,
        private readonly FaqItemRepositoryInterface $items,
        private readonly Markdown $markdown,
        private readonly array $config = [],
    ) {
    }

    // ─── دسته‌ها ──────────────────────────────────────────────────────────

    /**
     * ساخت دسته.
     *
     * @param array<string, mixed> $input title, slug?, sort_order?
     *
     * @throws ValidationException
     */
    public function createCategory(array $input): FaqCategory
    {
        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
        ], attributes: [
            'title' => 'عنوان دسته',
        ]);

        $slug = $this->resolveCategorySlug(
            (string) ($validated['slug'] ?? ''),
            (string) $validated['title'],
        );

        $category = FaqCategory::make(
            title: (string) $validated['title'],
            slug: $slug,
            sortOrder: $this->normalizeSort($input['sort_order'] ?? null),
        );

        $this->categories->save($category);

        return $category;
    }

    /**
     * ویرایش دسته (عنوان/اسلاگ/ترتیب).
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function updateCategory(FaqCategory $category, array $input): FaqCategory
    {
        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
        ], attributes: [
            'title' => 'عنوان دسته',
        ]);

        $slug = Slug::slugify((string) ($validated['slug'] ?? $category->slug()));
        if ($slug !== '' && $slug !== $category->slug()) {
            if ($this->categories->slugExists($slug, exceptId: $category->id())) {
                throw new ValidationException(
                    errors: ['slug' => ['این اسلاگ پیش‌تر برای دستهٔ دیگری ثبت شده است.']],
                    messageId: 'faq::messages.category_slug_taken',
                );
            }
            $category->changeSlug($slug);
        }

        $category->revise(
            title: (string) $validated['title'],
            sortOrder: \array_key_exists('sort_order', $input) ? $this->normalizeSort($input['sort_order']) : null,
        );

        $this->categories->save($category);

        return $category;
    }

    /** حذف نرم دسته (پرسش‌هایش در نمای عمومی «بدون دسته» می‌شوند). */
    public function trashCategory(FaqCategory $category): FaqCategory
    {
        $category->softDelete();
        $this->categories->save($category);
        Event::dispatch('faq.category.deleted', [
            'category_id' => $category->id(),
            'slug' => $category->slug(),
        ]);

        return $category;
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    /**
     * ساخت پرسش پیش‌نویس (یا منتشرشده با status=published).
     *
     * @param array<string, mixed> $input question, answer, category_id?, sort_order?, status?
     *
     * @throws ValidationException
     */
    public function create(array $input, int $authorId): FaqItem
    {
        $validated = $this->validateItem($input, requireAnswer: true);
        $categoryId = $this->resolveCategory($validated);
        $status = $this->resolveStatus($input);

        $item = FaqItem::draft(
            question: (string) $validated['question'],
            answer: $this->renderAnswer((string) $validated['answer']),
            categoryId: $categoryId,
            authorId: $authorId,
            sortOrder: $this->normalizeSort($input['sort_order'] ?? null),
        );

        $this->items->save($item);

        if ($status === 'published') {
            $this->publish($item);
        }

        return $item;
    }

    /**
     * ویرایش پرسش (بدون تغییر نویسنده/وضعیت) — رویداد faq.item.updated.
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function update(FaqItem $item, array $input): FaqItem
    {
        $validated = $this->validateItem($input, requireAnswer: false);
        $categoryId = $this->resolveCategory($validated);

        $answer = null;
        if (isset($validated['answer']) && \trim((string) $validated['answer']) !== '') {
            $answer = $this->renderAnswer((string) $validated['answer']);
        }

        $item->revise(
            question: (string) $validated['question'],
            answer: $answer,
            categoryId: $categoryId,
            sortOrder: \array_key_exists('sort_order', $input) ? $this->normalizeSort($input['sort_order']) : null,
            clearCategory: \array_key_exists('category_id', $input) && (string) ($input['category_id'] ?? '') === '',
        );

        $this->items->save($item);
        $this->flushEvents($item);

        return $item;
    }

    /** انتشار — رویداد faq.item.published. */
    public function publish(FaqItem $item): FaqItem
    {
        $item->publish();
        $this->items->save($item);
        $this->flushEvents($item);

        return $item;
    }

    public function archive(FaqItem $item): FaqItem
    {
        $item->archive();
        $this->items->save($item);

        return $item;
    }

    public function backToDraft(FaqItem $item): FaqItem
    {
        $item->backToDraft();
        $this->items->save($item);

        return $item;
    }

    /** حذف نرم — رویداد payload-only faq.item.deleted. */
    public function trash(FaqItem $item): FaqItem
    {
        $item->softDelete();
        $this->items->save($item);
        Event::dispatch('faq.item.deleted', ['item_id' => $item->id(), 'question' => $item->question()]);

        return $item;
    }

    /** حذف سخت (فقط trash‌شده — محافظت از داده). */
    public function forceDelete(FaqItem $item): void
    {
        if (!$item->isTrashed()) {
            throw new \DomainException('فقط پرسش حذف‌شده (trash) به‌صورت سخت پاک می‌شود.');
        }

        $this->items->delete($item->id());
        Event::dispatch('faq.item.deleted', ['item_id' => $item->id(), 'question' => $item->question(), 'force' => true]);
    }

    /** ثبت رأی «مفید بود» — شمارندهٔ انباشته + رویداد faq.helpful.voted. */
    public function registerHelpful(FaqItem $item, bool $yes): FaqItem
    {
        $item->recordHelpful($yes);
        $this->items->incrementHelpful($item->id(), $yes);
        Event::dispatch('faq.helpful.voted', [
            'item_id' => $item->id(),
            'vote' => $yes ? 'yes' : 'no',
            'helpful_yes' => $item->helpfulYes(),
            'helpful_no' => $item->helpfulNo(),
        ]);

        return $item;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateItem(array $input, bool $requireAnswer): array
    {
        return Validator::validate($input, [
            'question' => ['required', 'string', 'max:500'],
            'answer' => $requireAnswer ? ['required', 'string'] : ['nullable', 'string'],
            'category_id' => ['nullable', 'string', 'max:26'],
        ], attributes: [
            'question' => 'پرسش',
            'answer' => 'پاسخ',
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @throws ValidationException
     */
    private function resolveCategory(array $validated): ?string
    {
        $categoryId = (string) ($validated['category_id'] ?? '');
        if ($categoryId === '') {
            return null;
        }

        if ($this->categories->findById($categoryId) === null) {
            throw new ValidationException(
                errors: ['category_id' => ['دستهٔ انتخاب‌شده وجود ندارد.']],
                messageId: 'faq::messages.category_not_found',
            );
        }

        return $categoryId;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    private function resolveStatus(array $input): string
    {
        $status = (string) ($input['status'] ?? 'draft');
        if (!\in_array($status, ['draft', 'published'], true)) {
            throw new ValidationException(
                errors: ['status' => ['وضعیت نامعتبر است (فقط draft یا published هنگام ساخت).']],
                messageId: 'faq::messages.status_invalid',
            );
        }

        return $status;
    }

    private function resolveCategorySlug(string $explicit, string $title): string
    {
        $base = $explicit !== '' ? Slug::slugify($explicit) : Slug::slugify($title);
        if ($base === '') {
            $base = 'category';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->categories->slugExists($candidate))->toString();
    }

    /** markdown/html → HTML سانیتیزه‌شده (دو لایه: escape-first + Security::sanitize). */
    private function renderAnswer(string $raw): string
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return '';
        }

        $format = (string) ($this->config['body_format'] ?? 'markdown');
        $html = $format === 'html' ? $raw : $this->markdown->toHtml($raw);

        $profile = (string) ($this->config['sanitize']['profile'] ?? 'rich_text');
        $extraTags = (array) ($this->config['sanitize']['extra_tags'] ?? []);

        return Security::sanitize($html, $profile, [
            'allowed_tags' => [...(array) (config('security.sanitizers.' . $profile . '.allowed_tags') ?? []), ...$extraTags],
        ]);
    }

    private function normalizeSort(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        return \max(0, \min(999999, (int) $raw));
    }

    /** انتشار رویدادهای دامنهٔ ثبت‌شده روی entity. */
    private function flushEvents(FaqItem $item): void
    {
        foreach ($item->pullEvents() as $event) {
            $name = \method_exists($event, 'eventName') ? (string) $event->eventName() : '';
            if ($name === '') {
                continue;
            }

            Event::dispatch($name, [
                'item_id' => $item->id(),
                'question' => $item->question(),
            ]);
        }
    }
}
