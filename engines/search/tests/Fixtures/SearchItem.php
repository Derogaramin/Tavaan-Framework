<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Tests\Fixtures;

use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Search\Searchable;

/**
 * Fixture تست Search — مدل Searchable برای ایندکس خودکار (DOC-052 §5).
 *
 * @api internal
 */
class SearchItem extends Model
{
    use Searchable;

    protected string $table = 'search_items';

    protected string $keyType = 'bigint';

    public bool $incrementing = true;

    /** @var list<string> */
    protected array $fillable = ['title', 'summary', 'category_id', 'price', 'status'];

    public function searchIndex(): string
    {
        return 'items';
    }

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return [
            'title' => (string) ($this->getAttribute('title') ?? ''),
            'summary' => (string) ($this->getAttribute('summary') ?? ''),
            'category_id' => $this->getAttribute('category_id'),
            'price' => $this->getAttribute('price'),
            'status' => (string) ($this->getAttribute('status') ?? ''),
        ];
    }
}
