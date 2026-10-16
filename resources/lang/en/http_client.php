<?php

declare(strict_types=1);

return array (
  'connection_failed' => 'HTTP connection to :url failed: :reason',
  'too_many_redirects' => 'Too many redirects for :url',
  'response_too_large' => 'Response from :url exceeds the maximum size (:max bytes)',
  'request_failed' => 'HTTP request to :url failed with status :status',
  'circuit_open' => 'Circuit for service :service is open (cooldown: :cooldown seconds)',
  'invalid_url' => 'Invalid URL: :url',
  'ssrf_blocked' => 'Request to :url blocked by the SSRF guard (:reason)',
  'dns_failed' => 'DNS resolution failed for host :host',
  'assertion_failed' => 'HTTP client assertion failed (:assert)',
);
