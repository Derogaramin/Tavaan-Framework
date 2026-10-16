<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Tests\Unit;

use Tavaan\Engine\Database\Grammars\MySqlGrammar;
use Tavaan\Engine\Database\Grammars\PostgresGrammar;
use Tavaan\Engine\Database\Grammars\SqliteGrammar;
use Tavaan\Engine\Database\QueryBuilder;
use Tavaan\Engine\Database\Testing\FakeConnection;
use Tavaan\Testing\TestCase;

/**
 * تست QueryBuilder/Grammar — DOC-047 §12 (T-03: SQL درست در ۳ درایور).
 */
final class QueryBuilderCompileTest extends TestCase
{
    private function builder(string $driver = 'mysql'): QueryBuilder
    {
        $grammar = match ($driver) {
            'pgsql' => new PostgresGrammar(''),
            'sqlite' => new SqliteGrammar(''),
            default => new MySqlGrammar(''),
        };

        // Builder بدون اتصال واقعی — فقط compile (از FakeConnection استفاده می‌شود)
        return new QueryBuilder(new FakeConnection($grammar, $driver));
    }

    public function testBasicSelect(): void
    {
        $sql = $this->builder()->table('users')->select('id', 'name')->where('is_active', true)->toSql();
        $this->assertSame('select `id`, `name` from `users` where `is_active` = ?', $sql);
    }

    public function testSelectDriverQuoting(): void
    {
        $this->assertSame('select "id" from "users"', $this->builder('pgsql')->table('users')->select('id')->toSql());
        $this->assertSame('select "id" from "users"', $this->builder('sqlite')->table('users')->select('id')->toSql());
    }

    public function testNestedWhereAndOr(): void
    {
        $b = $this->builder()->table('users')
            ->where('is_active', true)
            ->where(fn (QueryBuilder $q) => $q->where('age', '>=', 18)->orWhere('vip', true));

        $this->assertSame('select * from `users` where `is_active` = ? and (`age` >= ? or `vip` = ?)', $b->toSql());
        $this->assertSame([true, 18, true], $b->getBindings());
    }

    public function testWhereInSubQuery(): void
    {
        $sub = $this->builder()->table('order_items')->select('order_id')->where('product_id', 5);
        $b = $this->builder()->table('orders')->whereIn('id', $sub);

        $this->assertSame('select * from `orders` where `id` in (select `order_id` from `order_items` where `product_id` = ?)', $b->toSql());
        $this->assertSame([5], $b->getBindings());
    }

    public function testJoinGroupHavingOrder(): void
    {
        $b = $this->builder()->table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->selectRaw('o.status, COUNT(DISTINCT o.id) as cnt')
            ->whereBetween('o.created_at', ['2026-01-01', '2026-02-01'])
            ->groupBy('o.status')
            ->having('cnt', '>', 10)
            ->orderByDesc('cnt');

        $expected = 'select o.status, COUNT(DISTINCT o.id) as cnt from `orders` as `o` '
            . 'inner join `users` as `u` on `u`.`id` = `o`.`user_id` '
            . 'left join `order_items` as `oi` on `oi`.`order_id` = `o`.`id` '
            . 'where `o`.`created_at` between ? and ? '
            . 'group by `o`.`status` having `cnt` > ? order by `cnt` desc';

        $this->assertSame($expected, $b->toSql());
        $this->assertSame(['2026-01-01', '2026-02-01', 10], $b->getBindings());
    }

    public function testUnionAndCte(): void
    {
        $b = $this->builder()
            ->withCte('recent', fn (QueryBuilder $q) => $q->table('orders')->where('created_at', '>', 'x'))
            ->table('posts')->select('id')->union($this->builder()->table('pages')->select('id'));

        $sql = $b->toSql();
        $this->assertStringContainsString('with `recent` as (select * from `orders` where `created_at` > ?)', $sql);
        $this->assertStringContainsString('union select `id` from `pages`', $sql);
        $this->assertSame(['x'], $b->getBindings());
    }

    public function testLockClausesPerDriver(): void
    {
        $this->assertStringContainsString('for update', $this->builder('mysql')->table('t')->where('id', 1)->forUpdate()->toSql());
        $this->assertStringContainsString('lock in share mode', $this->builder('mysql')->table('t')->sharedLock()->toSql());
        $this->assertStringContainsString('for share', $this->builder('pgsql')->table('t')->sharedLock()->toSql());
        $this->assertStringNotContainsString('for update', $this->builder('sqlite')->table('t')->forUpdate()->toSql());
    }

    public function testUpsertPerDriver(): void
    {
        $mysql = $this->builder('mysql')->table('users');
        $sqlM = $mysql->grammar()->compileUpsert('users', ['email', 'name'], ['email'], ['name'], 2);
        $this->assertStringContainsString('on duplicate key update `name` = values(`name`)', $sqlM);

        $pg = $this->builder('pgsql')->grammar()->compileUpsert('users', ['email', 'name'], ['email'], ['name'], 1);
        $this->assertStringContainsString('on conflict ("email") do update set "name" = excluded."name"', $pg);

        $lite = $this->builder('sqlite')->grammar()->compileUpsert('users', ['email', 'name'], ['email'], ['name'], 1);
        $this->assertStringContainsString('on conflict ("email") do update set "name" = excluded."name"', $lite);
    }

    public function testInsertOrIgnorePerDriver(): void
    {
        $this->assertStringContainsString('insert ignore into', $this->builder('mysql')->grammar()->compileInsert('t', rows: [['a' => 1]], mode: 'ignore-mysql'));
        $this->assertStringContainsString('insert or ignore into', $this->builder('sqlite')->grammar()->compileInsert('t', rows: [['a' => 1]], mode: 'ignore-sqlite'));
        $this->assertStringContainsString('on conflict do nothing', $this->builder('pgsql')->grammar()->compileInsert('t', rows: [['a' => 1]], mode: 'ignore-pgsql'));
    }

    public function testOperatorAllowlist(): void
    {
        $b = $this->builder()->table('users');
        $this->expectException(\Tavaan\Engine\Database\Exceptions\UnsafeQueryException::class);
        $b->where('id', '= 1; DROP TABLE users; --', 'x');
    }

    public function testOrderByDirectionAllowlist(): void
    {
        $this->expectException(\Tavaan\Engine\Database\Exceptions\UnsafeQueryException::class);
        $this->builder()->table('users')->orderBy('id', 'asc; DROP TABLE users');
    }

    public function testIdentifierEscaping(): void
    {
        $g = new MySqlGrammar('tvn_');
        $this->assertSame('`tvn_users`', $g->wrapTable('users'));
        $this->assertSame('`we``ird`', $g->wrapValue('we`ird'));
        $this->assertSame('`a`.`b`', $g->wrap('a.b'));
        $this->assertSame('*', $g->wrap('*'));
        $this->assertSame('`tvn_users` as `u`', $g->wrapTable('users as u'));
    }
}
