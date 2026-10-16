<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Exceptions;

/**
 * تَوان | Tavaan — Payment
 * SignatureMismatchException — امضای IPN/Webhook نامعتبر (🔒 DOC-073 §7).
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class SignatureMismatchException extends PaymentException
{
    public static function for(string $gateway): self
    {
        return new self(
            messageId: 'payment.signature_mismatch',
            context: ['gateway' => $gateway],
            hint: '🔒 درخواست IPN با کلید HMAC درگاه تأیید نشد — احتمال جعل/دستکاری.',
        );
    }
}
