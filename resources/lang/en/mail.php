<?php

declare(strict_types=1);

return array (
  'header_injection' => 'Mail header injection attempt in field :field',
  'invalid_address' => 'Invalid email address: :address',
  'no_recipients' => 'Mail message has no recipients',
  'attachment_unreadable' => 'Attachment is not readable: :path',
  'smtp_connect_failed' => 'SMTP connection to :host::port failed (:error)',
  'smtp_bad_response' => 'Unexpected SMTP response at :command: :response',
  'smtp_tls_failed' => 'STARTTLS upgrade failed',
  'transport_failed' => 'Mail transport (:transport) failed: :reason',
  'unknown_transport' => 'Unknown mail transport: :transport',
  'view_required' => 'Mailable must have a view, text, or html content',
  'ses_failed' => 'AWS SES responded with status :status',
  'assertion_failed' => 'Mail assertion failed (:expected)',
);
