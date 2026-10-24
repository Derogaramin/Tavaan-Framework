<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول خبرگزاری
 * ArticleWriteService — نوشتن خبر + workflow انتشار (DOC-092 §3).
 */

namespace Modules\News\Application\UseCases;

use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\Content\Domain\ValueObjects\Slug;
use Modules\News\Domain\Entities\Article;
use Modules\News\Domain\Repositories\ArticleRepositoryInterface;
use Modules\News\Domain\Repositories\NewsCategoryRepositoryInterface;
use Modules\News\Domain\Repositories\NewsTagRepositoryInterface;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ArticleWriteService — ساخت/ویرایش + انتقال‌های workflow با ردیابی editor.
 *
 * هر نوشتن → `Cache::tags(['news'])->flush()` (استراتژی DOC-092 §5:
 * invalidate با tag؛ TTL کوتاه به‌تنهایی کافی نیست).
 *
 * @api 1.0
 * @see modules/news/README.md
 */
final class ArticleWriteService
{
    /**
     * @param array<string, mixed> $config کلید config('news')
     */
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly NewsCategoryRepositoryInterface $categories,
        private readonly NewsTagRepositoryInterface $tags,
        private readonly Markdown $markdown,
        private readonly ExcerptGenerator $excerpts,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت خبر پیش‌نویس.
     *
     * @param array<string, mixed> $input
     * @param list<string>         $tagNames
     */
    public function create(array $input, int $authorId, array $tagNames = []): Article
    {
        $validated = $this->validate($input);

        $slug = $this->resolveSlug((string) ($validated['slug'] ?? ''), (string) $validated['title']);
        $bodyHtml = $this->renderBody((string) ($validated['body'] ?? ''));
        $lead = $this->resolveLead((string) ($validated['lead'] ?? ''), $bodyHtml);

        $article = Article::draft(
            slug: $slug,
            title: (string) $validated['title'],
            authorId: $authorId,
            lead: $lead !== '' ? $lead : null,
            body: $bodyHtml !== '' ? $bodyHtml : null,
            categoryId: $this->resolveCategoryId($input['category_id'] ?? null),
            featuredImage: $this->resolveMediaId($input['featured_image'] ?? null),
            seo: $this->buildSeo($validated, (string) $validated['title'], $lead),
        );

        $this->articles->save($article);
        $this->syncTags($article->id(), $tagNames);
        $this->applyGallery($article, $input['gallery'] ?? null);
        $this->invalidateCache();

        return $article;
    }

    /**
     * ویرایش محتوایی (بدون تغییر وضعیت workflow).
     *
     * @param array<string, mixed> $input
     * @param list<string>         $tagNames
     */
    public function update(Article $article, array $input, array $tagNames = []): Article
    {
        $validated = $this->validate($input);

        $slug = Slug::slugify((string) ($validated['slug'] ?? $article->slug()));
        if ($slug !== '' && $slug !== $article->slug()) {
            if ($this->articles->slugExists($slug, exceptId: $article->id())) {
                $slug = Slug::unique($slug, fn (string $c): bool => $this->articles->slugExists($c, exceptId: $article->id()))->toString();
            }
            $article->changeSlug($slug);
        }

        $bodyHtml = $this->renderBody((string) ($validated['body'] ?? ''));
        $lead = $this->resolveLead((string) ($validated['lead'] ?? ''), $bodyHtml);

        $featured = \array_key_exists('featured_image', $input)
            ? ($this->resolveMediaId($input['featured_image']) ?? '')
            : null;

        $article->revise(
            title: (string) $validated['title'],
            lead: $lead !== '' ? $lead : null,
            body: $bodyHtml !== '' ? $bodyHtml : null,
            categoryId: $this->resolveCategoryId($input['category_id'] ?? null),
            featuredImage: $featured,
            gallery: \array_key_exists('gallery', $input) ? $this->normalizeGallery($input['gallery']) : null,
            seo: $this->buildSeo($validated, (string) $validated['title'], $lead),
        );

        $this->articles->save($article);
        $this->syncTags($article->id(), $tagNames);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    // ─── workflow (DOC-092 §3) ────────────────────────────────────────────

    /** نویسنده → ارسال برای بازبینی. */
    public function submitForReview(Article $article): Article
    {
        $article->submitForReview();
        $this->articles->save($article);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    /** ویراستار → تأیید (با زمان‌بندی اختیاری). */
    public function approve(Article $article, int $editorId, ?\DateTimeImmutable $scheduledAt = null): Article
    {
        $article->approve($editorId, $scheduledAt);
        $this->articles->save($article);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    /** ویراستار → رد (بازگشت به نویسنده با دلیل). */
    public function reject(Article $article, int $editorId, string $reason = ''): Article
    {
        $article->reject($editorId, $reason);
        $this->articles->save($article);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    /** سردبیر → انتشار (فوری یا backdating). */
    public function publish(Article $article, int $editorId, ?\DateTimeImmutable $at = null): Article
    {
        $article->publish($editorId, $at);
        $this->articles->save($article);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    public function archive(Article $article): Article
    {
        $article->archive();
        $this->articles->save($article);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    /** علامت‌گذاری/برداشتن خبر فوری (DOC-092 §6). */
    public function setBreaking(Article $article, bool $breaking): Article
    {
        $article->setBreaking($breaking);
        $this->articles->save($article);
        $this->flushEvents($article);
        $this->invalidateCache();

        return $article;
    }

    public function setFeatured(Article $article, bool $featured): Article
    {
        $article->setFeatured($featured);
        $this->articles->save($article);
        $this->invalidateCache();

        return $article;
    }

    /** حذف نرم. */
    public function trash(Article $article): Article
    {
        $article->softDelete();
        $this->articles->save($article);
        Event::dispatch('news.article.deleted', ['article_id' => $article->id(), 'slug' => $article->slug()]);
        $this->invalidateCache();

        return $article;
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
            'title' => ['required', 'string', 'max:300'],
            'slug' => ['nullable', 'string', 'max:255'],
            'lead' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:300'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'title' => 'عنوان',
            'lead' => 'لید',
            'body' => 'بدنه',
        ]);
    }

    private function resolveSlug(string $explicit, string $title): string
    {
        $base = $explicit !== '' ? Slug::slugify($explicit) : Slug::slugify($title);
        if ($base === '') {
            $base = 'news';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->articles->slugExists($candidate))->toString();
    }

    /** markdown/html → HTML سانیتیزه‌شده (دو لایه — هم‌راستا با ماژول blog). */
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
            'allowed_tags' => [...(array) (\function_exists('config') ? (config('security.sanitizers.' . $profile . '.allowed_tags') ?? []) : []), ...$extraTags],
        ]);
    }

    private function resolveLead(string $explicit, string $bodyHtml): string
    {
        if (\trim($explicit) !== '') {
            return \trim($explicit);
        }
        if ($bodyHtml === '') {
            return '';
        }

        return $this->excerpts->fromHtml($bodyHtml, 180);
    }

    private function resolveCategoryId(mixed $categoryId): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }
        $id = (int) $categoryId;

