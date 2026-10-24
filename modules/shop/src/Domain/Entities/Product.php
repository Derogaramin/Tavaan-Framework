<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * Product — ریشهٔ Aggregate کاتالوگ (DOC-093 §1.2).
 */

namespace Modules\Shop\Domain\Entities;

use Modules\Comment\Contracts\CommentableInterface;
use Modules\Media\Contracts\MediaAttachableInterface;
use Modules\Shop\Domain\Enums\ProductStatus;
use Modules\Shop\Domain\Events\ProductWasUpdated;
use Modules\Shop\Domain\ValueObjects\Money;

/**
 * Product — محصول با قیمت/موجودی/variantها.
 *
 * 🔒 قیمت‌ها Money (int minor-unit — ADR-0013). موجودی در خود محصول
 * (تک‌انباره) + variantها؛ عملیات موجودی از InventoryService (race-safe).
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class Product implements CommentableInterface, MediaAttachableInterface
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $title,
        private ?string $summary,
        private ?string $description,
        private int $categoryId,
        private ?int $brandId,
        private Money $price,
        private ?Money $salePrice,
        private string $currency,
        private ?string $sku,
        private int $stock,
        private ProductStatus $status,
        private bool $isFeatured,
        private array $attributes,
        private ?string $featuredImage,
        private ?float $weight,
        private int $viewCount,
        private int $soldCount,
        private float $ratingAvg,
        private int $lowStockThreshold,
        private ?\DateTimeImmutable $publishedAt,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /** ساخت محصول پیش‌نویس (شناسهٔ ULID). */
    public static function draft(
        string $slug,
        string $title,
        int $categoryId,
        Money $price,
        ?string $summary = null,
        ?string $description = null,
        ?string $sku = null,
        int $stock = 0,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            slug: $slug,
            title: $title,
            summary: $summary,
            description: $description,
            categoryId: $categoryId,
            brandId: null,
            price: $price,
            salePrice: null,
            currency: $price->currency,
            sku: $sku,
            stock: \max(0, $stock),
            status: ProductStatus::Draft,
            isFeatured: false,
            attributes: [],
            featuredImage: null,
            weight: null,
            viewCount: 0,
            soldCount: 0,
            ratingAvg: 0.0,
            lowStockThreshold: 5,
            publishedAt: null,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * بازسازی از ردیف دیتابیس.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $currency = (string) ($row['currency'] ?? 'IRR');
        $attributes = [];
        if (isset($row['attributes']) && \is_string($row['attributes']) && $row['attributes'] !== '') {
            $decoded = \json_decode($row['attributes'], true);
            $attributes = \is_array($decoded) ? $decoded : [];
        } elseif (isset($row['attributes']) && \is_array($row['attributes'])) {
            $attributes = $row['attributes'];
        }

        return new self(
            id: (string) ($row['id'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            summary: isset($row['summary']) ? (string) $row['summary'] : null,
            description: isset($row['description']) ? (string) $row['description'] : null,
            categoryId: (int) ($row['category_id'] ?? 0),
            brandId: isset($row['brand_id']) && $row['brand_id'] !== null ? (int) $row['brand_id'] : null,
            price: Money::of((int) ($row['price'] ?? 0), $currency),
            salePrice: isset($row['sale_price']) && $row['sale_price'] !== null
                ? Money::of((int) $row['sale_price'], $currency)
                : null,
            currency: $currency,
            sku: isset($row['sku']) && $row['sku'] !== null && $row['sku'] !== '' ? (string) $row['sku'] : null,
            stock: (int) ($row['stock'] ?? 0),
            status: ProductStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? ProductStatus::Draft,
            isFeatured: (bool) ($row['is_featured'] ?? false),
            attributes: $attributes,
            featuredImage: isset($row['featured_image']) && \is_string($row['featured_image']) && $row['featured_image'] !== ''
                ? $row['featured_image']
                : null,
            weight: isset($row['weight']) && $row['weight'] !== null ? (float) $row['weight'] : null,
            viewCount: (int) ($row['view_count'] ?? 0),
            soldCount: (int) ($row['sold_count'] ?? 0),
            ratingAvg: (float) ($row['rating_avg'] ?? 0),
            lowStockThreshold: (int) ($row['low_stock_threshold'] ?? 5),
            publishedAt: self::date($row['published_at'] ?? null),
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    // ─── رفتارسازی دامنه ──────────────────────────────────────────────────

    /**
     * ویرایش محتوایی/قیمت (بدون تغییر موجودی — آن از InventoryService است).
     *
     * @param array<string, mixed> $attributes
     */
    public function revise(
        string $title,
        ?string $summary,
        ?string $description,
        int $categoryId,
        Money $price,
        ?Money $salePrice,
        ?string $sku = null,
        ?string $featuredImage = null,
        ?array $attributes = null,
        ?float $weight = null,
        ?bool $isFeatured = null,
        ?int $lowStockThreshold = null,
        ?\DateTimeImmutable $at = null,
    ): void {
        if ($salePrice !== null && $salePrice->currency !== $price->currency) {
            throw new \InvalidArgumentException('ارز قیمت و قیمت فروش باید یکسان باشد.');
        }
        if ($price->minor < 0) {
            throw new \InvalidArgumentException('قیمت منفی نامجاز است.');
        }

        $this->title = $title;
        $this->summary = $summary;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->price = $price;
        $this->salePrice = $salePrice;
        $this->currency = $price->currency;
        if ($sku !== null) {
            $this->sku = $sku !== '' ? $sku : null;
        }
        if ($featuredImage !== null) {
            $this->featuredImage = $featuredImage !== '' ? $featuredImage : null;
        }
        if ($attributes !== null) {
            $this->attributes = $attributes;
        }
        if ($weight !== null) {
            $this->weight = $weight >= 0 ? $weight : null;
        }
        if ($isFeatured !== null) {
            $this->isFeatured = $isFeatured;
        }
        if ($lowStockThreshold !== null) {
            $this->lowStockThreshold = \max(0, $lowStockThreshold);
        }
        $this->touch($at);

        $this->recordedEvents[] = new ProductWasUpdated(
            productId: $this->id,
            slug: $this->slug,
            title: $this->title,
            status: $this->status,
            priceMinor: $this->price->minor,
            currency: $this->currency,
        );
    }

    public function activate(?\DateTimeImmutable $at = null): void
    {
        if ($this->status === ProductStatus::Active) {
            return;
        }
        $this->status = ProductStatus::Active;
        $this->publishedAt ??= $at ?? $this->now();
        $this->touch($at);
        $this->recordUpdateEvent();
    }

    public function archive(?\DateTimeImmutable $at = null): void
    {
        if ($this->status === ProductStatus::Archived) {
            return;
        }
        $this->status = ProductStatus::Archived;
        $this->touch($at);
        $this->recordUpdateEvent();
    }

    public function backToDraft(?\DateTimeImmutable $at = null): void
    {
        $this->status = ProductStatus::Draft;
        $this->touch($at);
        $this->recordUpdateEvent();
    }

    public function changeSlug(string $slug): void
    {
        $slug = \trim($slug);
        if ($slug === '') {
            throw new \DomainException('slug نمی‌تواند خالی باشد.');
        }
        $this->slug = $slug;
    }

    public function incrementViews(int $by = 1): void
    {
        $this->viewCount = \max(0, $this->viewCount + $by);
    }

    public function incrementSold(int $by = 1): void
    {
        $this->soldCount = \max(0, $this->soldCount + $by);
    }

    public function softDelete(?\DateTimeImmutable $at = null): void
    {
        $this->deletedAt = $at ?? $this->now();
        $this->touch($at);
    }

    // ─── پرسش‌های دامنه ───────────────────────────────────────────────────

    /** قیمت مؤثر (فروش اگر فعال و کمتر از قیمت پایه). */
    public function effectivePrice(): Money
    {
        if ($this->salePrice !== null && $this->salePrice->minor < $this->price->minor) {
            return $this->salePrice;
        }

        return $this->price;
    }

    public function isOnSale(): bool
    {
        return $this->salePrice !== null && $this->salePrice->minor < $this->price->minor;
    }

    /** آیا قابل خرید است (فعال + موجود)؟ */
    public function isPurchasable(): bool
    {
        return $this->status->isPublic() && $this->deletedAt === null && $this->stock > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->lowStockThreshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    // ─── قراردادها ────────────────────────────────────────────────────────

    public function commentableType(): string
    {
        return 'shop_product';
    }

    public function commentableId(): string
    {
        return $this->id;
    }

    public function commentsOpen(): bool
    {
        return $this->status->isPublic() && $this->deletedAt === null;
    }

    public function mediaSubjectType(): string
    {
        return 'shop_product';
    }

    public function mediaSubjectId(): string
    {
        return $this->id;
    }

    // ─── Getters ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function categoryId(): int
    {
        return $this->categoryId;
    }

    public function brandId(): ?int
    {
        return $this->brandId;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function salePrice(): ?Money
    {
        return $this->salePrice;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function sku(): ?string
    {
        return $this->sku;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    public function status(): ProductStatus
    {
        return $this->status;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function featuredImage(): ?string
    {
        return $this->featuredImage;
    }

    public function weight(): ?float
    {
        return $this->weight;
    }

    public function viewCount(): int
    {
        return $this->viewCount;
    }

    public function soldCount(): int
    {
        return $this->soldCount;
    }

    public function ratingAvg(): float
    {
        return $this->ratingAvg;
    }

    public function lowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isTrashed(): bool
    {
        return $this->deletedAt !== null;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * نگاشت به ردیف دیتابیس.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'price' => $this->price->minor,
            'sale_price' => $this->salePrice?->minor,
            'currency' => $this->currency,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'status' => $this->status->value,
            'is_featured' => $this->isFeatured ? 1 : 0,
            'attributes' => $this->attributes === [] ? null : (string) \json_encode($this->attributes, JSON_UNESCAPED_UNICODE),
            'featured_image' => $this->featuredImage,
            'weight' => $this->weight,
            'view_count' => $this->viewCount,
            'sold_count' => $this->soldCount,
            'rating_avg' => $this->ratingAvg,
            'low_stock_threshold' => $this->lowStockThreshold,
            'published_at' => self::fmt($this->publishedAt),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
            'deleted_at' => self::fmt($this->deletedAt),
        ];
    }

    // ─── خصوصی (توسط InventoryService/Repository صدا زده می‌شود) ─────────

    /**
     * به‌روزرسانی موجودی در حافظه (پس از موفقیت DB در InventoryService).
     *
     * @internal
     */
    public function applyStockDelta(int $delta): void
    {
        $this->stock = \max(0, $this->stock + $delta);
    }

    private function recordUpdateEvent(): void
    {
        $this->recordedEvents[] = new ProductWasUpdated(
            productId: $this->id,
            slug: $this->slug,
            title: $this->title,
            status: $this->status,
            priceMinor: $this->price->minor,
            currency: $this->currency,
        );
    }

    private function touch(?\DateTimeImmutable $at = null): void
    {
        $this->updatedAt = $at ?? $this->now();
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (!\is_string($value) || \trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    private static function fmt(?\DateTimeImmutable $dt): ?string
    {
        return $dt?->format('Y-m-d H:i:s.v');
    }
}
