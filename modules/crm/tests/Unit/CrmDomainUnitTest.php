<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Unit;

use Modules\Crm\Domain\Entities\Campaign;
use Modules\Crm\Domain\Entities\Contact;
use Modules\Crm\Domain\Entities\Lead;
use Modules\Crm\Domain\Entities\Opportunity;
use Modules\Crm\Domain\Entities\Ticket;
use Modules\Crm\Domain\Entities\TicketReply;
use Modules\Crm\Domain\Enums\CampaignStatus;
use Modules\Crm\Domain\Enums\LeadStatus;
use Modules\Crm\Domain\Enums\OpportunityStage;
use Modules\Crm\Domain\Enums\TicketPriority;
use Modules\Crm\Domain\Enums\TicketStatus;
use Modules\Crm\Domain\Events\LeadWasConverted;
use Modules\Crm\Domain\Events\OpportunityWasWon;
use Modules\Crm\Domain\Events\TicketSlaBreached;
use Modules\Crm\Domain\Services\AudienceResolver;
use Modules\Crm\Domain\Services\ForecastCalculator;
use Modules\Crm\Domain\Services\LeadScorer;
use Modules\Crm\Domain\Services\SlaPolicy;
use Modules\Crm\Domain\Services\TicketNumberGenerator;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست واحد دامنهٔ CRM — موجودیت‌ها/سرویس‌های خالص (DOC-096).
 */
final class CrmDomainUnitTest extends TestCase
{
    // ─── LeadScorer — الگوریتم دقیق DOC-096 §3.2 ──────────────────────────

    public function testLeadScorerFollowsDocumentedAlgorithm(): void
    {
        $scorer = new LeadScorer();   // وزن‌های پیش‌فرض = مستند

        // referral(20) + company(15) + value>100M(25) + engagement(0) = 60
        self::assertSame(60, $scorer->score('referral', true, 150_000_000, 0));

        // website(5) + بدون شرکت(0) + value≤100M(10) = 15
        self::assertSame(15, $scorer->score('website', false, 50_000_000, 0));

        // engagement: ۲ فعالیت → 10 (هرکدام ۵)
        self::assertSame(25, $scorer->score('website', false, 50_000_000, 2));

        // engagement سقف ۱۵ — ۱۰ فعالیت هم ۱۵ است
        self::assertSame(30, $scorer->score('website', false, 50_000_000, 10));

        // سقف ۱۰۰
        self::assertSame(75, $scorer->score('referral', true, 200_000_000, 10));   // 20+15+25+15

        // آستانهٔ دقیق — value == threshold → low (10) چون شرط «>» است
        self::assertSame(15, $scorer->score('website', false, 100_000_000, 0));
        self::assertSame(30, $scorer->score('website', false, 100_000_001, 0));   // 5+25

        // tier
        self::assertSame('cold', $scorer->tier(15));
        self::assertSame('warm', $scorer->tier(60));
        self::assertSame('hot', $scorer->tier(75));
    }

    public function testLeadScorerWithCustomWeights(): void
    {
        $scorer = new LeadScorer([
            'source_referral' => 30,
            'source_default' => 0,
            'has_company' => 10,
            'high_value_threshold' => 1_000,
            'high_value' => 50,
            'low_value' => 5,
            'engagement_per_activity' => 1,
            'engagement_max' => 3,
            'cap' => 90,
        ]);

        self::assertSame(90, $scorer->score('referral', true, 2_000, 100));   // 30+10+50+3 → cap 90
        self::assertSame(5, $scorer->score('import', false, 500, 0));          // 0+0+5
    }

    // ─── Lead — چرخهٔ وضعیت ────────────────────────────────────────────────

