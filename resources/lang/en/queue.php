<?php

declare(strict_types=1);

return array (
  'unknown_driver' => 'Unknown queue driver [:driver]',
  'connection_not_configured' => 'Queue connection [:connection] is not configured',
  'connection_failed' => 'Queue connection failed',
  'redis_connect_failed' => 'Could not connect to Redis (:host::port): :error',
  'redis_write_failed' => 'Failed writing to the Redis connection (:command)',
  'redis_read_failed' => 'Failed reading the Redis reply',
  'redis_error' => 'Redis error: :error',
  'redis_closed' => 'The Redis connection is closed',
  'file_write_failed' => 'Failed writing the queue file in :dir',
  'unsafe_job' => 'Job class is not in the queue allowlist: :class',
  'invalid_payload' => 'Invalid queue payload',
  'unserializable' => 'Value is not serializable for the queue: :reason',
  'max_attempts_exceeded' => 'Job :job has exceeded its maximum attempts (:tries)',
  'job_timed_out' => 'Job :job timed out (:timeout seconds)',
  'database_required' => 'Queue connection :connection requires the database engine',
  'no_container' => 'Queue operation requires a container',
  'empty_batch' => 'Cannot dispatch an empty batch',
  'empty_chain' => 'Cannot dispatch an empty chain',
);
