<?php

declare(strict_types=1);

return array (
  'unknown_driver' => 'Unknown search driver: :driver',
  'invalid_index_name' => 'Invalid index name ":name" (letters/digits/_/- only, max 64 chars)',
  'driver_unavailable' => 'Search driver :driver is unavailable: :reason',
  'query_too_long' => 'Search query exceeds maximum allowed length (:max)',
  'connection_required' => 'Search driver requires a database connection: :driver',
  'persist_failed' => 'Failed to persist index at :path: :reason',
  'field_not_allowed' => 'Field ":field" is not allowed for index ":index"',
  'unknown_filter_op' => 'Unknown filter operator ":op"',
  'model_not_configured' => 'No model configured for index ":index" in config/search.php',
  'missing_doc_id' => 'Document id is missing for indexing (id or docId required)',
  'invalid_document' => 'Search document must be a Model or array',
);
