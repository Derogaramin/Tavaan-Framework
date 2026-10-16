<?php

declare(strict_types=1);

return array (
  'disk_full' => 'Disk is full',
  'path_traversal' => 'Path traversal attempt blocked',
  'file_not_found' => 'File not found: :path',
  'disk_not_configured' => 'Filesystem disk [:disk] is not configured',
  'unknown_driver' => 'Unknown filesystem driver [:driver]',
  'operation_failed' => 'Filesystem operation :op failed on :path (:reason)',
  'signing_unavailable' => 'Temporary URLs require the crypt engine (APP_KEY)',
  's3_error' => 'S3 error during :op — status :status',
  'transport_error' => 'HTTP/FTP transport error: :reason',
  'not_supported' => 'Operation :op is not supported by driver :driver',
);
