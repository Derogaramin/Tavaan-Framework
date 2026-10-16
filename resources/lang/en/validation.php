<?php

declare(strict_types=1);

/*
 * Validation messages — English (DOC-051 §7).
 * Placeholders: :attribute, :min, :max, :size, :values, :other, :format
 */

return array (
  'failed' => 'The given data was invalid',

  // presence & type
  'required' => 'The :attribute field is required.',
  'required_if' => 'The :attribute field is required when :other is :values.',
  'required_unless' => 'The :attribute field is required unless :other is :values.',
  'required_with' => 'The :attribute field is required when :values is present.',
  'required_without' => 'The :attribute field is required when :values is not present.',
  'nullable' => 'The :attribute is invalid.',
  'present' => 'The :attribute field must be present.',
  'filled' => 'The :attribute field must have a value.',
  'sometimes' => 'The :attribute is invalid.',
  'string' => 'The :attribute must be a string.',
  'integer' => 'The :attribute must be an integer.',
  'numeric' => 'The :attribute must be a number.',
  'boolean' => 'The :attribute field must be true or false.',
  'array' => 'The :attribute must be an array.',
  'object' => 'The :attribute is invalid.',
  'date' => 'The :attribute must be a valid date.',
  'digits' => 'The :attribute must be :digits digits.',
  'digits_between' => 'The :attribute must be between :min and :max digits.',

  // size & range
  'min' => array (
    'numeric' => 'The :attribute must be at least :min.',
    'string' => 'The :attribute must be at least :min characters.',
    'array' => 'The :attribute must have at least :min items.',
    'file' => 'The :attribute must be at least :min kilobytes.',
  ),
  'max' => array (
    'numeric' => 'The :attribute may not be greater than :max.',
    'string' => 'The :attribute may not be greater than :max characters.',
    'array' => 'The :attribute may not have more than :max items.',
    'file' => 'The :attribute may not be greater than :max kilobytes.',
  ),
  'between' => array (
    'numeric' => 'The :attribute must be between :min and :max.',
    'string' => 'The :attribute must be between :min and :max characters.',
    'array' => 'The :attribute must have between :min and :max items.',
    'file' => 'The :attribute must be between :min and :max kilobytes.',
  ),
  'size' => array (
    'numeric' => 'The :attribute must be :size.',
    'string' => 'The :attribute must be :size characters.',
    'array' => 'The :attribute must contain :size items.',
    'file' => 'The :attribute must be :size kilobytes.',
  ),
  'length' => 'The :attribute must be exactly :size characters.',
  'gt' => 'The :attribute must be greater than :other.',
  'gte' => 'The :attribute must be greater than or equal to :other.',
  'lt' => 'The :attribute must be less than :other.',
  'lte' => 'The :attribute must be less than or equal to :other.',

  // format
  'email' => 'The :attribute must be a valid email address.',
  'url' => 'The :attribute must be a valid URL.',
  'uuid' => 'The :attribute must be a valid UUID.',
  'ulid' => 'The :attribute must be a valid ULID.',
  'ip' => 'The :attribute must be a valid IP address.',
  'ipv4' => 'The :attribute must be a valid IPv4 address.',
  'ipv6' => 'The :attribute must be a valid IPv6 address.',
  'mac_address' => 'The :attribute must be a valid MAC address.',
  'regex' => 'The :attribute format is invalid.',
  'not_regex' => 'The :attribute format is not allowed.',
  'alpha' => 'The :attribute may only contain letters.',
  'alpha_num' => 'The :attribute may only contain letters and numbers.',
  'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
  'slug' => 'The :attribute must be a valid slug.',
  'json' => 'The :attribute must be a valid JSON string.',
  'date_format' => 'The :attribute does not match the format :format.',
  'timezone' => 'The :attribute must be a valid timezone.',

  // comparison & sets
  'in' => 'The selected :attribute is invalid.',
  'not_in' => 'The selected :attribute is not allowed.',
  'confirmed' => 'The :attribute confirmation does not match.',
  'same' => 'The :attribute and :other must match.',
  'different' => 'The :attribute and :other must be different.',
  'accepted' => 'The :attribute must be accepted.',
  'declined' => 'The :attribute must be declined.',

  // file
  'file' => 'The :attribute must be a file.',
  'image' => 'The :attribute must be an image (jpg/png/gif/bmp/webp).',
  'mimes' => 'The :attribute must be a file of type: :values.',
  'mimetypes' => 'The :attribute must be a file of type: :values.',
  'dimensions' => 'The :attribute has invalid image dimensions.',
  'max_filesize' => 'The :attribute may not be greater than :max kilobytes.',
  'uploaded_file' => 'The :attribute failed to upload.',

  // database
  'unique' => 'The :attribute has already been taken.',
  'exists' => 'The selected :attribute does not exist.',
  'exists_multiple' => 'Some values of :attribute do not exist.',
  'unique_composite' => 'The :attribute combination is duplicate.',

  // Iran-specific 🇮🇷
  'iran_mobile' => 'The mobile number is not valid (e.g. 09121234567).',
  'iran_national_code' => 'The national code is not valid.',
  'iran_postal_code' => 'The postal code is not valid (10 digits).',
  'iran_sheba' => 'The IBAN (Sheba) is not valid (IR + 24 digits).',
  'iran_card_number' => 'The card number is not valid (16 digits).',
  'iran_company_id' => 'The company national ID is not valid.',
  'jalali_date' => 'The :attribute must be a valid Jalali date.',
  'jalali_date_format' => 'The :attribute must be a Jalali date in :format format.',
  'persian_digits' => 'The :attribute must use Persian digits (۰-۹).',

  // security
  'honeypot' => 'Validation failed.',
  'no_html' => 'The :attribute may not contain HTML.',
  'safe_url' => 'The :attribute URL is not allowed.',
  'password' => 'The password does not meet the security policy.',
  'strong_password' => 'The password must be at least 8 characters with letters, numbers and symbols.',
  'not_compromised' => 'This password has appeared in a data breach; please choose another.',

  // math/money
  'decimal' => 'The :attribute must have at most :max decimal places.',
  'money' => 'The :attribute must be a non-negative integer amount.',
  'positive' => 'The :attribute must be positive.',
  'negative' => 'The :attribute must be negative.',
  'non_negative' => 'The :attribute must not be negative.',
  'percent' => 'The :attribute must be a percentage between 0 and 100.',
  'numeric_range' => 'The :attribute must be between :min and :max.',

  // advanced
  'nested' => 'The :attribute must be a nested structure.',
  'each' => 'The :attribute must be a list.',
  'prohibited' => 'The :attribute field is prohibited.',
  'prohibited_if' => 'The :attribute field is prohibited when :other is :values.',
  'exclude_if' => 'The :attribute is invalid.',
  'distinct' => 'The :attribute field has duplicate values.',

  // attribute-based
  'required_attribute' => 'The :attribute field is required.',
  'email_attribute' => 'The :attribute must be a valid email.',
  'max_length' => 'The :attribute may not be greater than :max characters.',
  'min_length' => 'The :attribute must be at least :min characters.',
  'iran_mobile_attribute' => 'The mobile number is not valid.',

  // display names
  'attributes' => array (
    'email' => 'email',
    'age' => 'age',
    'name' => 'name',
    'title' => 'title',
    'mobile' => 'mobile',
    'password' => 'password',
    'password_confirmation' => 'password confirmation',
    'national_code' => 'national code',
    'price' => 'price',
    'qty' => 'quantity',
    'quantity' => 'quantity',
    'total' => 'total',
    'address' => 'address',
    'city' => 'city',
    'postal_code' => 'postal code',
    'sheba' => 'IBAN (Sheba)',
    'card_number' => 'card number',
    'description' => 'description',
    'slug' => 'slug',
    'status' => 'status',
    'created_at' => 'created at',
    'updated_at' => 'updated at',
  ),
);
