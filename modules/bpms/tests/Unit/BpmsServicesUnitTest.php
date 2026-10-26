<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Unit;

use Modules\Bpms\Domain\Services\MonitoringCalculator;
use Modules\Bpms\Domain\Services\WorkflowDefinitionCompiler;
use Modules\Bpms\Domain\Services\DiagramGenerator;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;
use Modules\Bpms\Tests\Unit\ProcessModelTest;
use Tavaan\Testing\TestCase;

/**
 * تست واحد پایش/کامپایلر/نمودار (DOC-098 §8 + mirror + لایهٔ بصری).
 */
final class BpmsServicesUnitTest extends TestCase
{
    public function testStateDurationsAndBottlenecks(): void
    {
        $history = [
            ['from_state' => 'submit', 'duration_ms' => 1000],
            ['from_state' => 'submit', 'duration_ms' => 3000],
            ['from_state' => 'manager_approval', 'duration_ms' => 100000],
            ['from_state' => 'manager_approval', 'duration_ms' => 50000],
            ['from_state' => null, 'duration_ms' => null],          // ردیف بدون duration → نادیده
        ];

        $durations = MonitoringCalculator::stateDurations($history);
        self::assertSame(2000, $durations['submit']['avg_ms']);
        self::assertSame(2, $durations['submit']['count']);
        self::assertSame(75000, $durations['manager_approval']['avg_ms']);
        self::assertSame(100000, $durations['manager_approval']['max_ms']);

        // مرتب‌سازی نزولی — manager_approval اول (کندترین)
        $bottlenecks = MonitoringCalculator::bottlenecks($history, 1);
        self::assertCount(1, $bottlenecks);
        self::assertSame('manager_approval', $bottlenecks[0]['state']);
    }

    public function testSlaCompliance(): void
    {
        $tasks = [
            // به‌موقع
            ['status' => 'completed', 'due_at' => '2026-10-26 10:00:00.000', 'completed_at' => '2026-10-26 09:00:00.000'],
            // دیرهنگام
            ['status' => 'completed', 'due_at' => '2026-10-26 10:00:00.000', 'completed_at' => '2026-10-26 11:00:00.000'],
            // بدون due → در شمار نیست
            ['status' => 'completed', 'due_at' => null, 'completed_at' => '2026-10-26 11:00:00.000'],
            // باز → در شمار نیست
            ['status' => 'pending', 'due_at' => '2026-10-26 10:00:00.000', 'completed_at' => null],
            // دقیقاً سررسید → به‌موقع
            ['status' => 'completed', 'due_at' => '2026-10-26 10:00:00.000', 'completed_at' => '2026-10-26 10:00:00.000'],
        ];

        $sla = MonitoringCalculator::slaCompliance($tasks);
        self::assertSame(4, $sla['completed']);
        self::assertSame(3, $sla['with_due']);
        self::assertSame(2, $sla['on_time']);
        self::assertSame(66.7, $sla['compliance']);

        // بدون داده → null (نه تقسیم بر صفر)
        $empty = MonitoringCalculator::slaCompliance([]);
        self::assertNull($empty['compliance']);
    }

    public function testWorkload(): void
    {
        $now = new \DateTimeImmutable('2026-10-26 12:00:00');
        $tasks = [
            ['status' => 'pending', 'assignee_type' => 'role', 'assignee_id' => 'manager', 'due_at' => '2026-10-26 10:00:00.000', 'escalated_at' => '2026-10-26 11:00:00.000'],
            ['status' => 'claimed', 'assignee_type' => 'role', 'assignee_id' => 'manager', 'due_at' => null, 'escalated_at' => null],
            ['status' => 'pending', 'assignee_type' => 'user', 'assignee_id' => '3', 'due_at' => '2026-10-27 10:00:00.000', 'escalated_at' => null],
            ['status' => 'completed', 'assignee_type' => 'user', 'assignee_id' => '3', 'due_at' => null, 'escalated_at' => null],
        ];

        $workload = MonitoringCalculator::workload($tasks, $now);

        self::assertSame(2, $workload['role:manager']['open']);
        self::assertSame(1, $workload['role:manager']['overdue']);
        self::assertSame(1, $workload['role:manager']['escalated']);
        self::assertSame(1, $workload['user:3']['open']);
        self::assertSame(0, $workload['user:3']['overdue']);

        // مرتب‌سازی: پربارترین اول
        self::assertSame('role:manager', \array_key_first($workload));
    }

    public function testInstanceCountsAndCycleTime(): void
    {
        $instances = [
            ['status' => 'running', 'started_at' => '2026-10-26 08:00:00.000', 'completed_at' => null],
            ['status' => 'completed', 'started_at' => '2026-10-26 08:00:00.000', 'completed_at' => '2026-10-26 09:00:00.000'],
            ['status' => 'completed', 'started_at' => '2026-10-26 08:00:00.000', 'completed_at' => '2026-10-26 10:00:00.000'],
            ['status' => 'terminated', 'started_at' => '2026-10-26 08:00:00.000', 'completed_at' => '2026-10-26 08:30:00.000'],
        ];

        $counts = MonitoringCalculator::instanceCounts($instances);
        self::assertSame(1, $counts['running']);
        self::assertSame(2, $counts['completed']);
        self::assertSame(1, $counts['terminated']);

        // میانگین چرخه فقط completedها: (1h + 2h)/2 = 1.5h
        self::assertSame(5_400_000, MonitoringCalculator::averageCycleTimeMs($instances));
        self::assertNull(MonitoringCalculator::averageCycleTimeMs([['status' => 'running']]));
    }

