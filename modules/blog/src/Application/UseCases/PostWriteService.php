<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول وبلاگ
 * PostWriteService — نوشتن مقاله: ساخت/ویرایش/انتشار/زمان‌بندی/حذف.
 */

namespace Modules\Blog\Application\UseCases;

use Modules\Blog\Domain\Entities\Post;
use Modules\Blog\Domain\Repositories\CategoryRepositoryInterface;
use Modules\Blog\Domain\Repositories\PostRepositoryInterface;
use Modules\Blog\Domain\Repositories\TagRepositoryInterface;
use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Enums\ContentStatus;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\SeoMetadata;
use Modules\Content\Domain\ValueObjects\Slug;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * PostWriteService — مسیر نوشتن (DOC-091 §1/§5).
 *
 * زنجیرهٔ محتوا: markdown → HTML (escape-first) → Security::sanitize (لایهٔ دوم،
 * AC-BLOG-03). خلاصه و SEO خودکار از عنوان/بدنه پر می‌شوند.
 *
 * @api 1.0
 * @see modules/blog/README.md
 */
final class PostWriteService
{
    /**
     * @param array<string, mixed> $config کلید config('blog')
     */
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TagRepositoryInterface $tags,
        private readonly Markdown $markdown,
        private readonly ExcerptGenerator $excerpts,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت مقالهٔ پیش‌نویس.
     *
     * @param array<string, mixed> $input title, slug?, body?, excerpt?, category_id?, tags? (list<string> نام), featured_image?, seo_*?, status?
     * @param list<string>         $tagNames
     */
    public function create(array $input, int $authorId, array $tagNames = []): Post
    {
        $validated = $this->validate($input);

        $slug = $this->resolveSlug((string) ($validated['slug'] ?? ''), (string) $validated['title']);
        $bodyHtml = $this->renderBody((string) ($validated['body'] ?? ''));
        $excerpt = $this->resolveExcerpt((string) ($validated['excerpt'] ?? ''), $bodyHtml);

        $post = Post::draft(
            slug: $slug,
            title: (string) $validated['title'],
            authorId: $authorId,
            excerpt: $excerpt !== '' ? $excerpt : null,
            body: $bodyHtml !== '' ? $bodyHtml : null,
            categoryId: $this->resolveCategoryId($validated['category_id'] ?? null),
            featuredImage: $this->resolveFeaturedImage($input['featured_image'] ?? null),
            seo: $this->buildSeo($validated, (string) $validated['title'], $excerpt),
        );

        $this->posts->save($post);
        $this->syncTags($post->id(), $tagNames);

        $this->applyInitialStatus($post, (string) ($input['status'] ?? 'draft'), $input['published_at'] ?? null);

        return $post;
    }

    /**
     * ویرایش مقاله (بدون تغییر نویسنده).
     *
     * @param array<string, mixed> $input
     * @param list<string>         $tagNames
     */
    public function update(Post $post, array $input, array $tagNames = []): Post
    {
        $validated = $this->validate($input);

        $slug = Slug::slugify((string) ($validated['slug'] ?? $post->slug()));
        if ($slug !== '' && $slug !== $post->slug()) {
            if ($this->posts->slugExists($slug, exceptId: $post->id())) {
                $slug = Slug::unique($slug, fn (string $c): bool => $this->posts->slugExists($c, exceptId: $post->id()))->toString();
            }
            $post->changeSlug($slug);
        }

        $bodyHtml = $this->renderBody((string) ($validated['body'] ?? ''));
        $excerpt = $this->resolveExcerpt((string) ($validated['excerpt'] ?? ''), $bodyHtml);

        // featured_image: کلید غایب → حفظ فعلی؛ حاضر → جایگزینی/پاک‌سازی
        $featured = \array_key_exists('featured_image', $input)
            ? ($this->resolveFeaturedImage($input['featured_image']) ?? '')
            : null;

        $post->revise(
            title: (string) $validated['title'],
            excerpt: $excerpt !== '' ? $excerpt : null,
            body: $bodyHtml !== '' ? $bodyHtml : null,
            categoryId: $this->resolveCategoryId($validated['category_id'] ?? null),
            featuredImage: $featured,
            seo: $this->buildSeo($validated, (string) $validated['title'], $excerpt),
        );

        $this->posts->save($post);
        $this->syncTags($post->id(), $tagNames);
        $this->flushEvents($post);

        return $post;
    }

