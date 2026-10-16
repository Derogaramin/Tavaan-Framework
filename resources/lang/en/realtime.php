<?php

declare(strict_types=1);

return array (
  'unknown_broker' => 'Unknown realtime broker ":broker"',
  'broker_unavailable' => 'Realtime broker ":broker" is unavailable: :reason',
  'channel_forbidden' => 'Access to channel ":channel" is forbidden',
  'sockets_required' => 'Failed to start the WebSocket server',
  'server_start_failed' => 'WebSocket server failed to start: :reason',
  'invalid_frame' => 'Invalid WebSocket frame: :reason',
  'auth_secret_missing' => 'Channel auth secret is missing (set realtime.auth_secret or app.key)',
);
