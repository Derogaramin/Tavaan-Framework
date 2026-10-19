<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade;

use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\SchemaBuilder;

/**
 * تَوان | Tavaan — Upgrade
 * UpgradeRepository — وضعیت ارتقا در DB: patchهای اجراشده (idempotency) +
 * meta (نسخهٔ جاری، آخرین backup، نسخهٔ پیشین برای rollback).
 *
 * جداول: `tvn_upgrades` و `tvn_upgrade_meta` (ساخت خودکار در نخستین استفاده).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schema,
    ) {
    }

    /** ساخت جدول‌ها در صورت نبود. */
    public function createRepository(): void
    {
        if (!$this->schema->hasTable('tvn_upgrades')) {
            $this->schema->create('tvn_upgrades', static function (Blueprint $t): void {
                $t->string('id', 26)->primary();
                $t->string('version', 30);
                $t->string('patch', 190);
                $t->integer('batch')->default(1);
                $t->string('ran_at', 40);
            });
        }
        if (!$this->schema->hasTable('tvn_upgrade_meta')) {
            $this->schema->create('tvn_upgrade_meta', static function (Blueprint $t): void {
                $t->string('meta_key', 64)->primary();
                $t->text('meta_value')->nullable();
                $t->string('updated_at', 40);
            });
        }
    }

    // ─── patchها ──────────────────────────────────────────────────────────

    public function patchApplied(string $version, string $patch): bool
    {
        $this->createRepository();
        $row = $this->connection->table('tvn_upgrades')
            ->where('version', $version)
            ->where('patch', $patch)
            ->first();

        return $row !== null;
    }

    public function logPatch(string $version, string $patch, int $batch): void
    {
        $this->createRepository();
        $this->connection->table('tvn_upgrades')->insert([
            'id' => \Tavaan\Support\Ulid::generate()->toString(),
            'version' => $version,
            'patch' => $patch,
            'batch' => $batch,
            'ran_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array{version: string, patch: string, batch: int, ran_at: string}> */
    public function applied(): array
    {
        $this->createRepository();
        $rows = $this->connection->table('tvn_upgrades')->orderBy('ran_at')->get();

        return \array_map(static fn (array $row): array => [
            'version' => (string) $row['version'],
            'patch' => (string) $row['patch'],
            'batch' => (int) $row['batch'],
            'ran_at' => (string) $row['ran_at'],
        ], $rows);
    }

    public function nextBatch(): int
    {
        $this->createRepository();
        $row = $this->connection->selectOne('SELECT MAX(batch) AS max_batch FROM tvn_upgrades');

        return ((int) ($row['max_batch'] ?? 0)) + 1;
    }

    // ─── meta ─────────────────────────────────────────────────────────────

    public function getMeta(string $key, ?string $default = null): ?string
    {
        $this->createRepository();
        $row = $this->connection->table('tvn_upgrade_meta')->where('meta_key', $key)->first();

        return $row === null ? $default : (string) ($row['meta_value'] ?? $default);
    }

    public function setMeta(string $key, string $value): void
    {
        $this->createRepository();
        $this->connection->table('tvn_upgrade_meta')->updateOrInsert(
            ['meta_key' => $key],
            ['meta_value' => $value, 'updated_at' => \date('Y-m-d H:i:s')],
        );
    }
}
