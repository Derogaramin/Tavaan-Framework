<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqController — نمایش عمومی و رأی «مفید بود» (/faq).
 */

namespace Modules\Faq\Http\Controllers;

use Modules\Faq\Application\UseCases\FaqReadService;
use Modules\Faq\Application\UseCases\FaqWriteService;
use Modules\Faq\Domain\Entities\FaqCategory;
use Modules\Faq\Domain\Entities\FaqItem;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Session;

/**
 * FaqController — GET /faq + POST /faq/{id}/helpful + GET /api/faqs.
 *
 * 🔒 فقط پرسش‌های منتشرشده و غیرtrash نمایش داده می‌شوند.
 * پاسخ JSON با ساختار پایدار (data/meta) هم‌راستا با poll/comment.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqController
{
    public function __construct(
        private readonly FaqReadService $read,
        private readonly FaqWriteService $write,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /faq — درخت آکاردئونی با جست‌وجو (?q=) و فیلتر دسته (?cat=slug) */
    public function index(RequestInterface $request): mixed
    {
        $tree = $this->read->publicTree(
            (string) $request->query('q', ''),
            (string) $request->query('cat', ''),
        );

        return view('faq::faq-index', $tree + [
            'ok' => Session::pull('faq_helpful_ok'),
            'error' => Session::pull('faq_helpful_error'),
            'helpfulEnabled' => (bool) config('faq.helpful.enabled', true),
        ]);
    }

    /** POST /faq/{id}/helpful — رأی «مفید بود» (یک‌بار در هر نشست) */
    public function helpful(string $id, RequestInterface $request): mixed
    {
        if (!(bool) config('faq.helpful.enabled', true)) {
            throw new RouteNotFoundException(messageId: 'faq::messages.item_not_found', context: ['id' => $id]);
        }

        $item = $this->findPublicOr404($id);

        $guardKey = 'faq_helpful_' . $id;
        if (Session::get($guardKey) !== null) {
            return $this->respondDuplicate($request);
        }

        $value = (string) $request->input('value', '');
        if (!\in_array($value, ['yes', 'no'], true)) {
            if ($request->expectsJson()) {
                return $this->json(['errors' => ['value' => [trans('faq::messages.helpful_invalid')]]], 422);
            }

            Session::flash('faq_helpful_error', trans('faq::messages.helpful_invalid'));

            return redirect('/faq');
        }

        $updated = $this->write->registerHelpful($item, $value === 'yes');
        Session::put($guardKey, 1);

        if ($request->expectsJson()) {
            return $this->json([
                'data' => [
                    'helpful_yes' => $updated->helpfulYes(),
                    'helpful_no' => $updated->helpfulNo(),
                    'message' => trans('faq::messages.helpful_recorded'),
                ],
            ]);
        }

        Session::flash('faq_helpful_ok', trans('faq::messages.helpful_recorded'));

        return redirect('/faq');
    }

    /** GET /api/faqs — JSON درخت عمومی (با جست‌وجوی اختیاری ?q=) */
    public function apiIndex(RequestInterface $request): mixed
    {
        $tree = $this->read->publicTree(
            (string) $request->query('q', ''),
            (string) $request->query('cat', ''),
        );

        $groups = [];
        foreach ($tree['categories'] as $category) {
            $items = $tree['groups'][$category->id()] ?? [];
            if ($items === []) {
                continue;
            }
            $groups[] = [
                'category' => self::categoryToArray($category),
                'items' => \array_map(static fn (FaqItem $item): array => self::itemToArray($item), $items),
            ];
        }
        if ($tree['uncategorized'] !== []) {
            $groups[] = [
                'category' => null,
                'items' => \array_map(static fn (FaqItem $item): array => self::itemToArray($item), $tree['uncategorized']),
            ];
        }

        return $this->json([
            'data' => [
                'categories' => \array_map(
                    static fn (FaqCategory $category): array => self::categoryToArray($category),
                    $tree['categories'],
                ),
                'groups' => $groups,
                'total' => $tree['total'],
            ],
            'meta' => ['q' => $tree['q']],
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findPublicOr404(string $id): FaqItem
    {
        $item = $this->read->findItem($id);
        if ($item === null || !$item->isVisible()) {
            throw new RouteNotFoundException(messageId: 'faq::messages.item_not_found', context: ['id' => $id]);
        }

        return $item;
    }

    private function respondDuplicate(RequestInterface $request): mixed
    {
        if ($request->expectsJson()) {
            return $this->json(['message' => trans('faq::messages.helpful_already')], 429);
        }

        Session::flash('faq_helpful_error', trans('faq::messages.helpful_already'));

        return redirect('/faq');
    }

    /** پاسخ JSON از binding هسته `response.factory` (بدون ارجاع به موتور — ARCH-003). */
    private function json(mixed $data, int $status = 200): mixed
    {
        return $this->container->make('response.factory')->json($data, $status);
    }

    /** @return array<string, mixed> */
    private static function itemToArray(FaqItem $item): array
    {
        return [
            'id' => $item->id(),
            'question' => $item->question(),
            'answer' => $item->answer(),
            'category_id' => $item->categoryId(),
            'sort_order' => $item->sortOrder(),
            'helpful_yes' => $item->helpfulYes(),
            'helpful_no' => $item->helpfulNo(),
            'created_at' => $item->createdAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private static function categoryToArray(FaqCategory $category): array
    {
        return [
            'id' => $category->id(),
            'slug' => $category->slug(),
            'title' => $category->title(),
            'sort_order' => $category->sortOrder(),
        ];
    }
}
