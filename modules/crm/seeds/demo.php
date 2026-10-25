<?php

declare(strict_types=1);

/*
 * ماژول CRM — seed دمو (DOC-096؛ kit:install crm --with-demo).
 *
 * دادهٔ نمایشی: ۵ مخاطب، ۳ سرنخ (new/qualified/converted)، فرصت‌ها در
 * مرحله‌های pipeline (با won/lost برای گزارش)، فعالیت‌ها، ۳ تیکت
 * (open/pending/resolved — یکی با SLA گذشته برای نمایش escalation) و یک
 * کمپین پیش‌نویس. idempotent — با کلید موجود، رد می‌شود.
 */

use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Ulid;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = 'Y-m-d H:i:s.v';

    // کارشناسان — از کاربران دمو (core-user)
    $admin = DB::table('users')->where('email', 'admin@blog.test')->first();
    $sales = DB::table('users')->where('email', 'author@blog.test')->first();
    $support = DB::table('users')->where('email', 'editor@blog.test')->first();
    if ($admin === null || $sales === null || $support === null) {
        return;
    }
    $adminId = (int) $admin['id'];
    $salesId = (int) $sales['id'];
    $supportId = (int) $support['id'];

    // ── مخاطبان ───────────────────────────────────────────────────────────
    $contacts = [];
    foreach ([
        ['first' => 'سارا', 'last' => 'احمدی', 'company' => 'فناوری پارس', 'title' => 'مدیر خرید', 'email' => 'sara@pars-tech.test', 'mobile' => '09121234567', 'source' => 'website', 'tags' => ['vip', 'tehran']],
        ['first' => 'رضا', 'last' => 'کریمی', 'company' => null, 'title' => null, 'email' => 'reza.karimi@mail.test', 'mobile' => '09351112233', 'source' => 'referral', 'tags' => ['referral']],
        ['first' => 'مریم', 'last' => 'نوری', 'company' => 'بازرگانی نگین', 'title' => 'مدیرعامل', 'email' => 'm.nouri@negin.test', 'mobile' => '09127778899', 'source' => 'campaign', 'tags' => ['vip']],
        ['first' => 'علی', 'last' => 'رضایی', 'company' => 'استارتاپ سیمرغ', 'title' => 'بنیان‌گذار', 'email' => 'ali@simorgh.test', 'mobile' => '09901234567', 'source' => 'import', 'tags' => ['startup']],
        ['first' => 'نگار', 'last' => 'موسوی', 'company' => null, 'title' => null, 'email' => 'negar.m@mail.test', 'mobile' => '09331239876', 'source' => 'support', 'tags' => []],
    ] as $i => $data) {
        $emailKey = 'demo-' . $i;
        $exists = DB::table('crm_contacts')->where('email', $data['email'])->first();
        if ($exists !== null) {
            $contacts[$emailKey] = (string) $exists['id'];

            continue;
        }
        $id = Ulid::generate($now)->toString();
        DB::table('crm_contacts')->insert([
            'id' => $id,
            'first_name' => $data['first'],
            'last_name' => $data['last'],
            'company' => $data['company'],
            'title' => $data['title'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'phone' => null,
            'national_id' => null,
            'source' => $data['source'],
            'owner_id' => $i % 2 === 0 ? $salesId : $adminId,
            'user_id' => null,
            'status' => 'active',
            'tags' => (string) json_encode($data['tags'], JSON_UNESCAPED_UNICODE),
            'custom_fields' => '{}',
            'created_at' => $now->sub(new DateInterval('P' . (30 - $i * 4) . 'D'))->format($fmt),
            'updated_at' => null,
            'deleted_at' => null,
        ]);
        $contacts[$emailKey] = $id;
    }

    // ── سرنخ‌ها (با امتیازهای پیش‌محاسبهٔ الگوریتم §3.2) ───────────────────
    // referral(20)+company(15)+value>100M(25) = 60 • website(5)+0+10 = 15 • …
    $leads = [
        ['contact' => 'demo-1', 'title' => 'خرید لایسنس سازمانی — پارس', 'value' => 250_000_000, 'status' => 'new', 'score' => 40, 'owner' => $salesId, 'source' => 'referral'],
        ['contact' => 'demo-2', 'title' => 'استقرار CRM برای نگین', 'value' => 80_000_000, 'status' => 'qualified', 'score' => 30, 'owner' => $adminId, 'source' => 'campaign'],
        ['contact' => 'demo-3', 'title' => 'پکیج پشتیبانی سیمرغ', 'value' => 15_000_000, 'status' => 'lost', 'score' => 15, 'owner' => $salesId, 'source' => 'import', 'lost' => 'بودجه نداشتند'],
    ];
    foreach ($leads as $data) {
        $contactId = $contacts[$data['contact']] ?? null;
        if ($contactId === null) {
            continue;
        }
        if (DB::table('crm_leads')->where('contact_id', $contactId)->where('title', $data['title'])->exists()) {
            continue;
        }
        DB::table('crm_leads')->insert([
            'id' => Ulid::generate($now)->toString(),
            'contact_id' => $contactId,
            'title' => $data['title'],
            'value' => $data['value'],
            'currency' => 'IRR',
            'status' => $data['status'],
            'score' => $data['score'],
            'owner_id' => $data['owner'],
            'converted_to' => null,
            'lost_reason' => $data['lost'] ?? null,
            'source' => $data['source'],
            'created_at' => $now->sub(new DateInterval('P10D'))->format($fmt),
            'updated_at' => null,
        ]);
    }

    // ── فرصت‌ها در مرحله‌های pipeline (برای گزارش/forecast) ────────────────
    if (!DB::table('crm_opportunities')->where('title', 'قرارداد سالانهٔ پارس')->exists()) {
        $wonId = Ulid::generate($now)->toString();
        DB::table('crm_opportunities')->insert([
            'id' => $wonId,
            'contact_id' => $contacts['demo-0'],
            'lead_id' => null,
            'title' => 'قرارداد سالانهٔ پارس',
            'value' => 450_000_000,
            'currency' => 'IRR',
            'stage' => 'closed_won',
            'probability' => 100,
            'expected_close_date' => $now->sub(new DateInterval('P5D'))->format('Y-m-d'),
            'owner_id' => $salesId,
            'lost_reason' => null,
            'workflow_instance_id' => null,
            'created_at' => $now->sub(new DateInterval('P45D'))->format($fmt),
            'updated_at' => $now->sub(new DateInterval('P5D'))->format($fmt),
            'closed_at' => $now->sub(new DateInterval('P5D'))->format($fmt),
        ]);

        DB::table('crm_opportunities')->insert([
            'id' => Ulid::generate($now)->toString(),
            'contact_id' => $contacts['demo-2'],
            'lead_id' => null,
            'title' => 'استقرار فاز دوم نگین',
            'value' => 180_000_000,
            'currency' => 'IRR',
            'stage' => 'negotiation',
            'probability' => 75,
            'expected_close_date' => $now->add(new DateInterval('P10D'))->format('Y-m-d'),
            'owner_id' => $adminId,
            'lost_reason' => null,
            'workflow_instance_id' => null,
            'created_at' => $now->sub(new DateInterval('P20D'))->format($fmt),
            'updated_at' => null,
            'closed_at' => null,
        ]);

        DB::table('crm_opportunities')->insert([
            'id' => Ulid::generate($now)->toString(),
            'contact_id' => $contacts['demo-3'],
            'lead_id' => null,
            'title' => 'پکیج تیمی سیمرغ',
            'value' => 60_000_000,
            'currency' => 'IRR',
            'stage' => 'proposal',
            'probability' => 50,
            'expected_close_date' => $now->add(new DateInterval('P20D'))->format('Y-m-d'),
            'owner_id' => $salesId,
            'lost_reason' => null,
            'workflow_instance_id' => null,
            'created_at' => $now->sub(new DateInterval('P8D'))->format($fmt),
            'updated_at' => null,
            'closed_at' => null,
        ]);

        DB::table('crm_opportunities')->insert([
            'id' => Ulid::generate($now)->toString(),
            'contact_id' => $contacts['demo-1'],
            'lead_id' => null,
            'title' => 'تمدید پشتیبانی رضایی',
            'value' => 25_000_000,
            'currency' => 'IRR',
            'stage' => 'closed_lost',
            'probability' => 0,
            'expected_close_date' => $now->sub(new DateInterval('P2D'))->format('Y-m-d'),
            'owner_id' => $salesId,
            'lost_reason' => 'انتخاب رقیب (قیمت)',
            'workflow_instance_id' => null,
            'created_at' => $now->sub(new DateInterval('P30D'))->format($fmt),
            'updated_at' => $now->sub(new DateInterval('P2D'))->format($fmt),
            'closed_at' => $now->sub(new DateInterval('P2D'))->format($fmt),
        ]);
    }

    // ── فعالیت‌ها ─────────────────────────────────────────────────────────
    if (!DB::table('crm_activities')->where('subject', 'تماس معرفی — پارس')->exists()) {
        DB::table('crm_activities')->insert([
            'id' => Ulid::generate($now)->toString(),
            'activity_type' => 'call',
            'subject' => 'تماس معرفی — پارس',
            'body' => 'بررسی نیازمندی‌های فاز دوم؛ جلسهٔ دمو هماهنگ شد.',
            'contact_id' => $contacts['demo-0'],
            'lead_id' => null,
            'opportunity_id' => null,
            'ticket_id' => null,
            'user_id' => $salesId,
            'due_at' => null,
            'completed_at' => $now->sub(new DateInterval('P3D'))->format($fmt),
            'created_at' => $now->sub(new DateInterval('P3D'))->format($fmt),
        ]);
        DB::table('crm_activities')->insert([
            'id' => Ulid::generate($now)->toString(),
            'activity_type' => 'task',
            'subject' => 'ارسال پیش‌نویس قرارداد نگین',
            'body' => null,
            'contact_id' => $contacts['demo-2'],
            'lead_id' => null,
            'opportunity_id' => null,
            'ticket_id' => null,
            'user_id' => $adminId,
            'due_at' => $now->add(new DateInterval('P2D'))->format($fmt),
            'completed_at' => null,
            'created_at' => $now->format($fmt),
        ]);
    }

    // ── تیکت‌ها (open / pending / resolved + یکی SLA-گذشته) ────────────────
    if (!DB::table('crm_tickets')->where('subject', 'مشکل ورود به پنل')->exists()) {
        DB::table('crm_tickets')->insert([
            'id' => Ulid::generate($now)->toString(),
            'ticket_number' => 'TKT-DEMO-00001',
            'contact_id' => $contacts['demo-4'],
            'subject' => 'مشکل ورود به پنل',
            'body' => 'رمز عبور را بازیابی کردم ولی هنوز وارد نمی‌شوم.',
            'category' => 'auth',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $supportId,
            // SLA گذشته → در اجرای بعدی crm:check-sla اسکلیت می‌شود (دمو)
            'sla_due_at' => $now->sub(new DateInterval('PT2H'))->format($fmt),
            'escalated_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'created_at' => $now->sub(new DateInterval('PT10H'))->format($fmt),
            'updated_at' => null,
        ]);
        DB::table('crm_tickets')->insert([
            'id' => Ulid::generate($now)->toString(),
            'ticket_number' => 'TKT-DEMO-00002',
            'contact_id' => $contacts['demo-0'],
            'subject' => 'سؤال دربارهٔ فاکتور فصلی',
            'body' => 'فاکتور فصل دوم را برای حسابداری می‌خواستم.',
            'category' => 'billing',
            'priority' => 'normal',
            'status' => 'pending',
            'assigned_to' => $supportId,
            'sla_due_at' => $now->add(new DateInterval('PT4H'))->format($fmt),
            'escalated_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'created_at' => $now->sub(new DateInterval('PT5H'))->format($fmt),
            'updated_at' => null,
        ]);
        DB::table('crm_tickets')->insert([
            'id' => Ulid::generate($now)->toString(),
            'ticket_number' => 'TKT-DEMO-00003',
            'contact_id' => $contacts['demo-1'],
            'subject' => 'درخواست آموزش گزارش‌ها',
            'body' => 'امکان یک جلسهٔ آموزش کوتاه برای تیم ما هست؟',
            'category' => 'training',
            'priority' => 'low',
            'status' => 'resolved',
            'assigned_to' => $supportId,
            'sla_due_at' => $now->add(new DateInterval('P1D'))->format($fmt),
            'escalated_at' => null,
            'resolved_at' => $now->sub(new DateInterval('PT1H'))->format($fmt),
            'closed_at' => null,
            'created_at' => $now->sub(new DateInterval('P2D'))->format($fmt),
            'updated_at' => null,
        ]);
    }

    // ── کمپین پیش‌نویس ────────────────────────────────────────────────────
    if (!DB::table('crm_campaigns')->where('name', 'خوش‌آمدگویی بهاره')->exists()) {
        DB::table('crm_campaigns')->insert([
            'id' => Ulid::generate($now)->toString(),
            'name' => 'خوش‌آمدگویی بهاره',
            'type' => 'email',
            'status' => 'draft',
            'audience_query' => (string) json_encode(['tags' => ['vip'], 'status' => 'active'], JSON_UNESCAPED_UNICODE),
            'template' => "{first_name} گرامی،\n\nتخفیف ویژهٔ بهاری برای مشتریان VIP فعال شد.\n\n— تیم فروش",
            'sent_count' => 0,
            'failed_count' => 0,
            'scheduled_at' => null,
            'sent_at' => null,
            'created_at' => $now->format($fmt),
        ]);
    }
};
