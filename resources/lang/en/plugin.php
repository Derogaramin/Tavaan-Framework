<?php

declare(strict_types=1);

return array (
  'manifest_invalid' => 'Invalid plugin manifest: :reason',
  'signature_missing' => 'Plugin ":plugin" has no signature — rejected under require_signature',
  'signature_invalid' => 'Invalid signature for plugin ":plugin"',
  'vendor_untrusted' => 'Vendor ":author" is not in trusted_vendors',
  'lock_tampered' => 'Integrity violation for plugin ":plugin": :details',
  'lock_missing' => 'Plugin ":plugin" is not registered in plugins.lock',
  'not_found' => 'Plugin ":plugin" not found',
  'already_installed' => 'Plugin ":plugin" is already installed',
  'capability_denied' => 'Capability ":capability" denied for plugin ":plugin" — :reason',
  'hook_not_declared' => 'Hook ":hook" is not declared by the host — plugin ":plugin" cannot listen',
  'dangerous_code' => 'Dangerous code found in plugin ":plugin" — explicit admin approval required',
  'disabled' => 'Plugin system is disabled (TVN_PLUGINS=false)',
);
