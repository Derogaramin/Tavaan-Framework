<?php

declare(strict_types=1);

return array (
  'error' => 'Request processing error',
  'bad_request' => 'Bad request',
  'unauthorized' => 'Authentication required',
  'forbidden' => 'Access denied',
  'method_not_allowed' => 'Method not allowed',
  'unprocessable_entity' => 'Unprocessable entity',
  'too_many_requests' => 'Too many requests; please slow down',
  'server_error' => 'Internal server error',
  'csrf_mismatch' => 'Security token expired; please refresh the page',
  'route' => 
  array (
    'not_found' => 'The requested page was not found',
  ),
  'json' => 
  array (
    'invalid' => 'Invalid JSON body',
    'too_large' => 'JSON payload too large',
  ),
  'header' => 
  array (
    'injection' => 'Invalid header',
  ),
  'request' => 
  array (
    'smuggling' => 'Malformed request',
  ),
  'payload' => 
  array (
    'too_large' => 'Request payload too large',
  ),
);
