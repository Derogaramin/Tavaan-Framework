# DOC-016 — سیاست امنیتی تَوان (Security Policy) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-016 |
| مبنا | OWASP Top 10 (2021)، OWASP ASVS v4.0.3 Level 2، CWE Top 25، NIST SP 800-63B |
| وضعیت | Approved v1.0 — الزامی برای همهٔ موتورها |
| موتورهای مرتبط | crypt، hash، auth، rbac، audit، validate، security(guard)، http |

---

## ۱. مدل تهدید (Threat Model)

### ۱٫۱ دارایی‌ها (Assets)
| دارایی | حساسیت | نمونه |
|---|---|---|
| اعتبارنامهٔ کاربر | بحرانی | رمز (هش)، توکن، کلید 2FA |
| دادهٔ شخصی (PII) | بالا | نام، موبایل، ایمیل، کد ملی، آدرس |
| دادهٔ مالی | بحرانی | کارت، تراکنش، فاکتور |
| کلیدهای رمزنگاری | بحرانی | APP_KEY، JWT Secret، DB Password |
| Session/Cookie | بالا | Session ID، Remember Token |
| محتوای آپلودی | متوسط | تصویر، سند |
| کد منبع | بالا | منطق کسب‌وکار |
| لاگ‌ها | متوسط | شامل PII بالقوه |

### ۱٫۲ مهاجمان (Actors)
| Actor | توانایی | انگیزه |
|---|---|---|
| Anonymous Internet | اسکن انبوه، Exploit عمومی | نفوذ خودکار |
| Authenticated Low-Priv User | دسترسی به API/فرم‌ها | ارتقای دسترسی، دسترسی به دادهٔ دیگران |
| Insider (Admin/Dev) | دسترسی به DB/سرور | سوءاستفاده، نشت |
| Malicious Third-Party Plugin | اجرای کد درون فرایند | زنجیرهٔ تأمین |
| Compromised Dependency/Gateway | پاسخ‌های جعلی | تقلب مالی |

### ۱٫۳ مرزهای اعتماد (Trust Boundaries)
```
[Client] ──①──> [Reverse Proxy] ──②──> [PHP Entry Point] ──③──> [Kernel/Middleware]
   ──④──> [Controller/Validation] ──⑤──> [Application/Domain] ──⑥──> [Repository/DB]
   ──⑦──> [External Gateway (Payment/Mail/SMS)] ──⑧──> [Queue/Worker] ──⑨──> [Storage/Cache]
```
| مرز | کنترل اجباری |
|---|---|
| ① TLS، Rate Limit لبه، WAF (اختیاری) |
| ② Trust Proxy Config، Header Sanitization |
| ③ CSRF، CORS، Security Headers، Request Size Limit |
| ④ Validation کامل، Type Cast، Authorization |
| ⑤ Policy، Domain Rules، Idempotency |
| ⑥ Prepared Statement، Least Privilege DB User، Row-Level Scope |
| ⑦ SSRF Guard، Signature Verification، Timeout، Circuit Breaker |
| ⑧ Payload Allowlist، Max Tries، Sandbox |
| ⑨ Path Sandbox، MIME Validation، Encryption at Rest |

---

## ۲. نگاشت OWASP Top 10 → کنترل‌های تَوان