    public function testLeadLifecycle(): void
    {
        $lead = Lead::create('contact-1', 'سرنخ تست', 250_000_000, ownerId: 7, source: 'referral');

        self::assertSame(LeadStatus::New, $lead->status());
        self::assertSame(26, \strlen($lead->id()));
        self::assertCount(1, $lead->pullEvents());   // LeadWasCreated

        // new → converted مستقیم نامجاز (باید qualify شود)
        try {
            $lead->convertTo('opp-1');
            self::fail('convert بدون qualify باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $lead->qualify();
        self::assertSame(LeadStatus::Qualified, $lead->status());

        $lead->convertTo('opp-1');
        self::assertSame(LeadStatus::Converted, $lead->status());
        self::assertSame('opp-1', $lead->convertedTo());

        $events = $lead->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LeadWasConverted::class, $events[0]);
        self::assertSame('crm.lead.converted', $events[0]->eventName());

        // converted پایانی است
        try {
            $lead->markLost('دیر رسیدیم');
            self::fail('markLost از converted باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }
    }

    public function testLeadScoreClamped(): void
    {
        $lead = Lead::create('c', 'سرنخ');
        $lead->applyScore(150);
        self::assertSame(100, $lead->score());
        $lead->applyScore(-5);
        self::assertSame(0, $lead->score());
    }

    // ─── Opportunity — pipeline §3.1 ──────────────────────────────────────

    public function testOpportunityStageMatrixAndForecast(): void
    {
        $opp = Opportunity::create('contact-1', 'قرارداد بزرگ', 400_000_000, initialProbability: 10);

        self::assertSame(OpportunityStage::Prospecting, $opp->stage());
        self::assertSame(40_000_000, $opp->weightedValue());   // 400M × 10٪

        // prospecting → proposal (پرش رو به جلو مجاز)
        $opp->moveTo(OpportunityStage::Proposal, 50);
        self::assertSame(200_000_000, $opp->weightedValue());
        $events = $opp->pullEvents();
        self::assertCount(1, $events);   // stage_changed

        // دو گام عقب → نامجاز (فقط یک گام)
        try {
            $opp->moveTo(OpportunityStage::Prospecting, 10);
            self::fail('عقب‌گرد دو مرحله‌ای باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // یک گام عقب → مجاز
        $opp->moveTo(OpportunityStage::Qualification, 25);
        self::assertSame(OpportunityStage::Qualification, $opp->stage());
        $opp->pullEvents();

        // lost بدون دلیل → رد
        try {
            $opp->moveTo(OpportunityStage::ClosedLost, 0, '');
            self::fail('lost بدون دلیل باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // won از qualification
        $opp->moveTo(OpportunityStage::ClosedWon, 100);
        self::assertTrue($opp->stage()->isWon());
        self::assertNotNull($opp->closedAt());

        $events = $opp->pullEvents();
        self::assertCount(2, $events);   // stage_changed + won
        self::assertInstanceOf(OpportunityWasWon::class, $events[1]);
        self::assertSame('crm.opportunity.won', $events[1]->eventName());

        // بسته → هیچ انتقالی
        self::assertFalse($opp->stage()->canMoveTo(OpportunityStage::Prospecting));
    }

    public function testForecastCalculator(): void
    {
        $calc = new ForecastCalculator();

        // weighted = Σ floor(value × p٪) — int، بدون float
        $total = $calc->weightedTotal([
            ['value' => 100_000_000, 'probability' => 25],   // 25M
            ['value' => 50_000_000, 'probability' => 50],    // 25M
            ['value' => 10_000_001, 'probability' => 75],    // 7,500,000.75 → floor 7,500,000
        ]);
        self::assertSame(57_500_000, $total);

        $byStage = $calc->byStage([
            ['stage' => 'proposal', 'value' => 100, 'probability' => 50],
            ['stage' => 'proposal', 'value' => 200, 'probability' => 50],
            ['stage' => 'negotiation', 'value' => 400, 'probability' => 75],
        ]);
        self::assertSame(2, $byStage['proposal']['count']);
        self::assertSame(300, $byStage['proposal']['value']);
        self::assertSame(150, $byStage['proposal']['weighted']);
        self::assertSame(300, $byStage['negotiation']['weighted']);

        $wl = $calc->winLoss(3, 1, 5, 900);
        self::assertSame(75.0, $wl['win_rate']);
        self::assertSame(0.0, $calc->winLoss(0, 0)['win_rate']);
    }

    // ─── Ticket — SLA §3.3 + AC-CRM-08 ────────────────────────────────────

    public function testTicketStateMachine(): void
    {
        $ticket = Ticket::create('TKT-TEST-1', 'مشکل ورود', 'رمز را ریست کردم ولی…', priority: TicketPriority::High);

        self::assertSame(TicketStatus::Open, $ticket->status());
        self::assertCount(1, $ticket->pullEvents());   // TicketWasCreated

        // پاسخ agent → pending
        $ticket->markAwaitingCustomer();
        self::assertSame(TicketStatus::Pending, $ticket->status());
        self::assertFalse($ticket->status()->slaRunning());   // توقف SLA

        // پاسخ مشتری → open
        $ticket->reopenByCustomer();
        self::assertSame(TicketStatus::Open, $ticket->status());

        // resolve → closed مستقیم از open هم مجاز است؛ اول resolve
        $ticket->resolve(5);
        self::assertSame(TicketStatus::Resolved, $ticket->status());
        self::assertNotNull($ticket->resolvedAt());
        $events = $ticket->pullEvents();
        self::assertSame('crm.ticket.resolved', $events[0]->eventName());

        // ردِ حل → reopen
        $ticket->reopen();
        self::assertSame(TicketStatus::Open, $ticket->status());
        self::assertNull($ticket->resolvedAt());

        // resolve + close
        $ticket->resolve(5);
        $ticket->pullEvents();
        $ticket->close();
        self::assertSame(TicketStatus::Closed, $ticket->status());

        // closed پایانی — assign هم رد می‌شود
        try {
            $ticket->assignTo(9);
            self::fail('assign روی تیکت بسته باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }
    }

    public function testTicketSlaBreachEscalation(): void
    {
        $now = new \DateTimeImmutable('2026-10-25 12:00:00', new \DateTimeZone('UTC'));
        $slaDue = $now->sub(new \DateInterval('PT2H'));   // ۲ ساعت گذشته

        $ticket = Ticket::create('TKT-SLA-1', 'تیکت دیرکرده', 'متن', priority: TicketPriority::Urgent, slaDueAt: $slaDue, at: $now->sub(new \DateInterval('PT6H')));
        $ticket->pullEvents();

        self::assertTrue($ticket->isSlaBreached($now));
        self::assertSame(-120, $ticket->slaMinutesLeft($now));

        // escalate → رویداد + idempotent
        self::assertTrue($ticket->escalateForSla($now));
        $events = $ticket->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TicketSlaBreached::class, $events[0]);
        self::assertSame('crm.ticket.sla_breached', $events[0]->eventName());
        self::assertSame(120, $events[0]->toPayload()['overdue_minutes']);

        self::assertFalse($ticket->escalateForSla($now));   // دوباره escalate نمی‌شود
        self::assertSame([], $ticket->pullEvents());

        // pending → ساعت متوقف (breach جدید حساب نمی‌شود)
        $ticket2 = Ticket::create('TKT-SLA-2', 'تیکت', 'متن', slaDueAt: $slaDue, at: $now->sub(new \DateInterval('PT6H')));
        $ticket2->pullEvents();
        $ticket2->markAwaitingCustomer();
        self::assertFalse($ticket2->isSlaBreached($now));
        self::assertFalse($ticket2->escalateForSla($now));
    }

    public function testSlaPolicyDueTimes(): void
    {
        $policy = new SlaPolicy();   // پیش‌فرض مستند: urgent 2h / high 4h / normal 8h / low 24h

        $base = new \DateTimeImmutable('2026-10-25 10:00:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-10-25 12:00:00', $policy->dueAt(TicketPriority::Urgent, $base)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-25 14:00:00', $policy->dueAt(TicketPriority::High, $base)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-25 18:00:00', $policy->dueAt(TicketPriority::Normal, $base)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-26 10:00:00', $policy->dueAt(TicketPriority::Low, $base)->format('Y-m-d H:i:s'));

        // config override
        $custom = new SlaPolicy(['urgent' => 1, 'high' => 3, 'normal' => 6, 'low' => 12]);
        self::assertSame(1, $custom->hoursFor(TicketPriority::Urgent));
        self::assertSame('2026-10-25 11:00:00', $custom->dueAt(TicketPriority::Urgent, $base)->format('Y-m-d H:i:s'));
    }

    public function testTicketNumberGenerator(): void
    {
        Clock::setTestNow(new \DateTimeImmutable('2026-10-25 08:30:00', new \DateTimeZone('UTC')));

        try {
            $generator = new TicketNumberGenerator('TKT');
            $seen = [];
            for ($i = 0; $i < 30; $i++) {
                $number = $generator->generate();
                self::assertMatchesRegex('/^TKT-20261025-[0-9A-F]{5}$/', $number);
                $seen[$number] = true;
            }
            self::assertCount(30, $seen);
        } finally {
            Clock::setTestNow(null);
        }
    }

    public function testTicketReplyGuards(): void
    {
        // internal فقط از agent
        try {
            TicketReply::create('t1', 'یادداشت', 'customer', isInternal: true);
            self::fail('internal از مشتری باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // author_type نامعتبر
        try {
            TicketReply::create('t1', 'متن', 'robot');
            self::fail('نوع نویسندهٔ نامعتبر باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $reply = TicketReply::create('t1', 'پاسخ agent', 'agent', authorId: 3);
        self::assertTrue($reply->isAgent());
        self::assertFalse($reply->isInternal());

        // round-trip
        $revived = TicketReply::fromRow($reply->toRow() + ['id' => $reply->id(), 'ticket_id' => 't1']);
        self::assertSame('پاسخ agent', $revived->body());
        self::assertSame(3, $revived->authorId());
    }

    // ─── Contact ───────────────────────────────────────────────────────────

    public function testContactInvariants(): void
    {
        try {
            Contact::create('', '');
            self::fail('مخاطب بدون نام و ایمیل باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        $contact = Contact::create('سارا', 'احمدی', email: 'Sara@Example.test', tags: ['vip', 'vip', ' tehran ', '']);
        self::assertSame('sara@example.test', $contact->email());   // lowercase
        self::assertSame(['vip', 'tehran'], $contact->tags());      // dedupe + trim
        self::assertSame('سارا احمدی', $contact->fullName());
        self::assertTrue($contact->hasCompany() === false);

        $contact->revise(firstName: 'سارا', lastName: 'احمدی', company: 'پارس');
        self::assertTrue($contact->hasCompany());

        // soft delete
        $contact->softDelete();
        self::assertTrue($contact->isDeleted());
    }

    // ─── Campaign ──────────────────────────────────────────────────────────

    public function testCampaignFlow(): void
    {
        $campaign = Campaign::create('کمپین نوروز', "سلام {first_name} — {company}", 'email', ['tags' => ['vip']]);
        self::assertSame(CampaignStatus::Draft, $campaign->status());

        // personalize
        self::assertSame('سلام سارا — فناوری پارس', $campaign->renderFor('سارا', 'احمدی', 'فناوری پارس'));

        // scheduled با زمان → status=scheduled
        $scheduled = Campaign::create('c2', 'متن', 'sms', [], new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));
        self::assertSame(CampaignStatus::Scheduled, $scheduled->status());
        self::assertFalse($scheduled->isDueForSending(new \DateTimeImmutable('2026-10-30 00:00:00', new \DateTimeZone('UTC'))));
        self::assertTrue($scheduled->isDueForSending(new \DateTimeImmutable('2026-11-01 10:00:01', new \DateTimeZone('UTC'))));

        // draft → sending → sent + رویداد
        $campaign->markSending();
        $campaign->recordSent(3);
        $campaign->recordFailed(1);
        $campaign->markSent();
        self::assertSame(CampaignStatus::Sent, $campaign->status());
        self::assertSame(3, $campaign->sentCount());

        $events = $campaign->pullEvents();
        self::assertSame('crm.campaign.sent', $events[0]->eventName());
        self::assertSame(3, $events[0]->toPayload()['sent_count']);

        // sent → ویرایش/انتقال نامجاز
        try {
            $campaign->revise('نام جدید', 'متن');
            self::fail('ویرایش کمپین ارسال‌شده باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }

        // نوع نامعتبر
        try {
            Campaign::create('c3', 'متن', 'whatsapp');
            self::fail('نوع نامعتبر باید رد شود');
        } catch (\DomainException) {
            // انتظار
        }
    }

    // ─── AudienceResolver ──────────────────────────────────────────────────

    public function testAudienceResolver(): void
    {
        $resolver = new AudienceResolver();

        $filters = $resolver->resolve(['tags' => ['vip', ' tehran '], 'source' => 'shop'], 'email');
        self::assertSame(['vip', 'tehran'], $filters['tags_all']);
        self::assertSame('shop', $filters['source']);
        self::assertSame('active', $filters['status']);
        self::assertTrue($filters['has_email']);
        self::assertArrayNotHasKey('has_mobile', $filters);

        // sms → has_mobile
        $sms = $resolver->resolve([], 'sms');
        self::assertTrue($sms['has_mobile']);
        self::assertArrayNotHasKey('has_email', $sms);

        // owner + status
        $custom = $resolver->resolve(['status' => 'inactive', 'owner_id' => 12], 'email');
        self::assertSame('inactive', $custom['status']);
        self::assertSame(12, $custom['owner_id']);
    }

    // ─── OpportunityStage — ماتریس ─────────────────────────────────────────

    public function testStageMatrixDetails(): void
    {
        // رو به جلو چند پرش
        self::assertTrue(OpportunityStage::Prospecting->canMoveTo(OpportunityStage::Negotiation));
        // یک گام عقب
        self::assertTrue(OpportunityStage::Negotiation->canMoveTo(OpportunityStage::Proposal));
        // دو گام عقب → نامجاز
        self::assertFalse(OpportunityStage::Negotiation->canMoveTo(OpportunityStage::Prospecting));
        // بستن از هر باز
        self::assertTrue(OpportunityStage::Prospecting->canMoveTo(OpportunityStage::ClosedLost));
        self::assertTrue(OpportunityStage::Proposal->canMoveTo(OpportunityStage::ClosedWon));
        // بسته → هیچ
        self::assertFalse(OpportunityStage::ClosedWon->canMoveTo(OpportunityStage::Negotiation));
        self::assertFalse(OpportunityStage::ClosedLost->canMoveTo(OpportunityStage::Prospecting));
        // احتمالات پیش‌فرض
        self::assertSame([10, 25, 50, 75, 100, 0], \array_map(
            static fn (OpportunityStage $s): int => $s->defaultProbability(),
            OpportunityStage::cases(),
        ));
    }
}
