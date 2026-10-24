<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * AdminProductService — نوشتن کاتالوگ در پنل (CRUD + variant + وضعیت).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\Content\Domain\Services\ExcerptGenerator;
use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\Slug;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Entities\ProductVariant;
use Modules\Shop\Domain\Entities\ShopCategory;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Modules\Shop\Domain\Services\InventoryService;
use Modules\Shop\Domain\ValueObjects\Money;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class AdminProductService
{
    /** @param array<string, mixed> $config کلید config('shop') */
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ShopCategoryRepositoryInterface $categories,
        private readonly InventoryService $inventory,
        private readonly Markdown $markdown,
        private readonly ExcerptGenerator $excerpts,
        private readonly array $config = [],
    ) {
    }

    /** ساخت محصول (پیش‌نویس) — @return array<string, mixed> ورودی پاک‌شده */
    public function store(array $input, ?int $userId = null): Product
    {
        $validated = $this->validateInput($input);
        $currency = (string) ($this->config['currency'] ?? 'IRR');

        $product = Product::draft(
            slug: $this->uniqueSlug((string) $validated['title'], ''),
            title: (string) $validated['title'],
            categoryId: (int) $validated['category_id'],
            price: Money::of((int) $validated['price'], $currency),
            summary: $this->renderSummary((string) ($validated['summary'] ?? '')) ?: null,
            description: $this->renderDescription((string) ($validated['description'] ?? '')) ?: null,
            sku: isset($validated['sku']) && (string) $validated['sku'] !== '' ? (string) $validated['sku'] : null,
            stock: (int) ($validated['stock'] ?? 0),
        );

        $this->applyOptionalFields($product, $validated, $currency);
        $this->products->save($product);
        $this->flushEvents($product);
        $this->invalidateCache();

        return $product;
    }

    public function update(Product $product, array $input): Product
    {
        $validated = $this->validateInput($input);
        $currency = (string) ($this->config['currency'] ?? 'IRR');

        if (isset($input['slug']) && \trim((string) $input['slug']) !== '' && \trim((string) $input['slug']) !== $product->slug()) {
            $slug = Slug::fromRaw(\trim((string) $input['slug']))->toString();
            if ($this->products->slugExists($slug, $product->id())) {
                throw new ValidationException(errors: ['slug' => ['این slug پیش‌تر استفاده شده است.']], messageId: 'shop::messages.slug_taken');
            }
            $product->changeSlug($slug);
        }

        $product->revise(
            title: (string) $validated['title'],
            summary: $this->renderSummary((string) ($validated['summary'] ?? '')) ?: null,
            description: $this->renderDescription((string) ($validated['description'] ?? '')) ?: null,
            categoryId: (int) $validated['category_id'],
            price: Money::of((int) $validated['price'], $currency),
            salePrice: isset($validated['sale_price']) && $validated['sale_price'] !== null && $validated['sale_price'] !== ''
                ? Money::of((int) $validated['sale_price'], $currency)
                : null,
            sku: isset($validated['sku']) ? (string) $validated['sku'] : null,
            featuredImage: isset($validated['featured_image']) ? (string) $validated['featured_image'] : null,
            attributes: $this->parseAttributes($input['attributes'] ?? null),
            weight: isset($validated['weight']) && $validated['weight'] !== null && $validated['weight'] !== ''
                ? (float) $validated['weight']
                : null,
            isFeatured: isset($validated['is_featured']) ? $this->truthy($validated['is_featured']) : null,
            lowStockThreshold: isset($validated['low_stock_threshold']) ? (int) $validated['low_stock_threshold'] : null,
        );

        // موجودی — از راه InventoryService (با حرکت‌نامه)
        if (isset($input['stock']) && (int) $input['stock'] !== $product->stock()) {
            $delta = (int) $input['stock'] - $product->stock();
            $this->inventory->adjust($product->id(), null, $delta, 'ویرایش از پنل');
            $product->applyStockDelta($delta);
        }

        $this->products->save($product);
        $this->flushEvents($product);
        $this->invalidateCache();

        return $product;
    }

    public function setStatus(Product $product, string $status): Product
    {
        match ($status) {
            'active' => $product->activate(),
            'archived' => $product->archive(),
            'draft' => $product->backToDraft(),
            default => throw new ValidationException(
                errors: ['status' => ['وضعیت نامعتبر.']],
                messageId: 'shop::messages.invalid_status',
            ),
        };

        $this->products->save($product);
        $this->flushEvents($product);
        $this->invalidateCache();

        return $product;
    }

    public function softDelete(Product $product): void
    {
        $product->softDelete();
        $this->products->save($product);
        $this->flushEvents($product);
        $this->invalidateCache();

        Event::dispatch('shop.product.deleted', ['product_id' => $product->id(), 'slug' => $product->slug()]);
    }

    // ─── variantها ────────────────────────────────────────────────────────

    public function addVariant(Product $product, array $input): ProductVariant
    {
        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:190'],
            'price_delta' => ['required', 'integer'],
            'stock' => ['required', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:64'],
            'options' => ['nullable'],
        ], attributes: ['title' => 'عنوان تنوع', 'price_delta' => 'اختلاف قیمت', 'stock' => 'موجودی']);

        $options = $this->parseOptions($input['options'] ?? null);

        $variant = ProductVariant::create(
            productId: $product->id(),
            title: (string) $validated['title'],
            priceDelta: Money::of((int) $validated['price_delta'], $product->currency()),
            stock: (int) $validated['stock'],
            sku: isset($validated['sku']) && (string) $validated['sku'] !== '' ? (string) $validated['sku'] : null,
            options: $options,
        );
        $this->products->saveVariant($variant);
        $this->invalidateCache();

        return $variant;
    }

    public function removeVariant(Product $product, string $variantId): void
    {
        $variant = $this->products->findVariant($variantId);
        if ($variant === null || $variant->productId() !== $product->id()) {
            throw new ValidationException(messageId: 'shop::messages.variant_invalid');
        }

        $this->products->deleteVariant($variantId);
        $this->invalidateCache();
    }

    // ─── دسته‌ها ──────────────────────────────────────────────────────────

    public function storeCategory(string $title, ?int $parentId = null, ?string $description = null): ShopCategory
    {
        $category = ShopCategory::create(
            title: \trim($title),
            slug: $this->uniqueCategorySlug($title),
            parentId: $parentId,
            description: $description !== null && \trim($description) !== '' ? \trim($description) : null,
        );
        $this->categories->save($category);
        $this->invalidateCache();

        return $category;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function validateInput(array $input): array
    {
        return Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'category_id' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:64'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'featured_image' => ['nullable', 'string', 'max:26'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'is_featured' => ['nullable'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'attributes' => ['nullable'],
            'status' => ['nullable', 'in:draft,active,archived'],
        ], attributes: [
            'title' => 'عنوان محصول',
            'category_id' => 'دسته',
            'price' => 'قیمت',
            'sale_price' => 'قیمت فروش',
            'stock' => 'موجودی',
        ]);
    }

    private function applyOptionalFields(Product $product, array $validated, string $currency): void
    {
        $salePrice = null;
        if (isset($validated['sale_price']) && $validated['sale_price'] !== null && $validated['sale_price'] !== '') {
            $salePrice = Money::of((int) $validated['sale_price'], $currency);
        }

        $product->revise(
            title: $product->title(),
            summary: $product->summary(),
            description: $product->description(),
            categoryId: $product->categoryId(),
            price: $product->price(),
            salePrice: $salePrice,
            sku: $product->sku(),
            featuredImage: isset($validated['featured_image']) ? (string) $validated['featured_image'] : null,
            attributes: $this->parseAttributes($validated['attributes'] ?? null),
            weight: isset($validated['weight']) && $validated['weight'] !== null && $validated['weight'] !== ''
                ? (float) $validated['weight']
                : null,
            isFeatured: isset($validated['is_featured']) ? $this->truthy($validated['is_featured']) : null,
            lowStockThreshold: isset($validated['low_stock_threshold']) ? (int) $validated['low_stock_threshold'] : null,
        );

        if (($validated['status'] ?? 'draft') === 'active') {
            $product->activate();
        }
    }

    private function uniqueSlug(string $title, string $explicit): string
    {
        if ($explicit !== '') {
            $base = Slug::fromRaw($explicit)->toString();
        } else {
            $base = Slug::of($title)->toString();
            if ($base === '') {
                $base = 'product-' . \strtolower(\substr(\bin2hex(\random_bytes(4)), 0, 8));
            }
        }

        $slug = $base;
        $suffix = 2;
        while ($this->products->slugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $title): string
    {
        $base = Slug::of($title)->toString();
        if ($base === '') {
            $base = 'cat-' . \strtolower(\substr(\bin2hex(\random_bytes(3)), 0, 6));
        }

        $slug = $base;
        $suffix = 2;
        while ($this->categories->slugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function renderSummary(string $raw): string
    {
        if (\trim($raw) === '') {
            return '';
        }

        $html = $this->markdown->toHtml($raw);
        $profile = (string) ($this->config['sanitize']['profile'] ?? 'rich_text');
        $allowed = ['p', 'br', 'strong', 'em', 'b', 'i', 'a', 'code'];
        foreach ((array) ($this->config['sanitize']['extra_tags'] ?? []) as $tag) {
            if (\is_string($tag)) {
                $allowed[] = $tag;
            }
        }

        return Security::sanitize($html, $profile, ['allowed_tags' => $allowed]);
    }

    private function renderDescription(string $raw): string
    {
        return $this->renderSummary($raw);
    }

    /** @return array<string, mixed>|null */
    private function parseAttributes(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (\is_array($raw)) {
            return $raw;
        }
        if (\is_string($raw)) {
            $decoded = \json_decode($raw, true);

            return \is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** @return array<string, string> */
    private function parseOptions(mixed $raw): array
    {
        $parsed = $this->parseAttributes($raw);
        if ($parsed === null) {
            return [];
        }

        $out = [];
        foreach ($parsed as $key => $value) {
            if (\is_string($key) && (\is_string($value) || \is_int($value))) {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    private function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array((string) $value, ['1', 'on', 'true', 'yes'], true);
    }

    private function flushEvents(Product $product): void
    {
        foreach ($product->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }

    private function invalidateCache(): void
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true)) {
            return;
        }

        try {
            Cache::tags(['shop'])->flush();
        } catch (\Throwable) {
            // درایور بدون tag
        }
    }
}
