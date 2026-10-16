<?php

declare(strict_types=1);

return array (
  'not_found' => 'Backup :id not found',
  'verify_failed' => 'Backup verification failed for :id: :reason',
  'decrypt_failed' => 'Backup decryption failed for :id: :reason',
  'key_missing' => 'Backup encryption key is not configured (TVN_BACKUP_KEY or APP_KEY required)',
  'force_required' => 'Restore is destructive — confirm with --force',
  'store_failed' => 'Failed storing the backup: :reason',
  'unsafe_path' => 'Unsafe path in backup: :path',
);
