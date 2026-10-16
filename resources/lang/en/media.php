<?php

declare(strict_types=1);

return array (
  'too_large' => 'File size (:size bytes) exceeds the maximum (:max bytes)',
  'extension_not_allowed' => 'File extension :extension is not in the media allowlist',
  'mime_mismatch' => 'Detected MIME (:detected) is not allowed (:allowed) — :name',
  'extension_mismatch' => 'Extension :extension does not match the real MIME :mime',
  'polyglot_detected' => 'File :name contains embedded executable code',
  'image_bomb' => 'Image dimensions (:width×:height) exceed the maximum :max',
  'unreadable' => 'File is not readable: :path',
  'store_failed' => 'Failed storing media: :path (:reason)',
  'not_found' => 'Media record not found: :id',
);