    public function testFormatDuration(): void
    {
        self::assertSame('—', MonitoringCalculator::formatDuration(null));
        self::assertSame('500ms', MonitoringCalculator::formatDuration(500));
        self::assertSame('45s', MonitoringCalculator::formatDuration(45_000));
        self::assertSame('5m 0s', MonitoringCalculator::formatDuration(300_000));
        self::assertSame('2h 30m', MonitoringCalculator::formatDuration(9_000_000));
        self::assertSame('1d 0h', MonitoringCalculator::formatDuration(86_400_000));
    }

    // ─── WorkflowDefinitionCompiler (mirror) ──────────────────────────────

    public function testCompilerMapsElementsToEngineFormat(): void
    {
        $model = ProcessModel::fromArray(ProcessModelTest::leaveRequestJson());
        $compiled = WorkflowDefinitionCompiler::compile('bpms-leave-request', $model, 3);

        self::assertSame('bpms-leave-request', $compiled['id']);
        self::assertSame(3, $compiled['version']);
        self::assertCount(9, $compiled['states']);

        $states = [];
        foreach ($compiled['states'] as $state) {
            $states[(string) $state['id']] = $state;
        }

        self::assertSame('start', $states['start']['type']);
        self::assertSame('end', $states['end']['type']);
        self::assertSame('user_task', $states['submit']['type']);
        self::assertSame('initiator', $states['submit']['assignee']);
        // 🔒 gatewayها normal (مسیریابی خودکار موتور غیرفعال — mirror با fire صریح)
        self::assertSame('normal', $states['check_days']['type']);
        self::assertSame('normal', $states['decision']['type']);
        self::assertSame('normal', $states['auto_approve']['type']);

        // گذارها: یکی per یال (next + هر دو شرط gateway)
        $transitions = [];
        foreach ($compiled['transitions'] as $transition) {
            $transitions[(string) $transition['id']] = $transition;
        }
        self::assertArrayHasKey('t_start__submit', $transitions);
        self::assertArrayHasKey('t_check_days__auto_approve', $transitions);
        self::assertArrayHasKey('t_check_days__manager_approval', $transitions);
        self::assertArrayHasKey('t_decision__notify', $transitions);
        self::assertArrayHasKey('t_decision__reject', $transitions);

        // تایمرها منتقل نمی‌شوند (تایمر BPMS اکشن-محور است)
        self::assertArrayNotHasKey('timers', $compiled);
    }

    public function testCompilerDeduplicatesParallelEdges(): void
    {
        $model = ProcessModel::fromArray([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'split'],
                ['id' => 'split', 'type' => 'parallelGateway', 'fork' => ['a', 'b']],
                ['id' => 'a', 'type' => 'userTask', 'assignee' => 'role:x', 'next' => 'join'],
                ['id' => 'b', 'type' => 'userTask', 'assignee' => 'role:y', 'next' => 'join'],
                ['id' => 'join', 'type' => 'parallelGateway', 'next' => 'end'],
                ['id' => 'end', 'type' => 'endEvent'],
            ],
        ]);

        $compiled = WorkflowDefinitionCompiler::compile('bpms-purchase', $model);
        $ids = \array_column($compiled['transitions'], 'id');
        self::assertSame(\array_unique($ids), $ids);          // بدون تکرار
        self::assertContains('t_split__a', $ids);
        self::assertContains('t_split__b', $ids);
        self::assertContains('t_a__join', $ids);
        self::assertContains('t_b__join', $ids);
    }

    // ─── DiagramGenerator (لایهٔ طراحی بصری) ──────────────────────────────

    public function testDiagramSvgContainsNodesAndEscapesLabels(): void
    {
        $json = ProcessModelTest::leaveRequestJson();
        // برچسب با کاراکتر خطرناک → escape
        $json['elements'][1]['name'] = 'ثبت <script>alert(1)</script>';
        $model = ProcessModel::fromArray($json);

        $svg = (new DiagramGenerator())->toSvg($model, ['manager_approval']);

        self::assertTrue(\str_starts_with($svg, '<svg'));
        self::assertStringContainsString('</svg>', $svg);
        self::assertStringContainsString('ثبت', $svg);                       // لیبل userTask
        self::assertStringContainsString('بررسی تعداد روز', $svg);           // لیبل gateway
        self::assertStringNotContainsString('<script>', $svg);
        self::assertStringContainsString('&lt;script&gt;', $svg);
        // هایلایت توکن فعال
        self::assertStringContainsString('stroke="#16a34a"', $svg);
        // تایمر
        self::assertStringContainsString('P2D', $svg);
    }

    public function testDiagramMermaidOutput(): void
    {
        $model = ProcessModel::fromArray(ProcessModelTest::leaveRequestJson());
        $mermaid = (new DiagramGenerator())->toMermaid($model);

        self::assertTrue(\str_starts_with($mermaid, 'flowchart LR'));
        self::assertStringContainsString('submit --> check_days', $mermaid);
        self::assertStringContainsString('check_days -->|variables.days <= 2| auto_approve', $mermaid);
        self::assertStringContainsString('-.->|⏱ P2D: escalate|', $mermaid);
    }
}
