<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول CRM — DOC-096 §2.
 *
 * 🔒 پول: int minor-unit (ADR-0013 — انطباق با DECIMAL(18,4) مستند در گزارش
 * M9 گام ۶ ثبت شده است). prefix ماژول = crm_ (DoD §3).
 * 🔒 national_id: رمزنگاری‌شده در rest (Crypt facade — DOC-096 §2 «encrypted cast»).
 *
 * افزودنی‌های مستند نسبت به DOC-096 §2:
 * - crm_campaign_recipients: گیرندگان کمپین (idempotency ارسال + ردیابی)
 * - tickets.escalated_at / closed_at: جریان SLA/escalation §3.3 و تأیید مشتری
 * - activities.lead_id: فعالیت روی سرنخ (engagement scoring §3.2)
 * - opportunities.workflow_instance_id: نمونهٔ Workflowpipeline (audit — AC-CRM-07)
 * - campaigns.failed_count + created_at
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                       // ULID
                $t->string('first_name', 100);
                $t->string('last_name', 100)->default('');
                $t->string('company', 190)->nullable();
                $t->string('title', 100)->nullable();                // سمت شغلی
                $t->string('email', 190)->nullable();
                $t->string('mobile', 20)->nullable();
                $t->string('phone', 20)->nullable();
                $t->string('national_id', 500)->nullable();          // 🔒 encrypted
                $t->string('source', 50)->default('website');        // website|referral|campaign|import|shop|support
                $t->unsignedBigInteger('owner_id')->nullable();      // کارشناس مسئول
                $t->unsignedBigInteger('user_id')->nullable();       // پیوند اختیاری به users
                $t->string('status', 20)->default('active');         // active|inactive|blocked
                $t->json('tags')->nullable();
                $t->json('custom_fields')->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();           // soft delete
                $t->index(['owner_id'], 'idx_crm_contact_owner');
                $t->index(['email'], 'idx_crm_contact_email');
                $t->index(['status', 'created_at'], 'idx_crm_contact_status');
            });
        }

        if (!Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('contact_id', 26);
                $t->string('title', 255);
                $t->unsignedBigInteger('value')->default(0);         // minor-unit (ADR-0013)
                $t->char('currency', 3)->default('IRR');
                $t->string('status', 20)->default('new');            // new|qualified|converted|lost
                $t->integer('score')->default(0);                    // lead scoring §3.2
                $t->unsignedBigInteger('owner_id')->nullable();
                $t->char('converted_to', 26)->nullable();            // → opportunity
                $t->string('lost_reason', 255)->nullable();
                $t->string('source', 50)->default('website');        // snapshot منبع (§6: lead source effectiveness)
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['status', 'score'], 'idx_crm_lead_status');
                $t->index(['source', 'status'], 'idx_crm_lead_source');
                $t->index(['owner_id'], 'idx_crm_lead_owner');
            });
        }

        if (!Schema::hasTable('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('contact_id', 26);
                $t->char('lead_id', 26)->nullable();
                $t->string('title', 255);
                $t->unsignedBigInteger('value')->default(0);         // minor-unit
                $t->char('currency', 3)->default('IRR');
                $t->string('stage', 30)->default('prospecting');     // prospecting|qualification|proposal|negotiation|closed_won|closed_lost
                $t->integer('probability')->default(0);
                $t->date('expected_close_date')->nullable();
                $t->unsignedBigInteger('owner_id')->nullable();
                $t->string('lost_reason', 255)->nullable();
                $t->string('workflow_instance_id', 64)->nullable();  // نمونهٔ crm-sales-pipeline
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('closed_at', 3)->nullable();
                $t->index(['stage'], 'idx_crm_opp_stage');
                $t->index(['owner_id'], 'idx_crm_opp_owner');
            });
        }

        if (!Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('activity_type', 20);                     // call|meeting|email|note|task
                $t->string('subject', 255);
                $t->text('body')->nullable();
                $t->char('contact_id', 26)->nullable();
                $t->char('lead_id', 26)->nullable();
                $t->char('opportunity_id', 26)->nullable();
                $t->char('ticket_id', 26)->nullable();
                $t->unsignedBigInteger('user_id');
                $t->dateTime('due_at', 3)->nullable();
                $t->dateTime('completed_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['contact_id'], 'idx_crm_act_contact');
                $t->index(['lead_id'], 'idx_crm_act_lead');
                $t->index(['opportunity_id'], 'idx_crm_act_opp');
                $t->index(['ticket_id'], 'idx_crm_act_ticket');
                $t->index(['user_id', 'due_at'], 'idx_crm_act_user_due');
            });
        }

        if (!Schema::hasTable('crm_tickets')) {
            Schema::create('crm_tickets', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('ticket_number', 32)->unique();
                $t->char('contact_id', 26)->nullable();
                $t->string('subject', 255);
                $t->text('body');
                $t->string('category', 50)->nullable();
                $t->string('priority', 20)->default('normal');       // low|normal|high|urgent
                $t->string('status', 20)->default('open');           // open|pending|resolved|closed
                $t->unsignedBigInteger('assigned_to')->nullable();
                $t->dateTime('sla_due_at', 3)->nullable();
                $t->dateTime('escalated_at', 3)->nullable();         // SLA breach → escalation
                $t->dateTime('resolved_at', 3)->nullable();
                $t->dateTime('closed_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['status', 'priority'], 'idx_crm_ticket_status');
                $t->index(['assigned_to'], 'idx_crm_ticket_assigned');
                $t->index(['sla_due_at'], 'idx_crm_ticket_sla');
            });
        }

        if (!Schema::hasTable('crm_ticket_replies')) {
            Schema::create('crm_ticket_replies', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('ticket_id', 26);
                $t->unsignedBigInteger('author_id')->nullable();     // null = مهمان (پرتال)
                $t->string('author_type', 20);                       // agent|customer
                $t->text('body');
                $t->json('attachments')->nullable();
                $t->boolean('is_internal')->default(false);          // یادداشت داخلی
                $t->dateTime('created_at', 3);
                $t->index(['ticket_id', 'created_at'], 'idx_crm_reply_ticket');
            });
        }

        if (!Schema::hasTable('crm_campaigns')) {
            Schema::create('crm_campaigns', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('name', 190);
                $t->string('type', 30)->default('email');            // email|sms
                $t->string('status', 20)->default('draft');          // draft|scheduled|sending|sent|failed
                $t->json('audience_query')->nullable();              // {tags:[],source:…,status:…}
                $t->text('template');
                $t->integer('sent_count')->default(0);
                $t->integer('failed_count')->default(0);
                $t->dateTime('scheduled_at', 3)->nullable();
                $t->dateTime('sent_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['status', 'scheduled_at'], 'idx_crm_campaign_status');
            });
        }

        if (!Schema::hasTable('crm_campaign_recipients')) {
            Schema::create('crm_campaign_recipients', function (Blueprint $t): void {
                $t->id();
                $t->char('campaign_id', 26);
                $t->char('contact_id', 26);
                $t->string('address', 190);
                $t->string('status', 20)->default('sent');           // sent|failed
                $t->dateTime('sent_at', 3);
                $t->unique(['campaign_id', 'contact_id'], 'uk_crm_campaign_recipient');   // idempotency
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_recipients');
        Schema::dropIfExists('crm_campaigns');
        Schema::dropIfExists('crm_ticket_replies');
        Schema::dropIfExists('crm_tickets');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_contacts');
    }
};
