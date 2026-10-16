<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Audit\AuditEvent;
use Tavaan\Engine\Audit\Auditor;
use Tavaan\Engine\Audit\AuditObserver;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Testing\TPost;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Audit — DOC-058 §9 (T-01..T-06) + AC-AUD-07.
 */
final class AuditTest extends TestCase
{
    private Application $app;

    private Auditor $auditor;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();

        $config = $this->app->make('config');
        $config->set('database.default', 'sqlite_memory');
        $config->set('database.connections.sqlite_memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $config->set('audit.key', 'test-audit-key-32bytes-long-xxxxx');

        $migration = require \dirname(__DIR__, 4) . '/engines/audit/migrations/2026_01_04_000001_create_audit_logs_table.php';
        $migration->up();

        $this->app->make('schema')->create('orm_posts', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->string('status')->default('draft');
            $t->timestamp('deleted_at', 3)->nullable();   // TPost softDeletes
            $t->timestamps(3);
        });

        Model::flushTestState();
        $this->auditor = $this->app->make(Auditor::class);
    }

    protected function tearDown(): void
    {
        Model::flushTestState();
        Clock::setTestNow(null);
    }

    // ─── T-01: زنجیرهٔ هش درست ────────────────────────────────────────────

    public function testRecordBuildsValidChain(): void
    {
        $id1 = $this->auditor->record(AuditEvent::create('Order', '1', ['total' => 100], userId: 7));
        $id2 = $this->auditor->record(AuditEvent::update('Order', '1', ['total' => 100], ['total' => 250], userId: 7));
        $id3 = $this->auditor->record(AuditEvent::delete('Order', '1', ['total' => 250]));

        $this->assertSame(26, \strlen($id1));
        $this->assertNotSame($id1, $id2);
        $this->assertNotSame($id2, $id3);

        $db = $this->app->make('db')->connection();
        $rows = $db->table('tvn_audit_logs')->orderBy('id')->get();

        $this->assertCount(3, $rows);
        $this->assertSame(Auditor::GENESIS_HASH, $rows[0]['prev_hash']);
        $this->assertSame($rows[0]['hash'], $rows[1]['prev_hash']);
        $this->assertSame($rows[1]['hash'], $rows[2]['prev_hash']);
        $this->assertSame(64, \strlen((string) $rows[0]['hash']));

        // changed_fields
        $this->assertSame('["total"]', $rows[1]['changed_fields']);

        $result = $this->auditor->verify();
        $this->assertTrue($result->intact);
        $this->assertSame(3, $result->checked);
    }

    // ─── T-02: دستکاری → verify fail ──────────────────────────────────────

    public function testTamperedRecordDetected(): void
    {
        $this->auditor->record(AuditEvent::create('Order', '1', ['total' => 100]));
        $this->auditor->record(AuditEvent::update('Order', '1', ['total' => 100], ['total' => 250]));
        $this->auditor->record(AuditEvent::update('Order', '1', ['total' => 250], ['total' => 500]));

        $this->assertTrue($this->auditor->verify()->intact);

        // 🔒 دستکاری مستقیم در DB (مهاجم)
        $db = $this->app->make('db')->connection();
        $db->statement(
            'update tvn_audit_logs set new_values = ? where id = 2',
            [(string) \json_encode(['total' => 999999])],
        );

        $result = $this->auditor->verify();
        $this->assertFalse($result->intact);
        $this->assertStringContainsString('hash mismatch', (string) $result->reason);
    }

    // ─── T-03: حذف رکورد → verify fail ────────────────────────────────────

    public function testDeletedRecordDetected(): void
    {
        $this->auditor->record(AuditEvent::create('A', '1', []));
        $this->auditor->record(AuditEvent::create('A', '2', []));
        $this->auditor->record(AuditEvent::create('A', '3', []));

        $db = $this->app->make('db')->connection();
        $db->statement('delete from tvn_audit_logs where id = 2');   // حذف رکورد میانی

        $result = $this->auditor->verify();
        $this->assertFalse($result->intact);
        $this->assertStringContainsString('prev_hash mismatch', (string) $result->reason);
    }

    public function testWrongKeyDetected(): void
    {
        $this->auditor->record(AuditEvent::create('A', '1', []));

        // Auditor با کلید متفاوت (مثل rotation بدون re-chain)
        $other = new Auditor(
            $this->app->make('db')->connection(),
            ['key' => 'another-key-32bytes-long-xxxxxxx'],
        );

        $this->assertFalse($other->verify()->intact);
    }

    // ─── T-04: Observer خودکار روی Model ──────────────────────────────────

    public function testModelObserverRecordsChanges(): void
    {
        TPost::observe(new AuditObserver($this->auditor, fn (): ?int => 42, fn (): ?string => '1.2.3.4'));

        $post = TPost::create(['title' => 'p1', 'status' => 'draft']);
        $post->update(['status' => 'published']);
        $post->delete();

        $db = $this->app->make('db')->connection();
        $rows = $db->table('tvn_audit_logs')->orderBy('id')->get();

        $events = \array_column($rows, 'event');
        $this->assertContains('created', $events);
        $this->assertContains('updated', $events);
        $this->assertContains('deleted', $events);

        // کاربر/IP ثبت شده
        $created = $rows[0];
        $this->assertSame(42, (int) $created['user_id']);
        $this->assertSame('1.2.3.4', $created['ip']);

        // diff درست
        $updated = null;
        foreach ($rows as $row) {
            if ($row['event'] === 'updated') {
                $updated = $row;
            }
        }
        $this->assertNotNull($updated);
        $this->assertStringContainsString('"status":"draft"', (string) $updated['old_values']);
        $this->assertStringContainsString('"status":"published"', (string) $updated['new_values']);

        $this->assertTrue($this->auditor->verify()->intact);
    }

    // ─── T-05: exclude_fields ─────────────────────────────────────────────

    public function testExcludedFieldsMasked(): void
    {
        $this->auditor->record(AuditEvent::create('User', '1', [
            'name' => 'Ali',
            'password' => 'super-secret-hash',
            'remember_token' => 'token-abc',
            'secret' => 'xyz',
        ]));

        $db = $this->app->make('db')->connection();
        $row = $db->table('tvn_audit_logs')->first();
        $newValues = (string) $row['new_values'];

        $this->assertStringNotContainsString('super-secret-hash', $newValues);
        $this->assertStringNotContainsString('token-abc', $newValues);
        $this->assertStringNotContainsString('xyz', $newValues);
        $this->assertStringContainsString('REDACTED', $newValues);
        $this->assertStringContainsString('Ali', $newValues);   // فیلد عادی حفظ
    }

    // ─── export / prune / show ────────────────────────────────────────────

    public function testExportAndPrune(): void
    {
        Clock::setTestNow('2026-01-01 00:00:00');
        $this->auditor->record(AuditEvent::create('A', '1', []));

        Clock::setTestNow('2026-06-01 00:00:00');
        $this->auditor->record(AuditEvent::create('A', '2', []));
        Clock::setTestNow(null);

        $all = $this->auditor->export();
        // export خودش یک رکورد ممیزی می‌کند → حداقل ۲ رکورد در بازه
        $this->assertTrue(\count($all) >= 2);

        Clock::setTestNow('2026-12-01 00:00:00');
        $pruned = $this->auditor->prune(200);   // قدیمی‌تر از ~۲۰۰ روز
        $this->assertTrue($pruned >= 1);
        Clock::setTestNow(null);
    }

    public function testFindById(): void
    {
        $id = $this->auditor->record(AuditEvent::create('Order', '99', ['total' => 5]));

        $record = $this->auditor->find($id);
        $this->assertNotNull($record);
        $this->assertSame('created', $record['event']);
        $this->assertSame('99', $record['auditable_id']);
    }

    // ─── AC-AUD-07: کارایی verify ─────────────────────────────────────────

    public function testVerify10000RecordsPerformance(): void
    {
        // هدف: ≤ ۲s برای ۱۰۰۰۰ رکورد (سقف CI: ۱۰s برای محیط‌های کند)
        $db = $this->app->make('db')->connection();

        $rows = [];
        $prev = Auditor::GENESIS_HASH;
        $key = 'test-audit-key-32bytes-long-xxxxx';

        for ($i = 0; $i < 10000; $i++) {
            $payload = (string) \json_encode([
                'event' => 'created', 'type' => 'Bench', 'id' => (string) $i,
                'user' => null, 'old' => null, 'new' => null,
                'ts' => '2026-01-01 00:00:00.000000',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $hash = \hash_hmac('sha256', $prev . '|' . $payload, $key);

            $rows[] = [
                'audit_id' => \str_pad((string) $i, 26, '0', STR_PAD_LEFT),
                'event' => 'created', 'auditable_type' => 'Bench', 'auditable_id' => (string) $i,
                'user_id' => null, 'tenant_id' => null,
                'old_values' => null, 'new_values' => null, 'changed_fields' => '[]',
                'ip' => null, 'user_agent' => null, 'trace_id' => null,
                'prev_hash' => $prev, 'hash' => $hash,
                'created_at' => '2026-01-01 00:00:00.000000',
            ];
            $prev = $hash;
        }

        foreach (\array_chunk($rows, 500) as $chunk) {
            $db->table('tvn_audit_logs')->insert($chunk);
        }

        $start = \microtime(true);
        $result = $this->auditor->verify();
        $elapsed = (\microtime(true) - $start) * 1000;

        $this->assertTrue($result->intact);
        $this->assertSame(10000, $result->checked);
        $this->assertLessThan(10000, $elapsed, \sprintf('verify took %.0fms', $elapsed));
    }
}