        return $this->categories->findById($id) !== null ? $id : null;
    }

    private function resolveMediaId(mixed $mediaId): ?string
    {
        if (!\is_string($mediaId) || $mediaId === '') {
            return null;
        }

        return \preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $mediaId) === 1 ? $mediaId : null;
    }

    /** @return list<string> */
    private function normalizeGallery(mixed $gallery): array
    {
        if (\is_string($gallery) && $gallery !== '') {
            $decoded = \json_decode($gallery, true);
            $gallery = \is_array($decoded) ? $decoded : (\preg_split('/[,،\s]+/u', $gallery) ?: []);
        }
        if (!\is_array($gallery)) {
            return [];
        }

        $out = [];
        foreach ($gallery as $item) {
            $id = $this->resolveMediaId(\is_string($item) ? $item : null);
            if ($id !== null) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $input */
    private function applyGallery(Article $article, mixed $gallery): void
    {
        if ($gallery === null) {
            return;
        }
        $normalized = $this->normalizeGallery($gallery);
        if ($normalized === []) {
            return;
        }
        $article->revise(
            title: $article->title(),
            lead: $article->lead(),
            body: $article->body(),
            categoryId: $article->categoryId(),
            gallery: $normalized,
        );
        $article->pullEvents();   // revise رویداد ویرایش ثبت می‌کند — در create نویز نکن
        $this->articles->save($article);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function buildSeo(array $validated, string $title, string $lead): SeoMetadata
    {
        return SeoMetadata::make(
            title: (string) ($validated['seo_title'] ?? '') !== '' ? (string) $validated['seo_title'] : $title,
            plainBody: $lead,
            description: (string) ($validated['seo_description'] ?? ''),
            ogType: 'article',
        );
    }

    /** @param list<string> $tagNames */
    private function syncTags(string $articleId, array $tagNames): void
    {
        $ids = [];
        foreach ($tagNames as $name) {
            $name = \trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tag = $this->tags->findOrCreate($name, Slug::slugify($name));
            if ($tag->id() !== null) {
                $ids[] = $tag->id();
            }
        }

        $this->tags->syncArticle($articleId, $ids);
    }

    /** dispatch رویدادهای دامنهٔ ثبت‌شده (نام‌محور — DOC-092 §3/§6). */
    private function flushEvents(Article $article): void
    {
        foreach ($article->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }

    /** invalidate کش فهرست/صفحات با tag (DOC-092 §5). */
    private function invalidateCache(): void
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true)) {
            return;
        }

        try {
            Cache::tags(['news'])->flush();
        } catch (\Throwable) {
            // درایور بدون پشتیبانی tag → TTL کوتاه خودش کش را تازه می‌کند
        }
    }
}
