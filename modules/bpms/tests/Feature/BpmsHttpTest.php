<?php

declare(strict_types=1);

namespace Modules\Bpms\Tests\Feature;

use Modules\Bpms\Application\UseCases\ActionRegistry;
use Modules\Bpms\Application\UseCases\ProcessContext;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست HTTP ماژول BPMS — AC-BPMS-11 (Designer → JSON معتبر → Engine)،
 * صفحات پنل/وب، ماتریس نقش‌ها (§7)، فرم پویا روی وب، CSV و SVG.
 */
final class BpmsHttpTest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*', 'bpms/*']);
        $config->set('app.url', 'http://localhost');
        $config->set('mail.default', 'array');

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    // ─── AC-BPMS-11: چرخهٔ کامل Designer از راه HTTP ──────────────────────

    public function testAC11_DesignerPublishStartCompleteViaHttp(): void
    {
        $designer = $this->user('designer@bpms.test', 'طراح', 'process-designer');
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $manager = $this->user('manager@bpms.test', 'مدیر', 'manager');

        $this->actingAs($designer);

        // صفحهٔ فرایندساز
        $this->get('/admin/bpms/definitions/create')->assertOk();
        $this->get('/admin/bpms/definitions')->assertOk();

        // ۱) ساخت پیش‌نویس با JSON (تعریف/فرم به‌صورت رشتهٔ JSON — مانند designer واقعی)
        $definitionJson = (string) \json_encode([
            'startEvent' => 'start',
            'elements' => [
                ['id' => 'start', 'type' => 'startEvent', 'next' => 'submit'],
                ['id' => 'submit', 'type' => 'userTask', 'name' => 'ثبت درخواست',
                 'assignee' => 'initiator', 'form' => 'req_form', 'next' => 'check'],
                ['id' => 'check', 'type' => 'exclusiveGateway', 'conditions' => [
                    ['expr' => 'variables.amount > 100', 'next' => 'boss'],
                    ['default' => true, 'next' => 'end'],
                ]],
                ['id' => 'boss', 'type' => 'userTask', 'name' => 'تأیید رئیس',
                 'assignee' => 'role:manager', 'next' => 'end'],
                ['id' => 'end', 'type' => 'endEvent'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $formsJson = (string) \json_encode([
            'req_form' => ['id' => 'req_form', 'fields' => [
                ['name' => 'amount', 'type' => 'number', 'label' => 'مبلغ', 'min' => 1, 'max' => 1000, 'required' => true],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $response = $this->post('/admin/bpms/definitions', [
            'key' => 'expense-claim',
            'name' => 'هزینه‌های کارکنان',
            'description' => 'تأیید هزینهٔ کارمند',
            'definition' => $definitionJson,
            'form_schema' => $formsJson,
        ]);
        $response->assertRedirect();
        \preg_match('#/admin/bpms/definitions/([0-9A-Z]{26})#', (string) $response->header('Location'), $m);
        $defId = $m[1] ?? '';
        self::assertNotSame('', $defId);

        // JSON نامعتبر → رد با flash (بدون رکورد منتشرشده)
        $bad = $this->post('/admin/bpms/definitions', [
            'key' => 'bad-process',
            'name' => 'بد',
            'definition' => '{invalid json',
        ]);
        $bad->assertRedirect();
        self::assertFalse(DB::table('bpms_process_definitions')->where('key', 'bad-process')->exists());

        // JSON معتبر ولی گراف ناقص (بدون endEvent) → همان‌جا رد می‌شود (draft هم ذخیره نمی‌شود)
        $incomplete = $this->post('/admin/bpms/definitions', [
            'key' => 'incomplete',
            'name' => 'ناتمام',
            'definition' => (string) \json_encode([
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'loop'],
                    ['id' => 'loop', 'type' => 'serviceTask', 'action' => 'noop', 'next' => 'loop'],
                ],
            ]),
        ]);
        $incomplete->assertRedirect();
        self::assertFalse(DB::table('bpms_process_definitions')->where('key', 'incomplete')->exists());

        // ۲) صفحهٔ تعریف + نمودار SVG
        $show = $this->get('/admin/bpms/definitions/' . $defId);
        $show->assertOk();
        $show->assertSee('هزینه‌های کارکنان');
        $show->assertSee('expense-claim');

        $svg = $this->get('/admin/bpms/definitions/' . $defId . '/diagram.svg');
        $svg->assertOk();
        self::assertStringContainsString('image/svg+xml', (string) $svg->header('Content-Type'));
        self::assertStringContainsString('<svg', $svg->content());

        // ۳) انتشار → published + active + mirror ثبت شد
        $this->post('/admin/bpms/definitions/' . $defId . '/publish')->assertRedirect();
        $row = DB::table('bpms_process_definitions')->where('id', $defId)->first();
        self::assertNotNull($row);
        self::assertSame('published', $row['status']);
        self::assertTrue((bool) $row['is_active']);
        self::assertTrue(DB::table('tvn_workflows')->where('name', 'bpms-expense-claim')->exists());

        // ویرایش پس از انتشار → ممنوع (redirect با flash)
        $this->get('/admin/bpms/definitions/' . $defId . '/edit')->assertRedirect();

        // ۴) کارمند فرایند را از کاتالوگ وب آغاز می‌کند
        $this->actingAs($employee);
        $catalog = $this->get('/bpms/processes');
        $catalog->assertOk();
        $catalog->assertSee('هزینه‌های کارکنان');

        $this->get('/bpms/processes/expense-claim/start')->assertOk();

        $start = $this->post('/bpms/processes/expense-claim/start', ['business_key' => 'EXP-1']);
        $start->assertRedirect();
        \preg_match('#/bpms/instances/([0-9A-Z]{26})#', (string) $start->header('Location'), $mi);
        $instanceId = $mi[1] ?? '';
        self::assertNotSame('', $instanceId);

        // task «ثبت درخواست» در inbox کارمند
        $inbox = $this->get('/bpms/tasks');
        $inbox->assertOk();
        $inbox->assertSee('ثبت درخواست');

        $tasks = $this->app()->make(TaskRepositoryInterface::class)->inboxFor($employee->id, []);
        self::assertCount(1, $tasks);
        $task = $tasks[0];

        // صفحهٔ کار → فرم پویا رندر شده (فیلد amount از schema)
        $taskPage = $this->get('/bpms/tasks/' . $task->id());
        $taskPage->assertOk();
        $taskPage->assertSee('name="amount"');
        $taskPage->assertSee('مبلغ');

        // دادهٔ نامعتبر (amount > max) → redirect با خطا، task باز می‌ماند
        $this->post('/bpms/tasks/' . $task->id() . '/complete', ['amount' => 5000])->assertRedirect();
        $stillOpen = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($stillOpen);
        self::assertTrue($stillOpen->status()->isOpen());

        // دادهٔ معتبر → gateway مسیر boss را انتخاب می‌کند (amount > 100)
        $this->post('/bpms/tasks/' . $task->id() . '/complete', [
            'amount' => 500,
            'comment' => 'خرید تجهیزات',
        ])->assertRedirect();

        $instance = $this->app()->make(InstanceRepositoryInterface::class)->findById($instanceId);
        self::assertNotNull($instance);
        self::assertSame('boss', $instance->currentState());
        self::assertSame(500, $instance->getVariable('amount'));

        // صفحهٔ نمونهٔ من — timeline + نمودار
        $instancePage = $this->get('/bpms/instances/' . $instanceId);
        $instancePage->assertOk();
        $instancePage->assertSee('EXP-1');

        // ۵) مدیر کار «تأیید رئیس» را در inbox نقش می‌بیند و claim می‌کند
        $this->actingAs($manager);
        $managerInbox = $this->get('/bpms/tasks');
        $managerInbox->assertOk();
        $managerInbox->assertSee('تأیید رئیس');

        $bossTasks = $this->app()->make(TaskRepositoryInterface::class)->inboxFor($manager->id, ['manager']);
        self::assertCount(1, $bossTasks);
        $bossTask = $bossTasks[0];

        $this->post('/bpms/tasks/' . $bossTask->id() . '/claim')->assertRedirect();
        $this->post('/bpms/tasks/' . $bossTask->id() . '/complete', [])->assertRedirect();

        $final = $this->app()->make(InstanceRepositoryInterface::class)->findById($instanceId);
        self::assertNotNull($final);
        self::assertSame('completed', $final->status()->value);

        // mirror هم تا پایان پیش رفته
        self::assertNotNull($final->workflowInstanceId());
        $mirror = DB::table('tvn_process_instances')->where('id', $final->workflowInstanceId())->first();
        self::assertNotNull($mirror);
        self::assertSame('completed', $mirror['status']);
    }

    // ─── صفحات پنل + ماتریس نقش‌ها (§7) ───────────────────────────────────

    public function testAdminPagesAndRoleMatrix(): void
    {
        $this->seedPublishedLeaveDefinition();

        $admin = $this->user('padmin@bpms.test', 'مدیر فرایند', 'process-admin');
        $monitor = $this->user('monitor@bpms.test', 'پایشگر', 'monitor');
        $auditor = $this->user('auditor@bpms.test', 'حسابرس', 'auditor');
        $designer = $this->user('designer@bpms.test', 'طراح', 'process-designer');

        // process-admin — همه‌چیز
        $this->actingAs($admin);
        $this->get('/admin/bpms')->assertOk();
        $this->get('/admin/bpms/definitions')->assertOk();
        $this->get('/admin/bpms/instances')->assertOk();
        $this->get('/admin/bpms/tasks')->assertOk();
        $this->get('/admin/bpms/audit')->assertOk();

        // CSV با BOM
        $csv = $this->get('/admin/bpms/reports/kpis.csv');
        $csv->assertOk();
        self::assertStringContainsString('text/csv', (string) $csv->header('Content-Type'));
        self::assertTrue(\str_starts_with($csv->content(), "\xEF\xBB\xBF"));
        self::assertStringContainsString('section,metric,value', $csv->content());

        // monitor — فقط پایش/خواندن؛ انتشار و terminate ممنوع
        $this->actingAs($monitor);
        $this->get('/admin/bpms')->assertOk();
        $this->get('/admin/bpms/instances')->assertOk();
        $definitionId = (string) DB::table('bpms_process_definitions')->where('key', 'leave-request')->value('id');
        $this->get('/admin/bpms/definitions/' . $definitionId)->assertOk();
        self::assertSame(403, $this->post('/admin/bpms/definitions/' . $definitionId . '/publish')->status());

        $instanceId = $this->startLeaveAsAdmin($admin);
        self::assertSame(403, $this->post('/admin/bpms/instances/' . $instanceId . '/terminate')->status());

        // auditor — صفحهٔ audit + پایش
        $this->actingAs($auditor);
        $auditPage = $this->get('/admin/bpms/audit');
        $auditPage->assertOk();
        $auditPage->assertSee('definition.published');
        self::assertSame(403, $this->get('/admin/bpms/instances/create')->status());   // بدون instance.start

        // designer — تعاریف؛ نمونه/terminate ممنوع
        $this->actingAs($designer);
        $this->get('/admin/bpms/definitions')->assertOk();
        $this->get('/admin/bpms/definitions/create')->assertOk();
        self::assertSame(403, $this->get('/admin/bpms/tasks')->status());
        self::assertSame(403, $this->post('/admin/bpms/instances/' . $instanceId . '/terminate')->status());
    }

    public function testInstanceManagementFlowViaHttp(): void
    {
        $this->seedPublishedLeaveDefinition();
        $admin = $this->user('padmin@bpms.test', 'مدیر فرایند', 'process-admin');
        $this->actingAs($admin);

        // شروع از پنل
        $this->get('/admin/bpms/instances/create')->assertOk();
        $response = $this->post('/admin/bpms/instances', [
            'definition_key' => 'leave-request',
            'business_key' => 'ADM-1',
            'variables' => '{"days": 1}',
        ]);
        $response->assertRedirect();
        \preg_match('#/admin/bpms/instances/([0-9A-Z]{26})#', (string) $response->header('Location'), $m);
        $instanceId = $m[1] ?? '';
        self::assertNotSame('', $instanceId);

        // suspend → resume → terminate
        $this->post('/admin/bpms/instances/' . $instanceId . '/suspend')->assertRedirect();
        self::assertSame('suspended', DB::table('bpms_process_instances')->where('id', $instanceId)->value('status'));

        $this->post('/admin/bpms/instances/' . $instanceId . '/resume')->assertRedirect();
        self::assertSame('running', DB::table('bpms_process_instances')->where('id', $instanceId)->value('status'));

        $this->post('/admin/bpms/instances/' . $instanceId . '/terminate', ['reason' => 'تست'])->assertRedirect();
        self::assertSame('terminated', DB::table('bpms_process_instances')->where('id', $instanceId)->value('status'));

        // audit مدیریتی — terminate/suspend/resume در زنجیره
        self::assertTrue(DB::table('tvn_audit_logs')->where('event', 'instance.terminated')->exists());
        self::assertTrue(DB::table('tvn_audit_logs')->where('event', 'instance.suspended')->exists());
        self::assertTrue(DB::table('tvn_audit_logs')->where('event', 'instance.resumed')->exists());

        // متغیرهای حساس mask می‌شوند (bpms.masked_variables)
        $this->app()->make('config')->set('bpms.masked_variables', ['secret', 'password', 'token', 'national_id', 'card']);
        $response2 = $this->post('/admin/bpms/instances', [
            'definition_key' => 'leave-request',
            'business_key' => 'ADM-2',
            'variables' => '{"days": 1, "secret_note": "12345-67890"}',
        ]);
        $response2->assertRedirect();
        \preg_match('#/admin/bpms/instances/([0-9A-Z]{26})#', (string) $response2->header('Location'), $m2);
        $page = $this->get('/admin/bpms/instances/' . ($m2[1] ?? ''));
        $page->assertOk();
        $page->assertSee('***');
        $page->assertDontSee('12345-67890');
    }

    public function testTaskDelegationAndAdminAssignViaHttp(): void
    {
        $this->seedPublishedLeaveDefinition();
        $employee = $this->user('employee@bpms.test', 'کارمند', 'task-assignee');
        $colleague = $this->user('colleague@bpms.test', 'همکار', 'task-assignee');
        $admin = $this->user('padmin@bpms.test', 'مدیر فرایند', 'process-admin');

        // نمونه با task برای employee (initiator)
        $this->actingAs($employee);
        $this->post('/bpms/processes/leave-request/start', ['business_key' => 'DEL-1'])->assertRedirect();
        $tasks = $this->app()->make(TaskRepositoryInterface::class)->inboxFor($employee->id, []);
        self::assertCount(1, $tasks);
        $task = $tasks[0];

        // کاربر دیگر نمی‌تواند کارِ شخصی او را complete کند (redirect + task باز می‌ماند)
        $this->actingAs($colleague);
        $complete = $this->post('/bpms/tasks/' . $task->id() . '/complete', [
            'leave_type' => 'استحقاقی', 'days' => 1,
            'from_date' => '1405/08/10', 'to_date' => '1405/08/10',
        ]);
        $complete->assertRedirect();
        $untouched = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($untouched);
        self::assertTrue($untouched->status()->isOpen());
        self::assertNull($untouched->completedBy());

        // واگذاری توسط خودِ assignee
        $this->actingAs($employee);
        $this->post('/bpms/tasks/' . $task->id() . '/delegate', [
            'assignee_type' => 'user',
            'assignee_id' => (string) $colleague->id,
        ])->assertRedirect();

        $fresh = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($fresh);
        self::assertSame((string) $colleague->id, $fresh->assigneeId());

        // همکار اکنون می‌تواند کامل کند
        $this->actingAs($colleague);
        $this->post('/bpms/tasks/' . $task->id() . '/complete', [
            'leave_type' => 'استحقاقی', 'days' => 1,
            'from_date' => '1405/08/10', 'to_date' => '1405/08/10',
        ])->assertRedirect();
        $done = $this->app()->make(TaskRepositoryInterface::class)->findById($task->id());
        self::assertNotNull($done);
        self::assertSame('completed', $done->status()->value);

        // انتصاب اجباری مدیر روی کار بازِ نمونهٔ دوم
        $this->actingAs($admin);
        $this->post('/admin/bpms/instances', ['definition_key' => 'leave-request', 'business_key' => 'ADM-9'])->assertRedirect();
        $adminTasks = $this->app()->make(TaskRepositoryInterface::class)->paginate(['status' => 'pending'], 1, 10);
        self::assertGreaterThan(0, $adminTasks['total']);
        $pendingTask = $adminTasks['rows'][0];

        $this->post('/admin/bpms/tasks/' . $pendingTask->id() . '/assign', [
            'assignee_type' => 'user',
            'assignee_id' => (string) $employee->id,
        ])->assertRedirect();
        $assigned = $this->app()->make(TaskRepositoryInterface::class)->findById($pendingTask->id());
        self::assertNotNull($assigned);
        self::assertSame((string) $employee->id, $assigned->assigneeId());
        self::assertTrue(DB::table('tvn_audit_logs')->where('event', 'task.assigned')->exists());
    }

    public function testMonitoringDashboardRendersKpis(): void
    {
        Clock::setTestNow('2026-10-26 08:00:00');
        $this->seedPublishedLeaveDefinition();
        $admin = $this->user('padmin@bpms.test', 'مدیر فرایند', 'process-admin');

        // یک نمونه کامل (auto-approve) + یک نمونه در جریان
        $this->actingAs($admin);
        $this->post('/admin/bpms/instances', [
            'definition_key' => 'leave-request', 'business_key' => 'KPI-1',
        ])->assertRedirect();
        $tasks = $this->app()->make(TaskRepositoryInterface::class)->paginate(['status' => 'pending'], 1, 10);
        $task = $tasks['rows'][0];
        $this->post('/bpms/tasks/' . $task->id() . '/claim')->assertRedirect();
        $this->post('/bpms/tasks/' . $task->id() . '/complete', [
            'leave_type' => 'استحقاقی', 'days' => 1,
            'from_date' => '1405/08/10', 'to_date' => '1405/08/10',
        ])->assertRedirect();

        $this->post('/admin/bpms/instances', ['definition_key' => 'leave-request', 'business_key' => 'KPI-2'])->assertRedirect();

        $page = $this->get('/admin/bpms');
        $page->assertOk();
        $page->assertSee('پایش');
        $page->assertSee('گلوگاه');

        // آمار از سرویس — completed=1, running=1
        $monitoring = $this->app()->make(\Modules\Bpms\Application\UseCases\MonitoringService::class);
        $data = $monitoring->dashboard();
        self::assertSame(1, $data['instances']['completed']);
        self::assertSame(1, $data['instances']['running']);
        self::assertNotNull($data['sla']);
    }

    // ─── helpers ───────────────────────────────────────────────────────────

    /** تعریف leave-request منتشر/فعال + اکشن‌های ثبت‌شده (بدون HTTP). */
    private function seedPublishedLeaveDefinition(): void
    {
        $registry = $this->app()->make(ActionRegistry::class);
        foreach (['approveLeave', 'sendNotification', 'sendRejection'] as $action) {
            $registry->register($action, static function (ProcessContext $context): void {
                // بی‌اثر در تست HTTP
            });
        }

        $designer = $this->app()->make(\Modules\Bpms\Application\UseCases\DesignerService::class);
        $draft = $designer->createDraft([
            'key' => 'leave-request',
            'name' => 'درخواست مرخصی',
            'definition' => [
                'startEvent' => 'start',
                'elements' => [
                    ['id' => 'start', 'type' => 'startEvent', 'next' => 'submit'],
                    ['id' => 'submit', 'type' => 'userTask', 'name' => 'ثبت درخواست',
                     'assignee' => 'initiator', 'form' => 'leave_form', 'next' => 'check_days'],
                    ['id' => 'check_days', 'type' => 'exclusiveGateway', 'conditions' => [
                        ['expr' => 'variables.days <= 2', 'next' => 'auto_approve'],
                        ['default' => true, 'next' => 'manager_approval'],
                    ]],
                    ['id' => 'auto_approve', 'type' => 'serviceTask', 'action' => 'approveLeave', 'next' => 'notify'],
                    ['id' => 'manager_approval', 'type' => 'userTask', 'name' => 'تأیید مدیر',
                     'assignee' => 'role:manager', 'due' => 'P2D', 'next' => 'decision'],
                    ['id' => 'decision', 'type' => 'exclusiveGateway', 'conditions' => [
                        ['expr' => 'variables.approved', 'next' => 'notify'],
                        ['default' => true, 'next' => 'reject'],
                    ]],
                    ['id' => 'notify', 'type' => 'serviceTask', 'action' => 'sendNotification', 'next' => 'end'],
                    ['id' => 'reject', 'type' => 'serviceTask', 'action' => 'sendRejection', 'next' => 'end'],
                    ['id' => 'end', 'type' => 'endEvent'],
                ],
                'timers' => [['on' => 'manager_approval', 'after' => 'P2D', 'action' => 'escalate']],
            ],
            'form_schema' => [
                'leave_form' => ['id' => 'leave_form', 'fields' => [
                    ['name' => 'leave_type', 'type' => 'select', 'label' => 'نوع مرخصی',
                     'options' => ['استحقاقی', 'استعلاجی', 'بدون حقوق'], 'required' => true],
                    ['name' => 'days', 'type' => 'number', 'label' => 'تعداد روز', 'min' => 1, 'max' => 30, 'required' => true],
                    ['name' => 'from_date', 'type' => 'jalali_date', 'label' => 'از تاریخ', 'required' => true],
                    ['name' => 'to_date', 'type' => 'jalali_date', 'label' => 'تا تاریخ', 'required' => true],
                    ['name' => 'reason', 'type' => 'textarea', 'label' => 'دلیل'],
                ]],
            ],
        ]);
        $designer->publish($draft->id());
    }

    /** شروع یک نمونه به‌عنوان admin — شناسه نمونه. */
    private function startLeaveAsAdmin(User $admin): string
    {
        $engine = $this->app()->make(\Modules\Bpms\Application\UseCases\ProcessEngine::class);
        $instance = $engine->start('leave-request', [], $admin->id, 'TERM-1');

        return $instance->id();
    }

    private function seedRbac(): void
    {
        $permissions = [
            'bpms.definition.view', 'bpms.definition.manage', 'bpms.definition.publish',
            'bpms.instance.view', 'bpms.instance.view-all', 'bpms.instance.start',
            'bpms.instance.manage', 'bpms.instance.terminate',
            'bpms.task.view', 'bpms.task.view-all', 'bpms.task.claim', 'bpms.task.complete',
            'bpms.task.delegate', 'bpms.task.assign',
            'bpms.monitor.view', 'bpms.audit.view',
        ];
        foreach ($permissions as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.bpms');
            }
        }

        $taskPerms = ['bpms.task.view', 'bpms.task.claim', 'bpms.task.complete', 'bpms.task.delegate', 'bpms.instance.view', 'bpms.instance.start'];

        $rolePerms = [
            'process-designer' => ['bpms.definition.view', 'bpms.definition.manage', 'bpms.definition.publish', 'bpms.instance.view'],
            'process-admin' => $permissions,
            'task-assignee' => $taskPerms,
            'monitor' => ['bpms.monitor.view', 'bpms.definition.view', 'bpms.instance.view', 'bpms.instance.view-all', 'bpms.task.view-all'],
            'auditor' => ['bpms.audit.view', 'bpms.monitor.view', 'bpms.instance.view', 'bpms.instance.view-all', 'bpms.task.view-all', 'bpms.definition.view'],
            'manager' => $taskPerms,
            'admin' => $permissions,
        ];

        foreach ($rolePerms as $roleKey => $perms) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
            foreach ($perms as $permission) {
                $this->roles->grantPermissionToRole($roleKey, $permission);
            }
        }
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }
}
