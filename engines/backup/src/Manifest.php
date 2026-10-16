<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

/**
 * تَوان | Tavaan — Backup
 * Manifest — شرح‌نامهٔ پشتیبان (DOC-068 §6).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class Manifest
{
    /**
     * @param array{database: bool, files: list<string>} $contents
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version = '1.0.0',
        public readonly string $type = 'full',
        public readonly ?string $createdAt = null,
        public readonly int $size = 0,
        public readonly string $hash = '',
        public readonly array $contents = ['database' => false, 'files' => []],
        public readonly bool $encrypted = true,
        public readonly string $tavaanVersion = '',
        public readonly string $phpVersion = \PHP_VERSION,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'type' => $this->type,
            'created_at' => $this->createdAt,
            'size' => $this->size,
            'hash' => $this->hash,
            'contents' => $this->contents,
            'encrypted' => $this->encrypted,
            'tavaan_version' => $this->tavaanVersion,
            'php_version' => $this->phpVersion,
        ];
    }

    public function toJson(): string
    {
        return (string) \json_encode($this->toArray(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $contents = $data['contents'] ?? [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            version: (string) ($data['version'] ?? '1.0.0'),
            type: (string) ($data['type'] ?? 'full'),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            size: (int) ($data['size'] ?? 0),
            hash: (string) ($data['hash'] ?? ''),
            contents: [
                'database' => (bool) ($contents['database'] ?? false),
                'files' => \array_values(\array_map(strval(...), (array) ($contents['files'] ?? []))),
            ],
            encrypted: (bool) ($data['encrypted'] ?? true),
            tavaanVersion: (string) ($data['tavaan_version'] ?? ''),
            phpVersion: (string) ($data['php_version'] ?? \PHP_VERSION),
        );
    }

    public static function fromJson(string $json): ?self
    {
        $data = \json_decode($json, true);

        return \is_array($data) && isset($data['id']) ? self::fromArray($data) : null;
    }

    /** نام فایل آرشیو روی disk. */
    public function archiveName(): string
    {
        return $this->id . '.tvnbkp';
    }

    /** نام فایل manifest روی disk. */
    public function manifestName(): string
    {
        return $this->id . '.json';
    }
}
