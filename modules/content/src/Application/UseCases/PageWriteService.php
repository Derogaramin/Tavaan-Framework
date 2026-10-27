<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PageWriteService — مسیر نوشتن صفحات CMS (create/update/publish/trash).
 */

namespace Modules\Content\Application\UseCases;

use Modules\Content\Domain\Entities\Page;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\Repositories\PageRepositoryInterface;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\Content\Domain\ValueObjects\Slug;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * PageWriteService — UseCase نوشتن صفحات.
 *
 * زنجیرهٔ محتوا: markdown → HTML (escape-first) → Security::sanitize (لایهٔ
 * دوم — هم‌راستا با blog، AC-BLOG-03). slug یونیک خودکار از عنوان.
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class PageWriteService
{
    /** @param array<string, mixed> $config کلید config('content') */
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly Markdown $markdown,
        private readonly ExcerptGenerator $excerpts,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت صفحهٔ پیش‌نویس.
     *
     * @param array<string, mixed> $input title, slug?, body?, excerpt?, seo_*?, comments_open?
     *
     * @throws ValidationException
     */
    public function create(array $input, int $authorId): Page
    {
        $validated = $this->validate($input);

        $slug = $this->resolveSlug((string) ($validated['slug'] ?? ''), (string) $validated['title']);
        $bodyHtml = $this->renderBody((string) ($validated['body'] ?? ''));
        $excerpt = $this->resolveExcerpt((string) ($validated['excerpt'] ?? ''), $bodyHtml);

        $page = Page::draft(
            slug: $slug,
            title: (string) $validated['title'],
            authorId: $authorId,
            excerpt: $excerpt !== '' ? $excerpt : null,
            body: $bodyHtml !== '' ? $bodyHtml : null,
            seo: $this->buildSeo($validated, (string) $validated['title'], $excerpt),
            commentsOpen: !isset($input['comments_open']) || (bool) $input['comments_open'],
        );

        $this->pages->save($page);

        if (($input['status'] ?? 'draft') === 'published') {
            $this->publish($page);
        }

        return $page;
    }

    /**
     * ویرایش صفحه (بدون تغییر نویسنده).
     *
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function update(Page $page, array $input): Page
    {
        $validated = $this->validate($input);

        $slug = Slug::slugify((string) ($validated['slug'] ?? $page->slug()));
        if ($slug !== '' && $slug !== $page->slug()) {
            if ($this->pages->slugExists($slug, exceptId: $page->id())) {
                $slug = Slug::unique($slug, fn (string $c): bool => $this->pages->slugExists($c, exceptId: $page->id()))->toString();
            }
            $page->changeSlug($slug);
        }

        $bodyHtml = $this->renderBody((string) ($validated['body'] ?? ''));
        $excerpt = $this->resolveExcerpt((string) ($validated['excerpt'] ?? ''), $bodyHtml);

        $page->revise(
            title: (string) $validated['title'],
            slug: null,
            excerpt: $excerpt !== '' ? $excerpt : null,
            body: $bodyHtml !== '' ? $bodyHtml : null,
            seo: $this->buildSeo($validated, (string) $validated['title'], $excerpt),
            commentsOpen: \array_key_exists('comments_open', $input) ? (bool) $input['comments_open'] : null,
        );

        $this->pages->save($page);
        $this->flushEvents($page);

        return $page;
    }

    /** انتشار فوری — رویداد content.page.published. */
    public function publish(Page $page, ?\DateTimeImmutable $at = null): Page
    {
        if (!$page->status()->canTransitionTo(ContentStatus::Published)) {
            throw new \DomainException('انتشار از وضعیت «' . $page->status()->labelFa() . '» مجاز نیست.');
        }

        $page->publish($at);
        $this->pages->save($page);
        $this->flushEvents($page);

        return $page;
    }

    public function archive(Page $page): Page
    {
        $page->archive();
        $this->pages->save($page);
        $this->flushEvents($page);

        return $page;
    }

    public function backToDraft(Page $page): Page
    {
        $page->backToDraft();
        $this->pages->save($page);
        $this->flushEvents($page);

        return $page;
    }

    /** حذف نرم. */
    public function trash(Page $page): Page
    {
        $page->softDelete();
        $this->pages->save($page);
        Event::dispatch('content.page.deleted', ['page_id' => $page->id(), 'slug' => $page->slug()]);

        return $page;
    }

    /** حذف سخت (فقط برای صفحات trash‌شده — محافظت از داده). */
    public function forceDelete(Page $page): void
    {
        if (!$page->isTrashed()) {
            throw new \DomainException('فقط صفحهٔ حذف‌شده (trash) به‌صورت سخت پاک می‌شود.');
        }

        $this->pages->delete($page->id());
        Event::dispatch('content.page.deleted', ['page_id' => $page->id(), 'slug' => $page->slug(), 'force' => true]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        return Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'title' => 'عنوان',
            'body' => 'بدنه',
            'excerpt' => 'خلاصه',
        ]);
    }

    private function resolveSlug(string $explicit, string $title): string
    {
        $base = $explicit !== '' ? Slug::slugify($explicit) : Slug::slugify($title);
        if ($base === '') {
            $base = 'page';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->pages->slugExists($candidate))->toString();
    }

    /** markdown/html → HTML سانیتیزه‌شده (دو لایه: escape-first + Security::sanitize). */
    private function renderBody(string $raw): string
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

    private function resolveExcerpt(string $explicit, string $bodyHtml): string
    {
        if (\trim($explicit) !== '') {
            return \trim($explicit);
        }
        if ($bodyHtml === '') {
            return '';
        }

        return $this->excerpts->fromHtml($bodyHtml);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function buildSeo(array $validated, string $title, string $excerpt): SeoMetadata
    {
        return SeoMetadata::make(
            title: (string) ($validated['seo_title'] ?? ''),
            plainBody: $excerpt !== '' ? $excerpt : $title,
            description: (string) ($validated['seo_description'] ?? ''),
            keywords: (string) ($validated['seo_keywords'] ?? ''),
        );
    }

    /** انتشار رویدادهای دامنهٔ ثبت‌شده روی entity. */
    private function flushEvents(Page $page): void
    {
        foreach ($page->pullEvents() as $event) {
            $name = \method_exists($event, 'eventName') ? (string) $event->eventName() : '';
            if ($name === '') {
                continue;
            }

            Event::dispatch($name, [
                'page_id' => $page->id(),
                'slug' => $page->slug(),
                'title' => $page->title(),
            ]);
        }
    }
}
