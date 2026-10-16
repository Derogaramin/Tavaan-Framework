<?php

declare(strict_types=1);

/*
 * Translation keys for the Payment engine (DOC-073) — en
 */

return [
    'gateway_not_found' => 'Payment gateway ":gateway" is not recognized',
    'gateway_not_configured' => 'Gateway ":gateway" is not configured (:missing)',
    'gateway_error' => 'Invalid response from gateway ":gateway" during :operation',
    'gateway_connection_failed' => 'Connection to gateway ":gateway" failed during :operation',
    'not_found' => 'Payment transaction not found',
    'already_paid' => 'Transaction ":transaction" has already been paid successfully',
    'not_refundable' => 'Transaction ":transaction" with status :status is not refundable',
    'unsupported_operation' => 'Operation :operation is not supported by gateway ":gateway"',
    'invalid_amount' => 'Invalid amount: :reason',
    'order_id_required' => 'Order ID (orderId) is required to start a payment',
    'signature_mismatch' => 'IPN signature for gateway ":gateway" is invalid',
];
