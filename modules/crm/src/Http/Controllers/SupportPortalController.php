<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * SupportPortalController — پرتال عمومی تیکت (DOC-096 §3.3/§7: فرم وب‌سایت).
 */

namespace Modules\Crm\Http\Controllers;

use Modules\Crm\Application\UseCases\TicketService;
use Modules\Crm\Domain\Repositories\TicketRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\Validator;
use Tavaan\Core\Facade\Facades\View;

/**
 * مسیرها:
 * - GET  /crm/support                → فرم ثبت تیکت
 * - POST /crm/support                → ساخت تیکت (مهمان/کاربر)
 * - GET  /crm/support/{number}       → مشاهدهٔ وضعیت + پاسخ‌ها
 * - POST /crm/support/{number}/replies → پاسخ مشتری
 * - GET  /crm/support/track          → فرم پیگیری با شماره
 *
 * 🔒 ضداسپم: ThrottleMiddleware روی نوشتن‌ها + sanitize پروفایل plain.
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class SupportPortalController
{
    public function __construct(
        private readonly TicketService $service,
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    /** فرم ثبت تیکت. */
    public function create(RequestInterface $request): mixed
    {
        if (!(bool) \config('crm.tickets.portal_enabled', true)) {
            throw new RouteNotFoundException(messageId: 'crm::messages.portal_disabled');
        }

        return View::render('crm::front.support-form', []);
    }

    /** ساخت تیکت از پرتال. */
    public function store(RequestInterface $request): mixed
    {
        if (!(bool) \config('crm.tickets.portal_enabled', true)) {
            throw new RouteNotFoundException(messageId: 'crm::messages.portal_disabled');
        }

        $validated = Validator::validate($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:5', 'max:20000'],
            'category' => ['nullable', 'string', 'max:50'],
        ], attributes: [
            'name' => 'نام',
            'email' => 'ایمیل',
            'subject' => 'عنوان',
            'body' => 'متن',
        ]);

        $contact = $this->service->portalContact((string) $validated['name'], (string) $validated['email']);

        try {
            $ticket = $this->service->create([
                'subject' => (string) $validated['subject'],
                'body' => (string) $validated['body'],
                'category' => $validated['category'] ?? null,
                'priority' => 'normal',
            ], contactId: $contact?->id(), fromPortal: true);
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'ticket_created');
        Session::flash('ticket_number', $ticket->ticketNumber());

        return redirect('/crm/support/' . $ticket->ticketNumber());
    }

    /** مشاهدهٔ تیکت با شماره (capability URL — شماره = رمز دسترسی). */
    public function show(RequestInterface $request, string $number): mixed
    {
        $ticket = $this->tickets->findByNumber($number);
        if ($ticket === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.ticket_not_found', context: ['number' => $number]);
        }

        return View::render('crm::front.support-ticket', [
            'ticket' => $ticket,
            'replies' => $this->service->replies($ticket, asAgent: false),   // internalها دیده نمی‌شوند
        ]);
    }

    /** پاسخ مشتری روی تیکت. */
    public function reply(RequestInterface $request, string $number): mixed
    {
        $ticket = $this->tickets->findByNumber($number);
        if ($ticket === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.ticket_not_found');
        }

        if ($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Closed) {
            Session::flash('error', 'تیکت بسته پاسخ نمی‌گیرد.');

            return back();
        }

        try {
            $this->service->customerReply($ticket, (string) $request->input('body', ''));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'reply_sent');

        return redirect('/crm/support/' . $ticket->ticketNumber());
    }

    /** فرم پیگیری با شمارهٔ تیکت. */
    public function track(RequestInterface $request): mixed
    {
        return View::render('crm::front.support-form', [
            'track' => true,
        ]);
    }
}