| OWASP | تهدید | کنترل تَوان | موتور | الزام |
|---|---|---|---|---|
| A01 Broken Access Control | دسترسی غیرمجاز | RBAC/ABAC + Policy + Row-Level Scope + IDOR Guard | `rbac`, `orm` | NFR-SEC-*, FR-RBAC-04 |
| A02 Cryptographic Failures | نشت/رمزگشایی | AES-256-GCM، Argon2id، TLS-only، Encrypted Cast | `crypt`, `hash` | FR-SEC-03/04 |
| A03 Injection | SQLi/Command/LDAP | Prepared Statement ۱۰۰٪، ممنوعیت Raw با ورودی، Escaper | `database`, `view` | FR-SEC-02, NFR-SEC-02 |
| A04 Insecure Design | نقص طراحی | Threat Model، ADR، Rate Limit، Business Rules در Domain | همه | DOC-016 |
| A05 Security Misconfiguration | پیکربندی ناامن | Secure Defaults، Header خودکار، Debug off in PROD | `config`, `http` | FR-SEC-09 |
| A06 Vulnerable Components | پکیج آسیب‌پذیر | **صفر وابستگی** = سطح حملهٔ حداقلی + Scanner برای پلاگین‌ها | `plugin` | CON-02 |
| A07 Auth Failures | سرقت نشست/بروت‌فورس | MFA، Lockout، Session Hardening، Password Policy | `auth`, `session` | FR-AUTH-01..07 |
| A08 Integrity Failures | CI/CD، Deserialization | Allowlist Unserialize، Signature برای پلاگین، Outbox | `error`, `plugin` | FR-SEC-13 |
| A09 Logging Failures | نبود رد پای حمله | Audit Chain، Structured Log، Masking، Retention | `log`, `audit` | FR-SEC-11/14 |
| A10 SSRF | فراخوانی داخلی | SSRF Guard: IP Block، DNS Pin، Allowlist دامنه | `http-client` | FR-SEC-08 |

---

## ۳. کنترل‌های اجباری به تفکیک حوزه

### ۳٫۱ ورودی (Input)
| ID | کنترل | جزئیات |
|---|---|---|
| SEC-IN-01 | 🔒 Allowlist Validation | هر فیلد: نوع، طول، الگو، بازه. هیچ فیلدی بدون قاعده وارد Domain نشود |
| SEC-IN-02 | 🔒 Type Coercion صریح | `(int)`, `(string)`, enum → نه تکیه بر PHP loose types |
| SEC-IN-03 | 🔒 حداکثر اندازه | Body ≤ `http.max_body_size` (پیش‌فرض 8MB)، رشته ≤ 64KB، آرایه ≤ 1000 عنصر، عمق ≤ 8 |
| SEC-IN-04 | 🔒 Normalization قبل از Validate | Trim، NFKC (Unicode)، حذف Bidi/Control chars، یکسان‌سازی ارقام فارسی/عربی |
| SEC-IN-05 | ⚠️ ممنوعیت Mass Assignment | فقط `fillable` یا DTO صریح |
| SEC-IN-06 | 🔒 File Upload | Magic Bytes + MIME + پسوند allowlist + Rename با Random + جدا از DocumentRoot |
| SEC-IN-07 | 🔒 JSON | `json_decode(..., flags: JSON_THROW_ON_ERROR \| JSON_BIGINT_AS_STRING)` + عمق ≤ 32 |
| SEC-IN-08 | 🔒 Header | فقط از `$_SERVER` با Allowlist کلید؛ هرگز از ورودی کاربر Header نسازید |

### ۳٫۲ خروجی (Output)
| ID | کنترل | جزئیات |
|---|---|---|
| SEC-OUT-01 | 🔒 Context-Aware Escaping | HTML body، HTML attribute، JS، URL، CSS — هرکدام تابع جدا |
| SEC-OUT-02 | 🔒 Auto-Escape در View | `{{ }}` همیشه escape؛ `{!! !!}` فقط با Review + کامنت دلیل |
| SEC-OUT-03 | 🔒 Content-Type صحیح | `Content-Type: application/json; charset=utf-8` برای API |
| SEC-OUT-04 | 🔒 `X-Content-Type-Options: nosniff` | همیشه |
| SEC-OUT-05 | ⚠️ `Content-Disposition: attachment` برای دانلود + `filename*` RFC 5987 |
| SEC-OUT-06 | 🔒 ممنوعیت نشت Stack Trace در PROD | فقط `refCode` |
| SEC-OUT-07 | ⚠️ Sanitize خروجی HTML غنی (Rich Text) با Allowlist تگ/attr | موتور `security` |

