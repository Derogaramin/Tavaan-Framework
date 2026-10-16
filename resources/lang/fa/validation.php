<?php

declare(strict_types=1);

/*
 * پیام‌های اعتبارسنجی — فارسی (DOC-051 §7).
 * Placeholderها: :attribute، :min، :max، :size، :values، :other، :format
 */

return array (
  'failed' => 'دادهٔ ارسالی معتبر نیست',

  // حضور و نوع
  'required' => 'فیلد :attribute الزامی است.',
  'required_if' => 'فیلد :attribute زمانی که :other برابر :values است الزامی می‌باشد.',
  'required_unless' => 'فیلد :attribute الزامی است مگر اینکه :other برابر :values باشد.',
  'required_with' => 'فیلد :attribute هنگام وجود :values الزامی است.',
  'required_without' => 'فیلد :attribute هنگام نبود :values الزامی است.',
  'nullable' => ':attribute نامعتبر است.',
  'present' => 'فیلد :attribute باید در داده‌ها حضور داشته باشد.',
  'filled' => 'فیلد :attribute باید مقدار داشته باشد.',
  'sometimes' => ':attribute نامعتبر است.',
  'string' => ':attribute باید رشته باشد.',
  'integer' => ':attribute باید عدد صحیح باشد.',
  'numeric' => ':attribute باید عدد باشد.',
  'boolean' => ':attribute باید درست/نادرست باشد.',
  'array' => ':attribute باید آرایه باشد.',
  'object' => ':attribute نامعتبر است.',
  'date' => ':attribute باید تاریخ معتبر باشد.',
  'digits' => ':attribute باید :digits رقم باشد.',
  'digits_between' => ':attribute باید بین :min و :max رقم باشد.',

  // طول و بازه
  'min' => array (
    'numeric' => ':attribute باید حداقل :min باشد.',
    'string' => ':attribute باید حداقل :min نویسه باشد.',
    'array' => ':attribute باید حداقل :min آیتم داشته باشد.',
    'file' => ':attribute باید حداقل :min کیلوبایت باشد.',
  ),
  'max' => array (
    'numeric' => ':attribute نباید بزرگ‌تر از :max باشد.',
    'string' => ':attribute نباید بیشتر از :max نویسه باشد.',
    'array' => ':attribute نباید بیشتر از :max آیتم داشته باشد.',
    'file' => ':attribute نباید بزرگ‌تر از :max کیلوبایت باشد.',
  ),
  'between' => array (
    'numeric' => ':attribute باید بین :min و :max باشد.',
    'string' => ':attribute باید بین :min و :max نویسه باشد.',
    'array' => ':attribute باید بین :min و :max آیتم داشته باشد.',
    'file' => ':attribute باید بین :min و :max کیلوبایت باشد.',
  ),
  'size' => array (
    'numeric' => ':attribute باید برابر :size باشد.',
    'string' => ':attribute باید :size نویسه باشد.',
    'array' => ':attribute باید :size آیتم داشته باشد.',
    'file' => ':attribute باید :size کیلوبایت باشد.',
  ),
  'length' => ':attribute باید دقیقاً :size نویسه باشد.',
  'gt' => ':attribute باید بزرگ‌تر از :other باشد.',
  'gte' => ':attribute باید بزرگ‌تر یا مساوی :other باشد.',
  'lt' => ':attribute باید کوچک‌تر از :other باشد.',
  'lte' => ':attribute باید کوچک‌تر یا مساوی :other باشد.',

  // قالب
  'email' => ':attribute باید یک ایمیل معتبر باشد.',
  'url' => ':attribute باید یک نشانی وب معتبر باشد.',
  'uuid' => ':attribute باید UUID معتبر باشد.',
  'ulid' => ':attribute باید ULID معتبر باشد.',
  'ip' => ':attribute باید نشانی IP معتبر باشد.',
  'ipv4' => ':attribute باید نشانی IPv4 معتبر باشد.',
  'ipv6' => ':attribute باید نشانی IPv6 معتبر باشد.',
  'mac_address' => ':attribute باید نشانی MAC معتبر باشد.',
  'regex' => 'قالب :attribute نامعتبر است.',
  'not_regex' => 'قالب :attribute مجاز نیست.',
  'alpha' => ':attribute فقط می‌تواند شامل حروف باشد.',
  'alpha_num' => ':attribute فقط می‌تواند شامل حروف و اعداد باشد.',
  'alpha_dash' => ':attribute فقط می‌تواند شامل حروف، اعداد، خط تیره و زیرخط باشد.',
  'slug' => ':attribute باید اسلاگ معتبر باشد (حروف، اعداد و خط تیره).',
  'json' => ':attribute باید JSON معتبر باشد.',
  'date_format' => ':attribute باید با قالب :format مطابقت داشته باشد.',
  'timezone' => ':attribute باید منطقهٔ زمانی معتبر باشد.',

  // مقایسه و مجموعه
  'in' => ':attribute انتخاب‌شده معتبر نیست.',
  'not_in' => ':attribute انتخاب‌شده مجاز نیست.',
  'confirmed' => 'تکرار :attribute مطابقت ندارد.',
  'same' => ':attribute و :other باید یکسان باشند.',
  'different' => ':attribute و :other باید متفاوت باشند.',
  'accepted' => ':attribute باید پذیرفته شود.',
  'declined' => ':attribute باید رد شود.',

  // فایل
  'file' => ':attribute باید فایل باشد.',
  'image' => ':attribute باید تصویر (jpg/png/gif/bmp/webp) باشد.',
  'mimes' => ':attribute باید از نوع :values باشد.',
  'mimetypes' => ':attribute باید از نوع :values باشد.',
  'dimensions' => 'ابعاد تصویر :attribute مجاز نیست.',
  'max_filesize' => 'حجم :attribute نباید بیشتر از :max کیلوبایت باشد.',
  'uploaded_file' => 'بارگذاری :attribute ناموفق بود.',

  // دیتابیس
  'unique' => ':attribute قبلاً ثبت شده است.',
  'exists' => ':attribute انتخاب‌شده در دیتابیس وجود ندارد.',
  'exists_multiple' => 'برخی مقادیر :attribute در دیتابیس وجود ندارند.',
  'unique_composite' => 'ترکیب :attribute تکراری است.',

  // بومی ایران 🇮🇷
  'iran_mobile' => 'شمارهٔ موبایل معتبر نیست (نمونهٔ درست: 09121234567).',
  'iran_national_code' => 'کد ملی معتبر نیست.',
  'iran_postal_code' => 'کد پستی معتبر نیست (۱۰ رقم).',
  'iran_sheba' => 'شمارهٔ شبا معتبر نیست (IR + ۲۴ رقم).',
  'iran_card_number' => 'شمارهٔ کارت معتبر نیست (۱۶ رقم).',
  'iran_company_id' => 'شناسهٔ ملی شرکت معتبر نیست.',
  'jalali_date' => ':attribute باید تاریخ جلالی معتبر باشد.',
  'jalali_date_format' => ':attribute باید تاریخ جلالی با قالب :format باشد.',
  'persian_digits' => ':attribute باید با ارقام فارسی (۰-۹) نوشته شود.',

  // امنیتی
  'honeypot' => 'اعتبارسنجی ناموفق بود.',
  'no_html' => ':attribute نباید شامل HTML باشد.',
  'safe_url' => 'نشانی :attribute مجاز نیست.',
  'password' => 'رمز عبور سیاست امنیتی را برآورده نمی‌کند.',
  'strong_password' => 'رمز عبور باید حداقل ۸ نویسه با حروف، عدد و نماد باشد.',
  'not_compromised' => 'این رمز عبور در فهرست رمزهای لو‌رفته است؛ رمز دیگری انتخاب کنید.',

  // ریاضی/پول
  'decimal' => ':attribute باید عدد با حداکثر :max رقم اعشار باشد.',
  'money' => ':attribute باید مبلغ صحیح (ریال) و نامنفی باشد.',
  'positive' => ':attribute باید مثبت باشد.',
  'negative' => ':attribute باید منفی باشد.',
  'non_negative' => ':attribute نباید منفی باشد.',
  'percent' => ':attribute باید درصدی بین ۰ تا ۱۰۰ باشد.',
  'numeric_range' => ':attribute باید بین :min و :max باشد.',

  // پیشرفته
  'nested' => ':attribute باید ساختار تودرتو باشد.',
  'each' => ':attribute باید فهرست باشد.',
  'prohibited' => 'فیلد :attribute مجاز نیست.',
  'prohibited_if' => 'فیلد :attribute زمانی که :other برابر :values است مجاز نیست.',
  'exclude_if' => ':attribute نامعتبر است.',
  'distinct' => 'مقادیر :attribute تکراری است.',

  // Attribute-based
  'required_attribute' => 'فیلد :attribute الزامی است.',
  'email_attribute' => ':attribute باید ایمیل معتبر باشد.',
  'max_length' => ':attribute نباید بیشتر از :max نویسه باشد.',
  'min_length' => ':attribute باید حداقل :min نویسه باشد.',
  'iran_mobile_attribute' => 'شمارهٔ موبایل معتبر نیست.',

  // نام نمایشی فیلدها
  'attributes' => array (
    'email' => 'ایمیل',
    'age' => 'سن',
    'name' => 'نام',
    'title' => 'عنوان',
    'mobile' => 'موبایل',
    'password' => 'رمز عبور',
    'password_confirmation' => 'تکرار رمز عبور',
    'national_code' => 'کد ملی',
    'price' => 'قیمت',
    'qty' => 'تعداد',
    'quantity' => 'تعداد',
    'total' => 'مبلغ کل',
    'address' => 'نشانی',
    'city' => 'شهر',
    'postal_code' => 'کد پستی',
    'sheba' => 'شمارهٔ شبا',
    'card_number' => 'شمارهٔ کارت',
    'description' => 'توضیحات',
    'slug' => 'نامک',
    'status' => 'وضعیت',
    'created_at' => 'تاریخ ایجاد',
    'updated_at' => 'تاریخ ویرایش',
  ),
);
