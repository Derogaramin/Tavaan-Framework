<?php

declare(strict_types=1);

return array (
  'credentials' =>
  array (
    'invalid' => 'Invalid username or password',
  ),
  'throttled' => 'Too many attempts; try again in :retry_after seconds',
  'account_banned' => 'This account has been banned',
  'failed' => 'Authentication failed',
  'unauthenticated' => 'You must log in to access this area',
  'token' =>
  array (
    'invalid' => 'Invalid token',
    'malformed' => 'Malformed token',
    'expired' => 'Token has expired',
    'revoked' => 'Token has been revoked',
    'algorithm_none' => 'Algorithm none is not accepted',
    'algorithm_mismatch' => 'Token algorithm does not match the pinned algorithm',
    'algorithm_unsupported' => 'Unsupported algorithm',
    'signature_invalid' => 'Invalid token signature',
    'issuer_mismatch' => 'Invalid token issuer',
    'audience_mismatch' => 'Invalid token audience',
    'not_yet_valid' => 'Token is not valid yet',
    'missing_jti' => 'Token is missing a unique id (jti)',
    'missing_subject' => 'Token is missing a subject',
    'subject_not_found' => 'Token subject not found',
    'reuse_detected' => 'Refresh token reuse detected; the whole family was revoked',
    'refresh_invalid' => 'Invalid refresh token',
    'refresh_expired' => 'Refresh token has expired',
    'key_missing' => 'Token signing key is not configured',
    'key_invalid' => 'Invalid signing key',
    'sign_failed' => 'Token signing failed',
  ),
  'otp' =>
  array (
    'invalid' => 'Invalid or expired one-time code',
    'sent' => 'One-time code sent',
  ),
  'two_factor' =>
  array (
    'invalid' => 'Invalid two-factor code',
  ),
  'reset' =>
  array (
    'sent' => 'Password reset link sent',
    'done' => 'Password has been reset',
    'invalid_token' => 'Invalid reset token',
    'expired' => 'Reset token has expired',
  ),
);