### ۳٫۳ احراز هویت و نشست
| ID | کنترل | جزئیات |
|---|---|---|
| SEC-AU-01 | 🔒 Argon2id با `memory_cost ≥ 65536`, `time_cost ≥ 4`, `threads = 1` | fallback: bcrypt cost 12 |
| SEC-AU-02 | 🔒 Rehash خودکار اگر `password_needs_rehash` |
| SEC-AU-03 | 🔒 Password Policy: ≥ 12 کاراکتر، Blacklist، بدون تشابه با ایمیل/نام |
| SEC-AU-04 | 🔒 Rate Limit ورود: ۵ تلاش در دقیقه برای هر (IP+username) |
| SEC-AU-05 | 🔒 Lockout: ۱۰ شکست → ۱۵ دقیقه قفل + هشدار |
| SEC-AU-06 | 🔒 Session Regeneration پس از ورود، تغییر نقش، تغییر رمز |
| SEC-AU-07 | 🔒 Cookie: `HttpOnly`, `Secure`(prod), `SameSite=Lax`, `Path=/` |
| SEC-AU-08 | 🔒 Idle Timeout 30min، Absolute Timeout 12h |
| SEC-AU-09 | ⚠️ Concurrent Session Limit + "خروج از همهٔ دستگاه‌ها" |
| SEC-AU-10 | 🔒 OTP: ۶ رقم، TTL ۲ دقیقه، ۱ بار مصرف، Rate Limit، بدون نشت در لاگ |
| SEC-AU-11 | 🔒 پیام خطای ورود یکسان برای کاربر/رمز (`auth.credentials.invalid`) |
| SEC-AU-12 | 🔒 Timing-Safe مقایسه با `hash_equals` |
| SEC-AU-13 | ⚠️ MFA برای نقش‌های `admin`/`super-admin` اجباری |
| SEC-AU-14 | 🔒 JWT: `alg` پین‌شده (HS256/RS256/EdDSA)، رد `none`، `iss/aud/exp/nbf/iat/jti` اجباری |
| SEC-AU-15 | 🔒 Refresh Token Rotation + Reuse Detection (Family) |

### ۳٫۴ کنترل دسترسی
| ID | کنترل |
|---|---|
| SEC-AC-01 | 🔒 Deny by Default — هیچ عملی بدون مجوز صریح |
| SEC-AC-02 | 🔒 بررسی مجوز در Application Layer نه فقط UI |
| SEC-AC-03 | 🔒 IDOR Guard: همهٔ دسترسی به رکورد با Ownership/Policy |
| SEC-AC-04 | 🔒 Row-Level Scope خودکار برای tenant و owner |
| SEC-AC-05 | 🔒 مجوزهای API جدا از مجوزهای وب (یا مشترک با context) |
| SEC-AC-06 | ⚠️ Least Privilege برای DB User (بدون DROP در PROD) |
| SEC-AC-07 | 🔒 تفویض اختیار با بازهٔ زمانی و ثبت Audit |

### ۳٫۵ رمزنگاری
| ID | کنترل |
|---|---|
| SEC-CR-01 | 🔒 `APP_KEY` = ۳۲ بایت Random، Base64، در ENV |
| SEC-CR-02 | 🔒 AES-256-GCM با IV تصادفی ۱۲ بایت برای هر رمزنگاری |
| SEC-CR-03 | 🔒 AAD (Additional Authenticated Data) شامل Key ID |
| SEC-CR-04 | 🔒 Key Versioning: پیشوند `v1:`/`v2:` برای Rotation |
| SEC-CR-05 | 🔒 HKDF برای Derivation کلیدهای فرعی |
| SEC-CR-06 | 🔒 `random_bytes`/`random_int` فقط (CSPRNG) |
| SEC-CR-07 | 🔒 ممنوعیت MD5/SHA1 برای اهداف امنیتی (فقط Checksum غیرامن با علامت) |
| SEC-CR-08 | 🔒 HMAC با `hash_hmac('sha256', ..., key)` + `hash_equals` |
| SEC-CR-09 | ⚠️ Encrypted Cast برای PII (موبایل، کد ملی) + جست‌وجو با Blind Index |
| SEC-CR-10 | 🔒 هرگز رمز/کلید در URL یا کوئری‌استرینگ |

