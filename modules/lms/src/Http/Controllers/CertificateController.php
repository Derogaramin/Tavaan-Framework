<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateController — راستی‌آزمایی عمومی گواهی (AC-LMS-08).
 */

namespace Modules\Lms\Http\Controllers;

use Modules\Lms\Application\UseCases\CertificateService;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\View;

/**
 * مسیرها:
 * - GET /lms/certificates/{serial}            → صفحهٔ راستی‌آزمایی عمومی
 * - GET /lms/certificates/{serial}/document   → سند آمادهٔ چاپ (print-to-PDF)
 *
 * 🔒 serial یکتا + رقم کنترل = «capability URL» — دسترسی عمومی بدون لاگین
 * (DOC-095 §3.4: serial یکتا + verify_url).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CertificateController
{
    public function __construct(
        private readonly CertificateService $service,
        private readonly CertificateRepositoryInterface $certificates,
    ) {
    }

    /** راستی‌آزمایی عمومی — /lms/certificates/{serial}. */
    public function verify(RequestInterface $request, string $serial): mixed
    {
        $result = $this->service->verify($serial);

        if ($result['certificate'] === null) {
            // serial نامعتبر/ناشناخته — همان صفحهٔ verify با وضعیت «یافت نشد» (بدون 404 تا ربات‌ها کاوش نکنند)
            return View::render('lms::front.certificate-verify', [
                'result' => $result,
                'serial' => $serial,
            ]);
        }

        return View::render('lms::front.certificate-verify', [
            'result' => $result,
            'serial' => $serial,
        ]);
    }

    /** فرم راستی‌آزمایی — /lms/verify-certificate (عمومی). */
    public function verifyForm(RequestInterface $request): mixed
    {
        return View::render('lms::front.certificate-verify', [
            'result' => null,
            'serial' => (string) $request->input('serial', ''),
        ]);
    }

    /** سند گواهی (HTML آمادهٔ چاپ) — /lms/certificates/{serial}/document. */
    public function document(RequestInterface $request, string $serial): mixed
    {
        $certificate = $this->certificates->findBySerial($serial);
        if ($certificate === null || !$certificate->isValid()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.certificate_not_found', context: ['serial' => $serial]);
        }

        return $this->service->documentHtml($certificate);
    }
}
