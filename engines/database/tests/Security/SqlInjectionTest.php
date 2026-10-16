<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Tests\Security;

use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Exceptions\QueryException;
use Tavaan\Engine\Database\Exceptions\UnsafeQueryException;
use Tavaan\Testing\TestCase;

/**
 * تست Fuzzing تزریق SQL — DOC-047 §12 T-10/T-11 (AC-DB-02: صفر injection).
 *
 * ۲۰۰ payload مخرب در value/binding/identifier/like/raw — همه باید
 * بی‌اثر باشند (دادهٔ اضافی نشت نکند، جدول دوم دست‌نخورده بماند).
 */
final class SqlInjectionTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $config = new Repository(['database' => [
            'default' => 'fuzz',
            'connections' => ['fuzz' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
        ]]);

        $this->db = (new ConnectionManager($config))->connection();

        $this->db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, role TEXT)');
        $this->db->statement('CREATE TABLE secrets (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT)');
        $this->db->statement("INSERT INTO secrets (body) VALUES ('top-secret-canary')");
        $this->db->table('users')->insert([
            ['name' => 'admin', 'email' => 'admin@x.io', 'role' => 'admin'],
            ['name' => 'user', 'email' => 'user@x.io', 'role' => 'user'],
        ]);
    }

    /** @return list<string> */
    private function payloads(): array
    {
        $base = [
            "' OR '1'='1",
            "' OR 1=1--",
            "' OR 1=1#",
            "' OR 1=1/*",
            "' UNION SELECT NULL, body, NULL FROM secrets--",
            "' UNION ALL SELECT NULL, body, NULL FROM secrets",
            "1; DROP TABLE secrets--",
            "1; DROP TABLE secrets; --",
            "1; DELETE FROM secrets",
            "'; DROP TABLE users; --",
            "admin'--",
            "admin' #",
            "' OR ''='",
            "1' OR '1'='1' /*",
            "' OR 'x'='x",
            "') OR ('x'='x",
            "\" OR \"1\"=\"1",
            "\" OR 1=1--",
            "1 AND 1=1",
            "1 AND 1=2",
            "' AND 1=1--",
            "' AND SLEEP(5)--",
            "' WAITFOR DELAY '0:0:5'--",
            "'; EXEC xp_cmdshell('dir'); --",
            "' BENCHMARK(10000000,MD5(1))--",
            "1/**/UNION/**/SELECT/**/NULL",
            "' UnIoN SeLeCt body FrOm secrets--",
            "%27%20OR%201%3D1",
            "' || (SELECT body FROM secrets) || '",
            "' + (SELECT body FROM secrets) + '",
            "0x27204f5220313d31",
            "\\'; DROP TABLE secrets; --",
            "' HAVING 1=1--",
            "' GROUP BY id HAVING 1=1--",
            "' ORDER BY 1--",
            "' ORDER BY 100--",
            "1' ORDER BY 1#",
            "-1' UNION SELECT 1,2,3--",
            "' AND EXTRACTVALUE(1,CONCAT(0x7e,(SELECT body FROM secrets LIMIT 1)))--",
            "' AND UPDATEXML(1,CONCAT(0x7e,(SELECT body FROM secrets)),1)--",
            "(SELECT * FROM (SELECT(SLEEP(5)))a)",
            "' AND (SELECT * FROM (SELECT(SLEEP(5)))a)--",
            "/*!50000UNION*/ /*!50000SELECT*/ 1,2,3",
            "' AND 1=(SELECT COUNT(*) FROM secrets)--",
            "1; ATTACH DATABASE '/tmp/x' AS pwn--",
            "'; INSERT INTO secrets (body) VALUES ('pwned'); --",
            "' OR EXISTS(SELECT 1 FROM secrets)--",
            "{{7*7}}",
            "\${7*7}",
            "%00' OR 1=1--",
            "'\nOR 1=1--",
            "'\tOR '1'='1",
            "١' OR '١'='١",
            "'; VACUUM; --",
            "' ON CONFLICT DO NOTHING--",
            "returning body--",
        ];

        // ۲۰۰ payload: ترکیب base با prefix/suffix
        $payloads = [];
        foreach ($base as $p) {
            $payloads[] = $p;
            $payloads[] = ' ' . $p;
            $payloads[] = $p . ' ';
            $payloads[] = 'admin' . $p;
        }
        while (\count($payloads) < 200) {
            $payloads[] = $base[\count($payloads) % \count($base)] . \str_repeat('-', \count($payloads) % 5);
        }

        return \array_slice($payloads, 0, 200);
    }

    public function testWhereValuePayloadsAreInert(): void
    {
        foreach ($this->payloads() as $payload) {
            // where روی email: هیچ payload نباید سطری از secrets نشت دهد یا users را خراب کند
            $rows = $this->db->table('users')->where('email', $payload)->get();
            $this->assertSame([], $rows, 'exact-match where must never return rows for payload: ' . $payload);

            // like روی name
            $rows = $this->db->table('users')->where('name', 'like', '%' . $payload . '%')->get();
            $this->assertSame([], $rows, 'like must not match payload: ' . $payload);

            // whereRaw با binding (T-11)
            $rows = $this->db->table('users')->whereRaw('email = ?', [$payload])->get();
            $this->assertSame([], $rows, 'whereRaw with binding must be inert: ' . $payload);
        }

        $this->assertCanaryIntact();
    }

    public function testInsertPayloadsRoundtrip(): void
    {
        foreach (\array_slice($this->payloads(), 0, 60) as $i => $payload) {
            $this->db->table('users')->insert(['name' => $payload, 'email' => "p{$i}@x.io", 'role' => 'user']);
        }

        // مقادیر عیناً برگردند (نه اجرا شده!)
        $rows = $this->db->table('users')->where('role', 'user')->where('email', 'like', 'p%@x.io')->get();
        $this->assertCount(60, $rows);

        $this->assertCanaryIntact();
        $this->assertSame(2, $this->db->table('users')->whereIn('email', ['admin@x.io', 'user@x.io'])->count());
    }

    public function testUpdateDeletePayloadsAreInert(): void
    {
        foreach (\array_slice($this->payloads(), 0, 50) as $payload) {
            $affected = $this->db->table('users')->where('email', $payload)->update(['role' => 'hacked']);
            $this->assertSame(0, $affected, 'update with payload must affect nothing: ' . $payload);

            $deleted = $this->db->table('users')->where('email', $payload)->delete();
            $this->assertSame(0, $deleted, 'delete with payload must affect nothing: ' . $payload);
        }

        $this->assertSame(2, $this->db->table('users')->count());
        $this->assertCanaryIntact();
    }

    public function testOperatorInjectionRejected(): void
    {
        $rejected = 0;
        foreach ([
            '= 1 OR 1=1',
            "= 1; DROP TABLE secrets; --",
            "LIKE '%' OR '1'='1",
            'IS NOT NULL OR 1=1',
            'UNION SELECT',
        ] as $operator) {
            try {
                $this->db->table('users')->where('role', $operator, 'x')->get();
            } catch (UnsafeQueryException) {
                $rejected++;
            }
        }

        $this->assertSame(5, $rejected, 'all malicious operators must be rejected by allowlist');
        $this->assertCanaryIntact();
    }

    public function testOrderByInjectionRejected(): void
    {
        foreach (['asc; DROP TABLE secrets--', 'asc, (SELECT body FROM secrets)', "desc' OR 1=1--"] as $direction) {
            try {
                $this->db->table('users')->orderBy('id', $direction)->get();
                self::fail('direction injection must throw for: ' . $direction);
            } catch (UnsafeQueryException) {
                $this->assertTrue(true);
            }
        }

        $this->assertCanaryIntact();
    }

    public function testIdentifierPayloadsFailSafely(): void
    {
        // ستون/جدول مخرب → QueryException (syntax) ولی secrets سالم و بدون نشت
        foreach (["users; DROP TABLE secrets--", 'users--', 'secrets'] as $table) {
            try {
                $this->db->table($table)->get();
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }

        // جدول secrets واقعاً وجود دارد — خواندنش نشت نیست (تکنیک allowlist در سطح اپ)
        $this->assertCanaryIntact();
    }

    public function testRawWithSuperglobalPatternRejected(): void
    {
        try {
            $this->db->table('users')->whereRaw('email = $_GET[email]')->get();
            self::fail('raw containing superglobal pattern must throw');
        } catch (UnsafeQueryException $e) {
            $this->assertSame('database.unsafe_raw', $e->messageId);
        }
    }

    private function assertCanaryIntact(): void
    {
        // جدول secrets باید دقیقاً یک رکورد canary داشته باشد
        $this->assertSame(1, $this->db->table('secrets')->count());
        $this->assertSame('top-secret-canary', (string) $this->db->table('secrets')->value('body'));
    }
}