    /** انتشار فوری — رویداد blog.post.published (DOC-091 §5). */
    public function publish(Post $post, ?\DateTimeImmutable $at = null): Post
    {
        if (!$post->status()->canTransitionTo(ContentStatus::Published)) {
            throw new \DomainException('انتشار از وضعیت «' . $post->status()->labelFa() . '» مجاز نیست.');
        }

        $post->publish($at);
        $this->posts->save($post);
        $this->flushEvents($post);

        return $post;
    }

    /** زمان‌بندی انتشار (DOC-091 §6). */
    public function schedule(Post $post, \DateTimeImmutable $publishAt): Post
    {
        $post->schedule($publishAt);
        $this->posts->save($post);
        $this->flushEvents($post);

        return $post;
    }

    public function archive(Post $post): Post
    {
        $post->archive();
        $this->posts->save($post);
        $this->flushEvents($post);

        return $post;
    }

    public function backToDraft(Post $post): Post
    {
        $post->backToDraft();
        $this->posts->save($post);
        $this->flushEvents($post);

        return $post;
    }

    /** حذف نرم. */
    public function trash(Post $post): Post
    {
        $post->softDelete();
        $this->posts->save($post);
        Event::dispatch('blog.post.deleted', ['post_id' => $post->id(), 'slug' => $post->slug()]);

        return $post;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
        ];

        return Validator::validate($input, $rules, attributes: [
            'title' => 'عنوان',
            'body' => 'بدنه',
            'excerpt' => 'خلاصه',
        ]);
    }

    private function resolveSlug(string $explicit, string $title): string
    {
        $base = $explicit !== '' ? Slug::slugify($explicit) : Slug::slugify($title);
        if ($base === '') {
            $base = 'post';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->posts->slugExists($candidate))->toString();
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

    private function resolveCategoryId(mixed $categoryId): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }
        $id = (int) $categoryId;

        return $this->categories->findById($id) !== null ? $id : null;
    }

    private function resolveFeaturedImage(mixed $mediaId): ?string
    {
        if (!\is_string($mediaId) || $mediaId === '') {
            return null;
        }

        // CHAR(26) = ULID رسانه (DOC-091 §2: featured_image → tvn_media)
        return \preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $mediaId) === 1 ? $mediaId : null;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function buildSeo(array $validated, string $title, string $excerpt): SeoMetadata
    {
        return SeoMetadata::make(
            title: (string) ($validated['seo_title'] ?? '') !== '' ? (string) $validated['seo_title'] : $title,
            plainBody: $excerpt,
            description: (string) ($validated['seo_description'] ?? ''),
            keywords: (string) ($validated['seo_keywords'] ?? ''),
            ogType: (string) ($this->config['seo']['default_og_type'] ?? 'article'),
        );
    }

    /** @param list<string> $tagNames */
    private function syncTags(string $postId, array $tagNames): void
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

        $this->tags->syncPost($postId, $ids);
    }

    /** وضعیت اولیهٔ درخواستی (draft|published|scheduled) — فقط در create. */
    private function applyInitialStatus(Post $post, string $status, mixed $publishedAt): void
    {
        if ($status === 'published') {
            $this->publish($post);

            return;
        }

        if ($status === 'scheduled' && \is_string($publishedAt) && $publishedAt !== '') {
            try {
                $this->schedule($post, new \DateTimeImmutable($publishedAt, new \DateTimeZone('UTC')));
            } catch (\Exception) {
                // تاریخ نامعتبر → همان پیش‌نویس می‌ماند
            }
        }
    }

    /** dispatch رویدادهای دامنهٔ ثبت‌شده (نام‌محور — DOC-091 §5). */
    private function flushEvents(Post $post): void
    {
        foreach ($post->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