### ۳٫۶ SSRF و درخواست‌های خروجی
| ID | کنترل |
|---|---|
| SEC-SS-01 | 🔒 Block: `127.0.0.0/8`, `10/8`, `172.16/12`, `192.168/16`, `169.254/16`, `::1`, `fc00::/7`, `fe80::/10`, `0.0.0.0` |
| SEC-SS-02 | 🔒 DNS Resolution سپس اتصال به IP Resolve شده (جلوگیری از DNS Rebinding) |
| SEC-SS-03 | 🔒 Allowlist دامنه/پروتکل (فقط http/https) |
| SEC-SS-04 | 🔒 ممنوعیت Redirect خودکار به دامنهٔ جدید بدون Re-validation |
| SEC-SS-05 | ⚠️ Timeout + حداکثر اندازهٔ پاسخ + محدودیت نرخ |

### ۳٫۷ Headerهای امنیتی (پیش‌فرض)
```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
                         img-src 'self' data:; font-src 'self'; object-src 'none';
                         base-uri 'self'; form-action 'self'; frame-ancestors 'none';
                         upgrade-insecure-requests
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self)
Cross-Origin-Opener-Policy: same-origin
Cross-Origin-Resource-Policy: same-origin
X-Tavaan-Ref: <refCode on errors>
Server: (حذف/مخفی)
```
> 🔒 در `config/security.php` قابل تنظیم؛ غیرفعال‌سازی هر مورد → هشدار لاگ + Badge در Debug Bar.

### ۳٫۸ لاگ و ممیزی
| ID | کنترل |
|---|---|
| SEC-LG-01 | 🔒 Masking خودکار: `password*`, `token`, `secret`, `card*`, `cvv`, `authorization`, `cookie`, `national_id`, `mobile` |
| SEC-LG-02 | 🔒 Audit Chain: هر رکورد شامل `hash(prev_hash + payload)` |
| SEC-LG-03 | 🔒 ثبت رویدادهای امنیتی: Login Success/Fail، Logout، Permission Change، Data Export، Delete، Config Change |
| SEC-LG-04 | 🔒 Retention: لاگ عمومی ۹۰ روز، Audit ۵ سال (قابل تنظیم) |
| SEC-LG-05 | ⚠️ Immutable Storage برای Audit (Write-Once) |
| SEC-LG-06 | 🔒 ممنوعیت Log کردن مقدار کامل PII |

---

## ۴. امنیت زنجیرهٔ تأمین (Supply Chain)

| کنترل | توضیح |
|---|---|
| 🔒 SC-01 | هسته صفر وابستگی → هیچ CVE خارجی |
| 🔒 SC-02 | پلاگین‌ها دارای Manifest + امضا (Ed25519) + Hash در `plugins.lock` |
| ⚠️ SC-03 | نصب پلاگین فقط از Registry تأییدشده یا فایل محلی با تأیید دستی |
| 🔒 SC-04 | Sandbox پلاگین: بدون `eval`، بدون دسترسی مستقیم DB (فقط Contract)، بدون FS خارج از مسیرش |
| ⚠️ SC-05 | CI شامل Secret Scanner (Entropy + Pattern) |
| ⚠️ SC-06 | SBOM تولید خودکار (`tvn security:sbom`) |

---

## ۵. امنیت پایگاه داده

