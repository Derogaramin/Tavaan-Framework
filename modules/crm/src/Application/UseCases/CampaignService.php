<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CampaignService — کمپین ایمیل/پیامک (DOC-096 §2/§8: E2E-04).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Entities\Campaign;
use Modules\Crm\Domain\Repositories\CampaignRepositoryInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Services\AudienceResolver;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Facade\Facades\Mail;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * CampaignService — ساخت/زمان‌بندی/ارسال کمپین.
 *
 * ارسال: audience_query → AudienceResolver → مخاطبان → برای هر گیرنده
 * `Mail::raw` ( personalize با {first_name}/{last_name}/{company} ) +
 * رکورد recipient (uk campaign+contact = **idempotent** در برابر اجرای
 * دوبارهٔ cron).
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class CampaignService
{
    /**
     * @param array<string, mixed> $config کلید config('crm.campaigns')
     */
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly ContactRepositoryInterface $contacts,
        private readonly AudienceResolver $audience,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): Campaign
    {
        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', 'in:email,sms'],
            'template' => ['required', 'string', 'max:50000'],
            'audience' => ['nullable'],
            'scheduled_at' => ['nullable', 'string', 'max:40'],
        ], attributes: [
            'name' => 'نام کمپین',
            'template' => 'قالب پیام',
        ]);

        $campaign = Campaign::create(
            name: (string) $validated['name'],
            template: (string) $validated['template'],
            type: (string) $validated['type'],
            audienceQuery: $this->parseAudience($validated['audience'] ?? null),
            scheduledAt: self::parseDate($validated['scheduled_at'] ?? null),
        );

        $this->campaigns->save($campaign);

        return $campaign;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(Campaign $campaign, array $input): Campaign
    {
        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'max:190'],
            'template' => ['required', 'string', 'max:50000'],
            'audience' => ['nullable'],
            'scheduled_at' => ['nullable', 'string', 'max:40'],
        ], attributes: ['name' => 'نام کمپین']);

        try {
            $campaign->revise(
                name: (string) $validated['name'],
                template: (string) $validated['template'],
                audienceQuery: $this->parseAudience($validated['audience'] ?? null),
                scheduledAt: self::parseDate($validated['scheduled_at'] ?? null),
            );
        } catch (\DomainException $e) {
            throw new ValidationException(errors: ['campaign' => [$e->getMessage()]], messageId: 'crm::messages.campaign_sent_immutable');
        }

        $this->campaigns->save($campaign);

        return $campaign;
    }

    /**
     * ارسال کمپین (دستی یا cron — `tvn crm:send-campaigns`).
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function send(Campaign $campaign): array
    {
        if ($campaign->status() === \Modules\Crm\Domain\Enums\CampaignStatus::Sent) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0];   // idempotent
        }

        $filters = $this->audience->resolve($campaign->audienceQuery(), $campaign->type());
        $recipients = $this->contacts->forCampaign($filters, 5000);

        $campaign->markSending();
        $this->campaigns->save($campaign);

        $sent = $failed = $skipped = 0;

        foreach ($recipients as $contact) {
            // idempotency — گیرندهٔ تکراری در اجرای مجدد cron
            if ($this->campaigns->recipientExists($campaign->id(), $contact->id())) {
                $skipped++;

                continue;
            }

            $address = $campaign->type() === 'sms' ? (string) ($contact->mobile() ?? '') : (string) ($contact->email() ?? '');
            if ($address === '') {
                $skipped++;

                continue;
            }

            $body = $campaign->renderFor($contact->firstName(), $contact->lastName(), (string) ($contact->company() ?? ''));

            $ok = $this->deliver($campaign, $address, $body);
            $this->campaigns->recordRecipient($campaign->id(), $contact->id(), $address, $ok);

            $ok ? $sent++ : $failed++;
        }

        if ($sent > 0) {
            $campaign->recordSent($sent);
        }
        if ($failed > 0) {
            $campaign->recordFailed($failed);
        }

        // هیچ ارسالی موفق نبود → failed؛ در غیر این صورت sent
        if ($sent === 0 && $failed > 0) {
            $campaign->markFailed();
        } else {
            $campaign->markSent();
        }
        $this->campaigns->save($campaign);

        foreach ($campaign->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /** کمپین‌های زمان‌بندی‌شدهٔ سررسیدرسیده (cron). @return int تعداد ارسال‌شده */
    public function sendDue(): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $count = 0;

        foreach ($this->campaigns->dueScheduled($now) as $campaign) {
            $result = $this->send($campaign);
            if ($result['sent'] > 0 || $result['failed'] > 0) {
                $count++;
            }
        }

        return $count;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function deliver(Campaign $campaign, string $address, string $body): bool
    {
        try {
            if ($campaign->type() === 'sms') {
                // 📱 درایور پیامک در این نگارش log-only است (بک‌لاگ: gateway SMS)
                Log::info('crm.campaign.sms', ['to' => $address, 'campaign' => $campaign->id()]);

                return true;
            }

            $fromName = (string) ($this->config['from_name'] ?? 'پشتیبانی');
            Mail::raw($body, static function (mixed $message) use ($address, $campaign, $fromName): void {
                if (\is_object($message) && \method_exists($message, 'to')) {
                    $message->to($address);
                }
                if (\is_object($message) && \method_exists($message, 'subject')) {
                    $message->subject($campaign->name());
                }
                if (\is_object($message) && \method_exists($message, 'from')) {
                    $message->from((string) \config('mail.from.address', 'noreply@tavaan.local'), $fromName);
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('crm.campaign.deliver_failed', [
                'campaign' => $campaign->id(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function parseAudience(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (\is_array($raw)) {
            return $raw;
        }

        $decoded = \json_decode((string) $raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private static function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || (string) $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
