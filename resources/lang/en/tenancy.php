<?php

declare(strict_types=1);

return array (
  'not_found' => 'Tenant not found',
  'suspended' => 'Tenant is inactive or suspended',
  'quota_exceeded' => 'Quota for ":resource" exhausted (limit :limit, used :used)',
  'context_missing' => 'Tenant context is not set — tenant-aware operation requires TenantContext',
  'invalid_identifier' => 'Invalid tenant identifier ":identifier" (letters/digits/._- only, max 64 chars)',
  'duplicate_identifier' => 'Tenant with identifier ":identifier" already exists',
  'create_failed' => 'Failed to create tenant ":identifier"',
  'database_required' => 'Tenancy operation requires a database connection',
  'template_missing' => 'Tenant connection template ":template" is not configured in config/database.php',
  'provision_failed' => 'Tenant data-space provisioning failed: :reason',
  'unsafe_identifier' => 'Unsafe identifier rejected for database operation: :identifier',
  'schema_requires_pgsql' => 'Schema strategy requires PostgreSQL (current driver: :driver)',
  'console_required' => 'Running migrations requires the console engine',
  'cross_tenant_forbidden' => 'Cross-tenant operation forbidden without tenancy.cross_tenant permission',
);
