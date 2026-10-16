<?php

declare(strict_types=1);

return array (
  'ssrf' =>
  array (
    'blocked' => 'Outbound URL blocked',
    'scheme' => 'Scheme :scheme is not allowed for outbound requests',
    'no_host' => 'URL without host is invalid',
    'invalid_url' => 'Invalid URL',
    'private_ip' => 'URL points to a private range (:ip)',
    'dns_failed' => 'Hostname could not be resolved',
    'userinfo' => 'URL contains suspicious userinfo',
  ),
  'rate_limited' => 'Too many requests',
);