| کنترل |
|---|
| 🔒 DB-01 Prepared Statement ۱۰۰٪ — ممنوعیت `whereRaw` با ورودی کاربر (فقط با Binding) |
| 🔒 DB-02 Identifier Quoting توسط Grammar (هرگز از ورودی کاربر نام ستون نسازید) |
| ⚠️ DB-03 کاربر DB با حداقل مجوز؛ کاربر Migration جدا |
| 🔒 DB-04 ممنوعیت `TRUNCATE/DROP` در زمان اجرا (فقط Migration) |
| ⚠️ DB-05 رمزنگاری در سطح ستون برای PII + Blind Index |
| 🔒 DB-06 Query Timeout و Max Rows |
| ⚠️ DB-07 Connection Encryption (TLS) برای DB راه دور |
| 🔒 DB-08 Backup رمزنگاری‌شده |

---

## ۶. تست‌های امنیتی اجباری 🧪

| تست | شرح |
|---|---|
| 🧪 SEC-T-01 | SQL Injection Fuzzing روی همهٔ Endpointها (Payload Set: OWASP + SQLMap top 100) |
| 🧪 SEC-T-02 | XSS Context Test (HTML/Attr/JS/URL) |
| 🧪 SEC-T-03 | CSRF: درخواست بدون توکن → 419 |
| 🧪 SEC-T-04 | Path Traversal: `../../etc/passwd` → رد |
| 🧪 SEC-T-05 | IDOR: کاربر A به رکورد کاربر B → 403 |
| 🧪 SEC-T-06 | Privilege Escalation: تغییر نقش از طریق Mass Assignment → رد |
| 🧪 SEC-T-07 | Session Fixation: Session ID پس از ورود تغییر می‌کند |
| 🧪 SEC-T-08 | Upload: فایل با پسوند `.php` و Magic جعلی → رد |
| 🧪 SEC-T-09 | SSRF: درخواست به `http://169.254.169.254/` → رد |
| 🧪 SEC-T-10 | Rate Limit: ۱۰۱ درخواست در دقیقه → 429 |
| 🧪 SEC-T-11 | Timing Attack: مقایسهٔ توکن با `hash_equals` (بررسی AST) |
| 🧪 SEC-T-12 | Header Audit: همهٔ Headerهای امنیتی در پاسخ حاضر |
| 🧪 SEC-T-13 | Error Leak: پاسخ ۵۰۰ بدون مسیر/trace |
| 🧪 SEC-T-14 | Crypto Vector Test: Test Vectors استاندارد برای AES-GCM/HKDF |
| 🧪 SEC-T-15 | JWT: `alg=none` و Key Confusion → رد |

---

## ۷. پاسخ به حادثه (Incident Response)

1. **شناسایی** — `tvn security:scan`، لاگ Audit، هشدار Metric.
2. **مهار** — `tvn maintenance:on`، غیرفعال‌سازی مسیر، Block IP.
3. **ریشه‌یابی** — `tvn log:find <refCode>`، بازسازی Trace.
4. **بازیابی** — Rotate کلیدها (`tvn key:rotate`)، Invalidate Sessionها (`tvn auth:logout-all`).
5. **اطلاع‌رسانی** — به کاربران متأثر (الزام قانونی ۷۲ ساعت).
6. **درس‌آموزی** — ثبت ADR و افزودن تست رگرسیون.

---

## ۸. چک‌لیست امنیتی Merge (PR Gate) 🔒

- [ ] ورودی‌ها Validate شده‌اند (Rule + DTO)؟
- [ ] خروجی‌ها Escape شده‌اند؟
- [ ] مجوز در سطح Application بررسی شده (نه فقط UI)؟
- [ ] کوئری‌ها Prepared هستند و Raw با ورودی کاربر نداریم؟
- [ ] دادهٔ حساس در لاگ Mask شده؟
- [ ] رویداد امنیتی در Audit ثبت می‌شود؟
- [ ] Rate Limit روی Endpoint حساس فعال است؟
- [ ] تست‌های امنیتی مرتبط اضافه شده؟
- [ ] هیچ Secret هاردکد نشده؟
- [ ] Headerهای امنیتی دست‌نخورده‌اند؟
