<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Modules\Crm\Application\UseCases\ContactService;
use Modules\Crm\Application\UseCases\CrmReportService;
use Modules\Crm\Application\UseCases\LeadService;
use Modules\Crm\Application\UseCases\PipelineService;
use Modules\Crm\Application\UseCases\SlaMonitorService;
use Modules\Crm\Domain\Enums\LeadStatus;
use Modules\Crm\Domain\Enums\OpportunityStage;
use Modules\Crm\Domain\Enums\TicketStatus;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول CRM — سناریوهای DOC-096 §8 (E2E-01..06) + AC-CRM-07/08.
 *
 * مسیر واقعی HTTP + SQLite in-memory + RBAC واقعی + mail driver=array +
 * Workflow واقعی pipeline — بدون I/O خارجی.
 */
final class CrmE2ETest extends TestCase
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
        $config->set('session.csrf.except', ['admin/*', 'crm/*', 'api/*']);
        $config->set('app.url', 'http://localhost');
        $config->set('mail.default', 'array');
        $config->set('crm.search.enabled', false);
        $config->set('crm.sync.shop_orders', true);
        $config->set('crm.notifications', [
            'ticket_assigned' => true, 'ticket_replied' => true, 'sla_breached' => true,
            'ticket_resolved' => true, 'lead_assigned' => true, 'opportunity_won' => true,
        ]);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── E2E-01: lead → qualify → opportunity → won (AC-CRM-07) ────────────

    public function testE2E01_LeadToWonWithWorkflowPipeline(): void
    {
        $admin = $this->user('crm-admin@crm.test', 'مدیر CRM', 'crm-admin');
        $this->actingAs($admin);

        // ۱) مخاطب
        $this->post('/admin/crm/contacts', [
            'first_name' => 'سارا', 'last_name' => 'احمدی',
            'email' => 'sara@pars.test', 'company' => 'فناوری پارس', 'source' => 'website',
        ])->assertRedirect();
        $contact = $this->app()->make(ContactRepositoryInterface::class)->findByEmail('sara@pars.test');
        self::assertNotNull($contact);

        // ۲) سرنخ با ارزش بالا
        $this->post('/admin/crm/leads', [
            'contact_id' => $contact->id(),
            'title' => 'قرارداد سازمانی پارس',
            'value' => 250_000_000,
        ])->assertRedirect();
        $leads = $this->app()->make(LeadRepositoryInterface::class);
        $lead = $leads->forContact($contact->id())[0];
        self::assertSame(LeadStatus::New, $lead->status());

        // scoring خودکار: website(5) + company(15) + value>100M(25) + engagement(0) = 45
        self::assertSame(45, $lead->score());

        // ۳) qualify → convert
        $this->post('/admin/crm/leads/' . $lead->id() . '/qualify')->assertRedirect();
        $convertResponse = $this->post('/admin/crm/leads/' . $lead->id() . '/convert', [
            'title' => 'قرارداد سازمانی پارس',
            'value' => 250_000_000,
        ]);
        $convertResponse->assertRedirect();
        \preg_match('#/admin/crm/opportunities/([0-9A-Z]{26})#', (string) $convertResponse->header('Location'), $m);
        $oppId = $m[1] ?? '';
        self::assertNotSame('', $oppId);

        $leads = $this->app()->make(LeadRepositoryInterface::class);
        $lead = $leads->findById($lead->id());
        self::assertSame(LeadStatus::Converted, $lead->status());
        self::assertSame($oppId, $lead->convertedTo());

        // ۴) AC-CRM-07 — نمونهٔ Workflow pipeline ساخته شده
        $opps = $this->app()->make(OpportunityRepositoryInterface::class);
        $opportunity = $opps->findById($oppId);
        self::assertNotNull($opportunity);
        self::assertSame(OpportunityStage::Prospecting, $opportunity->stage());
        self::assertSame(10, $opportunity->probability());
        self::assertNotNull($opportunity->workflowInstanceId());
        self::assertTrue(DB::table('tvn_process_instances')->where('id', $opportunity->workflowInstanceId())->exists());

        // ۵) حرکت در pipeline: qualification → proposal → negotiation → won
        foreach (['qualification', 'proposal', 'negotiation'] as $stage) {
            $this->post('/admin/crm/opportunities/' . $oppId . '/move', ['stage' => $stage])->assertRedirect();
        }
        $opportunity = $opps->findById($oppId);
        self::assertSame(OpportunityStage::Negotiation, $opportunity->stage());
        self::assertSame(75, $opportunity->probability());

        // نمونهٔ workflow هم‌گام با دامنه پیش رفته
        $wf = DB::table('tvn_process_instances')->where('id', $opportunity->workflowInstanceId())->first();
        self::assertNotNull($wf);

        $this->post('/admin/crm/opportunities/' . $oppId . '/move', ['stage' => 'closed_won'])->assertRedirect();
        $won = $opps->findById($oppId);
        self::assertTrue($won->stage()->isWon());
        self::assertNotNull($won->closedAt());

        // اعلان won برای مالک (database)
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $admin->id)
            ->where('data', 'LIKE', '%crm.opportunity.won%')
            ->exists());

        // ۶) گزارش — win rate ۱۰۰٪
        $report = $this->get('/admin/crm/reports');
        $report->assertOk();
        $overview = $this->app()->make(CrmReportService::class)->overview();
        self::assertSame(100.0, $overview['win_loss']['win_rate']);
        self::assertSame(1, $overview['win_loss']['won']);
    }

    // ─── E2E-02: lead scoring ──────────────────────────────────────────────

    public function testE2E02_LeadScoringAutomatic(): void
    {
        $admin = $this->user('crm-admin2@crm.test', 'مدیر CRM ۲', 'crm-admin');
        $this->actingAs($admin);

        // مخاطب referral با شرکت
        $contactService = $this->app()->make(ContactService::class);
        $contact = $contactService->create([
            'first_name' => 'رضا', 'last_name' => 'کریمی',
            'email' => 'reza@ref.test', 'company' => 'بازرگانی نگین', 'source' => 'referral',
        ], $admin->id);

        $leadService = $this->app()->make(LeadService::class);
        $lead = $leadService->create($contact->id(), ['title' => 'سرنخ ارجاعی', 'value' => 150_000_000], $admin->id);

        // referral(20) + company(15) + >100M(25) = 60
        self::assertSame(60, $lead->score());

        // ثبت فعالیت → engagement +5 → 65
        $this->post('/admin/crm/activities', [
            'activity_type' => 'call',
            'subject' => 'تماس اولیه',
            'lead_id' => $lead->id(),
            'contact_id' => $contact->id(),
        ])->assertRedirect();

        $leads = $this->app()->make(LeadRepositoryInterface::class);
        self::assertSame(65, $leads->findById($lead->id())->score());

        // صفحهٔ سرنخ امتیاز را نشان می‌دهد
        $page = $this->get('/admin/crm/leads/' . $lead->id());
        $page->assertOk();
        $page->assertSee('امتیاز ' . trans_number(65));

        // ویرایش ارزش به زیر آستانه → 20+15+10+5 = 50
        $this->post('/admin/crm/leads/' . $lead->id(), ['title' => 'سرنخ ارجاعی', 'value' => 5_000_000])->assertRedirect();
        self::assertSame(50, $leads->findById($lead->id())->score());
    }

    // ─── E2E-03: تیکت با SLA + escalation (AC-CRM-08) ──────────────────────

    public function testE2E03_TicketSlaAndEscalation(): void
    {
        $manager = $this->user('sup-manager@crm.test', 'مدیر پشتیبانی', 'support-manager');
        $agent = $this->user('sup-agent@crm.test', 'کارشناس پشتیبانی', 'support-agent');

        // ۱) ثبت تیکت از پرتال عمومی (مهمان)
        $response = $this->post('/crm/support', [
            'name' => 'نگار موسوی',
            'email' => 'negar@mail.test',
            'subject' => 'مشکل ورود به پنل',
            'body' => 'رمز را ریست کردم ولی هنوز وارد نمی‌شود.',
        ]);
        $response->assertRedirect();
        \preg_match('#/crm/support/(TKT-[0-9A-Z-]+)#', (string) $response->header('Location'), $m);
        $number = $m[1] ?? '';
        self::assertNotSame('', $number);

        $tickets = $this->app()->make(TicketRepositoryInterface::class);
        $ticket = $tickets->findByNumber($number);
        self::assertNotNull($ticket);
        self::assertSame(TicketStatus::Open, $ticket->status());
        self::assertNotNull($ticket->slaDueAt());

        // مخاطب خودکار (source=support)
        $contacts = $this->app()->make(ContactRepositoryInterface::class);
        $contact = $contacts->findByEmail('negar@mail.test');
        self::assertNotNull($contact);
        self::assertSame('support', $contact->source());

        // صفحهٔ پیگیری عمومی
        $portal = $this->get('/crm/support/' . $number);
        $portal->assertOk();
        $portal->assertSee('مشکل ورود به پنل');

        // ۲) assign توسط manager → اعلان برای agent
        $this->actingAs($manager);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/assign', ['assigned_to' => $agent->id])->assertRedirect();
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $agent->id)
            ->where('data', 'LIKE', '%crm.ticket.assigned%')
            ->exists());

        // ۳) پاسخ agent → pending (توقف SLA)
        $this->actingAs($agent);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/replies', [
            'body' => 'سلام — کش مرورگر را پاک کنید و دوباره تلاش کنید.',
        ])->assertRedirect();
        self::assertSame(TicketStatus::Pending, $tickets->findById($ticket->id())->status());

        // پاسخ مشتری در پرتال → open
        $this->post('/crm/support/' . $number . '/replies', ['body' => 'انجام شد ولی هنوز مشکل دارم.'])->assertRedirect();
        self::assertSame(TicketStatus::Open, $tickets->findById($ticket->id())->status());

        // ۴) SLA گذشته → escalation خودکار (AC-CRM-08)
        DB::table('crm_tickets')->where('id', $ticket->id())->update([
            'sla_due_at' => (new \DateTimeImmutable('-30 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);

        $monitor = $this->app()->make(SlaMonitorService::class);
        $result = $monitor->escalateOverdue();
        self::assertSame(1, $result['escalated']);
        self::assertSame([$number], $result['ticket_numbers']);

        $escalated = $tickets->findById($ticket->id());
        self::assertTrue($escalated->isEscalated());

        // اعلان escalation به support-manager (نقش config)
        self::assertTrue(DB::table('tvn_notifications')
            ->where('notifiable_id', (string) $manager->id)
            ->where('data', 'LIKE', '%crm.ticket.sla_breached%')
            ->exists());

        // idempotent — اجرای مجدد monitor اثر ندارد
        self::assertSame(0, $monitor->escalateOverdue()['escalated']);

        // ۵) resolve → اعلان رضایت برای مشتری (contact)
        $this->actingAs($agent);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/resolve')->assertRedirect();
        self::assertSame(TicketStatus::Resolved, $tickets->findById($ticket->id())->status());

        // بستن
        $this->actingAs($manager);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/close')->assertRedirect();
        self::assertSame(TicketStatus::Closed, $tickets->findById($ticket->id())->status());
    }

    public function testSlaPausedWhilePending(): void
    {
        $manager = $this->user('sup-manager2@crm.test', 'مدیر پ ۲', 'support-manager');
        $agent = $this->user('sup-agent2@crm.test', 'کارشناس ۲', 'support-agent');

        // تیکت دوم — پاسخ agent → pending، سپس SLA گذشته → escalate نمی‌شود
        $this->post('/crm/support', [
            'name' => 'علی رضایی', 'email' => 'ali@simorgh.test',
            'subject' => 'سؤال درباره فاکتور', 'body' => 'فاکتور مهر را لطفاً ایمیل کنید.',
        ])->assertRedirect();

        $tickets = $this->app()->make(TicketRepositoryInterface::class);
        $all = $tickets->paginate([], 1, 50);
        $ticket = null;
        foreach ($all['rows'] as $row) {
            if ($row->subject() === 'سؤال درباره فاکتور') {
                $ticket = $row;
            }
        }
        self::assertNotNull($ticket);

        $this->actingAs($manager);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/assign', ['assigned_to' => $agent->id])->assertRedirect();
        $this->actingAs($agent);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/replies', ['body' => 'فاکتور ارسال شد.'])->assertRedirect();

        DB::table('crm_tickets')->where('id', $ticket->id())->update([
            'sla_due_at' => (new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);

        // pending → ساعت SLA متوقف — escalation رخ نمی‌دهد
        $result = $this->app()->make(SlaMonitorService::class)->escalateOverdue();
        self::assertSame(0, $result['escalated']);
        self::assertFalse($tickets->findById($ticket->id())->isEscalated());
    }

    // ─── E2E-04: campaign email ────────────────────────────────────────────

    public function testE2E04_CampaignEmailToAudience(): void
    {
        $marketing = $this->user('marketing@crm.test', 'کارشناس بازاریابی', 'marketing');
        $contactService = $this->app()->make(ContactService::class);

        // دو مخاطب vip + یک مخاطب بدون برچسب
        $contactService->create(['first_name' => 'سارا', 'last_name' => 'احمدی', 'email' => 'sara@vip.test', 'tags' => ['vip']], $marketing->id);
        $contactService->create(['first_name' => 'مریم', 'last_name' => 'نوری', 'email' => 'm@vip.test', 'tags' => 'vip'], $marketing->id);
        $plain = $contactService->create(['first_name' => 'حسن', 'last_name' => 'عادی', 'email' => 'h@plain.test'], $marketing->id);

        $this->actingAs($marketing);

        // ساخت کمپین
        $this->post('/admin/crm/campaigns', [
            'name' => 'تخفیف ویژهٔ VIP',
            'type' => 'email',
            'template' => 'سلام {first_name}، تخفیف ویژهٔ شما فعال شد.',
            'audience' => '{"tags":["vip"]}',
        ])->assertRedirect();

        $campaignRow = DB::table('crm_campaigns')->where('name', 'تخفیف ویژهٔ VIP')->first();
        self::assertNotNull($campaignRow);
        $campaignId = (string) $campaignRow['id'];

        // ارسال
        $this->post('/admin/crm/campaigns/' . $campaignId . '/send')->assertRedirect();

        $sent = DB::table('crm_campaigns')->where('id', $campaignId)->first();
        self::assertSame('sent', (string) $sent['status']);
        self::assertSame(2, (int) $sent['sent_count']);
        self::assertSame(0, (int) $sent['failed_count']);

        // گیرندگان — فقط vipها
        $recipients = DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->get();
        self::assertCount(2, $recipients);
        $addresses = \array_map(static fn (array $r): string => (string) $r['address'], $recipients);
        \sort($addresses);
        self::assertSame(['m@vip.test', 'sara@vip.test'], $addresses);
        self::assertNotContains('h@plain.test', $addresses);

        // ارسال مجدد — idempotent (uk recipient)
        DB::table('crm_campaigns')->where('id', $campaignId)->update(['status' => 'draft']);
        $this->app()->make(\Modules\Crm\Application\UseCases\CampaignService::class)
            ->send($this->app()->make(\Modules\Crm\Domain\Repositories\CampaignRepositoryInterface::class)->findById($campaignId));
        self::assertSame(2, DB::table('crm_campaign_recipients')->where('campaign_id', $campaignId)->count());
    }

    // ─── E2E-05: گزارش pipeline ────────────────────────────────────────────

    public function testE2E05_PipelineReportAndCsv(): void
    {
        $admin = $this->user('crm-admin3@crm.test', 'مدیر CRM ۳', 'crm-admin');
        $contactService = $this->app()->make(ContactService::class);
        $pipeline = $this->app()->make(PipelineService::class);

        $contact = $contactService->create(['first_name' => 'تست', 'email' => 'report@crm.test'], $admin->id);

        // ۳ فرصت: proposal(50٪×100M) negotiation(75٪×200M) won(300M)
        $o1 = $pipeline->create($contact->id(), 'فرصت یک', 100_000_000, ownerId: $admin->id);
        $o2 = $pipeline->create($contact->id(), 'فرصت دو', 200_000_000, ownerId: $admin->id);
        $o3 = $pipeline->create($contact->id(), 'فرصت سه', 300_000_000, ownerId: $admin->id);

        $pipeline->moveToStage($o1, OpportunityStage::Proposal);
        $pipeline->moveToStage($o2, OpportunityStage::Negotiation);
        $pipeline->win($o3);

        $this->actingAs($admin);
        $report = $this->get('/admin/crm/reports');
        $report->assertOk();
        $report->assertSee('پیش‌بینی وزنی');

        // weighted = 100M×50٪ + 200M×75٪ = 50M + 150M = 200M
        $overview = $this->app()->make(CrmReportService::class)->overview();
        self::assertSame(200_000_000, $overview['pipeline']['weighted']);
        self::assertSame(300_000_000, $overview['pipeline']['total_value']);
        self::assertSame(2, $overview['pipeline']['count']);
        self::assertSame(100.0, $overview['win_loss']['win_rate']);   // 1 won / 0 lost

        // CSV
        $csv = $this->get('/admin/crm/reports/pipeline.csv');
        $csv->assertOk();
        self::assertStringContainsString('text/csv', (string) $csv->header('Content-Type'));
        $body = $csv->content();
        self::assertTrue(\str_starts_with($body, "\xEF\xBB\xBF"), 'CSV باید BOM داشته باشد');
        self::assertStringContainsString('pipeline,"proposal",1,100000000,50000000', $body);

        // نقش بدون crm.report.view → ۴۰۳
        $agent = $this->user('sales-agent-r@crm.test', 'کارشناس فروش', 'sales-agent');
        $this->actingAs($agent)->get('/admin/crm/reports')->assertStatus(403);
    }

    // ─── E2E-06: sync مشتری از shop ───────────────────────────────────────

    public function testE2E06_ShopCustomerSyncViaEvent(): void
    {
        $customer = $this->users->create(name: 'مشتری فروشگاه', email: 'buyer@shop.test', plainPassword: 'test-12345');

        // رویداد shop.order.paid (بدون ماژول shop — فقط payload رشته‌ای)
        Event::dispatch('shop.order.paid', [
            'order_id' => 'ord-1',
            'order_number' => 'SH-20261025-AB12C',
            'customer_id' => $customer->id,
            'total_minor' => 4_500_000,
            'currency' => 'IRR',
            'authority' => 'sbx-x',
        ]);

        $contacts = $this->app()->make(ContactRepositoryInterface::class);
        $contact = $contacts->findByEmail('buyer@shop.test');
        self::assertNotNull($contact, 'مخاطب باید از رویداد shop ساخته می‌شد');
        self::assertSame('shop', $contact->source());
        self::assertSame($customer->id, $contact->userId());
        self::assertSame('مشتری', $contact->firstName());
        self::assertSame('SH-20261025-AB12C', $contact->customFields()['last_order']);
        self::assertSame(4_500_000, $contact->customFields()['last_order_total']);

        // سفارش دوم — همان مخاطب به‌روزرسانی می‌شود (idempotent)
        Event::dispatch('shop.order.paid', [
            'order_id' => 'ord-2',
            'order_number' => 'SH-20261026-CD34E',
            'customer_id' => $customer->id,
            'total_minor' => 9_000_000,
            'currency' => 'IRR',
            'authority' => 'sbx-y',
        ]);

        self::assertSame(1, DB::table('crm_contacts')->where('email', 'buyer@shop.test')->count());
        $updated = $contacts->findByEmail('buyer@shop.test');
        self::assertSame('SH-20261026-CD34E', $updated->customFields()['last_order']);
        self::assertSame(9_000_000, $updated->customFields()['last_order_total']);
    }

    // ─── API یکپارچگی (DOC-096 §7) ────────────────────────────────────────

    public function testApiLeadAndTicketIntake(): void
    {
        // lead از فرم وب‌سایت
        $leadResponse = $this->postJson('/api/v1/crm/leads', [
            'name' => 'وحید تستی',
            'email' => 'vahid@web.test',
            'title' => 'درخواست دمو',
            'value' => 50_000_000,
            'source' => 'website',
        ]);
        $leadResponse->assertOk();
        $leadResponse->assertJson(['data' => ['status' => 'new']]);
        $json = $leadResponse->json();
        self::assertNotSame('', (string) ($json['data']['lead_id'] ?? ''));
        // website(5) + بدون شرکت(0) + ≤100M(10) = 15
        self::assertSame(15, (int) $json['data']['score']);

        // مخاطب ساخته شد
        self::assertNotNull($this->app()->make(ContactRepositoryInterface::class)->findByEmail('vahid@web.test'));

        // ticket از API
        $ticketResponse = $this->postJson('/api/v1/crm/tickets', [
            'name' => 'وحید تستی',
            'email' => 'vahid@web.test',
            'subject' => 'باگ در داشبورد',
            'body' => 'صفحهٔ داشبورد با خطا باز می‌شود.',
        ]);
        $ticketResponse->assertOk();
        $number = (string) ($ticketResponse->json()['data']['ticket_number'] ?? '');
        self::assertTrue(\str_starts_with($number, 'TKT-'));

        // وضعیت تیکت
        $statusResponse = $this->get('/api/v1/crm/tickets/' . $number);
        $statusResponse->assertOk();
        $statusResponse->assertJson(['data' => ['status' => 'open', 'escalated' => false]]);

        // validation → 422
        $bad = $this->postJson('/api/v1/crm/leads', ['name' => 'بدون ایمیل', 'title' => 'x']);
        $bad->assertStatus(422);
    }

    // ─── نقش‌ها و دسترسی‌ها ────────────────────────────────────────────────

    public function testRoleMatrix(): void
    {
        $admin = $this->user('crm-admin4@crm.test', 'مدیر CRM ۴', 'crm-admin');
        $salesA = $this->user('sales-a@crm.test', 'فروشنده الف', 'sales-agent');
        $salesB = $this->user('sales-b@crm.test', 'فروشنده ب', 'sales-agent');

        $contactService = $this->app()->make(ContactService::class);
        $leadService = $this->app()->make(LeadService::class);
        $contact = $contactService->create(['first_name' => 'مشترک', 'email' => 'shared@crm.test'], $admin->id);

        // سرنخ متعلق به salesA
        $this->actingAs($salesA);
        $this->post('/admin/crm/leads', ['contact_id' => $contact->id(), 'title' => 'سرنخ الف', 'value' => 1_000_000])->assertRedirect();
        // سرنخ متعلق به admin
        $this->actingAs($admin);
        $this->post('/admin/crm/leads', ['contact_id' => $contact->id(), 'title' => 'سرنخ مدیر', 'value' => 2_000_000])->assertRedirect();

        // sales-agent → فهرست فقط سرنخ‌های خودش
        $this->actingAs($salesA);
        $mine = $this->get('/admin/crm/leads');
        $mine->assertOk();
        $mine->assertSee('سرنخ الف');
        $mine->assertDontSee('سرنخ مدیر');

        // sales-agent → ویرایش سرنخ دیگری ۴۰۳
        $adminLead = null;
        foreach ($this->app()->make(LeadRepositoryInterface::class)->forContact($contact->id()) as $lead) {
            if ($lead->title() === 'سرنخ مدیر') {
                $adminLead = $lead;
            }
        }
        self::assertNotNull($adminLead);
        $this->actingAs($salesA)->post('/admin/crm/leads/' . $adminLead->id() . '/qualify')->assertStatus(403);

        // sales-agent → کمپین/گزارش ۴۰۳
        $this->get('/admin/crm/campaigns')->assertStatus(403);
        $this->get('/admin/crm/reports')->assertStatus(403);

        // marketing → کمپین ۲۰۰ ولی تیکت (بدون مجوز) ۴۰۳
        $marketing = $this->user('mkt2@crm.test', 'بازاریاب ۲', 'marketing');
        $this->actingAs($marketing)->get('/admin/crm/campaigns')->assertOk();
        $this->get('/admin/crm/tickets')->assertStatus(403);

        // support-agent → فقط assignedهای خود
        $support = $this->user('sup3@crm.test', 'پشتیبان ۳', 'support-agent');
        $this->actingAs($support)->get('/admin/crm/tickets')->assertOk();   // بدون crash — فهرست خالیِ خود

        // مهمان → redirect
        $guest = $this->withOutActor();
        $guest->get('/admin/crm/leads')->assertRedirect();
    }

    // ─── امنیت ────────────────────────────────────────────────────────────

    public function testTicketXssAndInternalNotesHidden(): void
    {
        // XSS در پرتال → sanitize (plain)
        $this->post('/crm/support', [
            'name' => 'هکر', 'email' => 'hacker@xss.test',
            'subject' => 'تست امنیت',
            'body' => '<script>alert(1)</script> مشکل <b>اینجاست</b>',
        ])->assertRedirect();

        $tickets = $this->app()->make(TicketRepositoryInterface::class);
        $ticket = null;
        foreach ($tickets->paginate(['search' => 'تست امنیت'], 1, 10)['rows'] as $row) {
            $ticket = $row;
        }
        self::assertNotNull($ticket);

        // پرتال عمومی — اسکریپت خام دیده نمی‌شود
        $portal = $this->get('/crm/support/' . $ticket->ticketNumber());
        $portal->assertOk();
        self::assertStringNotContainsString('<script>alert', $portal->content());

        // یادداشت داخلی در پرتال مشتری دیده نمی‌شود
        $manager = $this->user('sup-m4@crm.test', 'مدیر پ ۴', 'support-manager');
        $this->actingAs($manager);
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/assign', ['assigned_to' => $manager->id])->assertRedirect();
        $this->post('/admin/crm/tickets/' . $ticket->id() . '/replies', [
            'body' => 'یادداشت محرمانهٔ تیم — فاش نشود XYZ123',
            'internal' => '1',
        ])->assertRedirect();

        // status عوض نشد (internal)
        self::assertSame(TicketStatus::Open, $tickets->findById($ticket->id())->status());

        $publicPage = $this->withOutActor()->get('/crm/support/' . $ticket->ticketNumber());
        $publicPage->assertOk();
        self::assertStringNotContainsString('XYZ123', $publicPage->content());

        // ولی در پنل agent دیده می‌شود
        $this->actingAs($manager);
        $adminPage = $this->get('/admin/crm/tickets/' . $ticket->id());
        $adminPage->assertOk();
        $adminPage->assertSee('XYZ123');
    }

    public function testNationalIdEncryptedAtRestAndMasked(): void
    {
        $admin = $this->user('crm-admin5@crm.test', 'مدیر CRM ۵', 'crm-admin');
        $this->actingAs($admin);

        $this->post('/admin/crm/contacts', [
            'first_name' => 'رمز', 'last_name' => 'نگار',
            'email' => 'enc@crm.test', 'national_id' => '0012345678',
        ])->assertRedirect();

        // در DB — plaintext نیست
        $row = DB::table('crm_contacts')->where('email', 'enc@crm.test')->first();
        self::assertNotNull($row);
        self::assertNotSame('0012345678', (string) $row['national_id']);
        self::assertStringNotContainsString('0012345678', (string) $row['national_id']);

        // repository رمزگشایی می‌کند
        $contact = $this->app()->make(ContactRepositoryInterface::class)->findByEmail('enc@crm.test');
        self::assertSame('0012345678', $contact->nationalId());

        // ویو — mask
        $page = $this->get('/admin/crm/contacts/' . $contact->id());
        $page->assertOk();
        $page->assertSee('******5678');
        $page->assertDontSee('0012345678');
    }

    // ─── helperها ─────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        $permissions = [
            'crm.contact.view', 'crm.contact.create', 'crm.contact.update', 'crm.contact.delete', 'crm.contact.assign',
            'crm.lead.view', 'crm.lead.create', 'crm.lead.update', 'crm.lead.convert', 'crm.lead.assign',
            'crm.opportunity.view', 'crm.opportunity.create', 'crm.opportunity.update', 'crm.opportunity.move', 'crm.opportunity.assign',
            'crm.activity.manage',
            'crm.ticket.view', 'crm.ticket.create', 'crm.ticket.assign', 'crm.ticket.reply', 'crm.ticket.resolve',
            'crm.campaign.view', 'crm.campaign.manage', 'crm.campaign.send',
            'crm.report.view',
        ];
        foreach ($permissions as $key) {
            if (!DB::table('tvn_permissions')->where('key', $key)->exists()) {
                $this->roles->createPermission($key, 'tavaan.crm');
            }
        }

        $rolePerms = [
            'sales-agent' => ['crm.contact.view', 'crm.lead.create', 'crm.opportunity.create', 'crm.activity.manage'],
            'sales-manager' => [
                'crm.contact.view', 'crm.contact.create', 'crm.contact.update', 'crm.contact.assign',
                'crm.lead.view', 'crm.lead.create', 'crm.lead.update', 'crm.lead.convert', 'crm.lead.assign',
                'crm.opportunity.view', 'crm.opportunity.create', 'crm.opportunity.update', 'crm.opportunity.move', 'crm.opportunity.assign',
                'crm.activity.manage', 'crm.report.view',
            ],
            'support-agent' => ['crm.ticket.reply', 'crm.ticket.resolve', 'crm.activity.manage'],
            'support-manager' => ['crm.ticket.view', 'crm.ticket.create', 'crm.ticket.assign', 'crm.ticket.reply', 'crm.ticket.resolve', 'crm.activity.manage', 'crm.report.view'],
            'marketing' => ['crm.campaign.view', 'crm.campaign.manage', 'crm.campaign.send', 'crm.contact.view', 'crm.contact.create', 'crm.contact.update', 'crm.activity.manage'],
            'crm-admin' => $permissions,
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

    private function user(string $email, string $name, string $role = 'crm-admin'): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    /** حذف بازیگر جاری (برای درخواست‌های مهمان). */
    private function withOutActor(): static
    {
        $this->app()->make(\Tavaan\Engine\Auth\AuthManager::class)->guard('web')->logout();

        return $this;
    }
}
