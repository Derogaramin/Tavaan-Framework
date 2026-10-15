# DOC-111 — تحلیل ایستا و CI/CD (Static Analysis & Pipeline) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-111 |
| مرتبط | DOC-010 (قواعد PHP)، DOC-110 (تست)، DOC-113 (DoD) |

---

## ۱. ابزارهای کیفیت (فقط در dev — هسته بدون آن‌ها اجرا می‌شود)

| ابزار | نقش | سطح |
|---|---|---|
| **PHPStan** (level max) | تحلیل ایستای نوع | 🔒 اجباری |
| **PHP-CS-Fixer** | سبک کد (DOC-011) | 🔒 اجباری |
| **Infection** | Mutation testing | ⚠️ برای هسته |
| **Psalm** (security rules) | تحلیل امنیتی | ⚠️ |
| **phpmd** | پیچیدگی/بوهای کد | 💡 |
| **tools/scripts/check-deps.sh** | ممنوعیت vendor | 🔒 |
| **tools/scripts/verify-docs.py** | سلامت مستندات | ⚠️ |

> 🔒 این ابزارها **وابستگی زمان اجرا نیستند**؛ فقط در CI/dev. پروژه در production
> بدون هیچ‌کدام اجرا می‌شود (اصل صفر‌وابستگی).

---

## ۲. PHPStan — پیکربندی

```neon
# phpstan.neon.dist
parameters:
    level: max
    paths:
        - core
        - engines
        - modules
        - app/src
    excludePaths:
        - */tests/*
        - */storage/*
    treatPhpDocTypesAsCertain: false
    reportUnmatchedIgnoredErrors: false
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    bootstrapFiles:
        - core/Support/helpers.php
    scanDirectories:
        - engines
    universalObjectCratesClasses:
        - Tavaan\Engine\Database\ORM\Model
```

🔒 **بدون Baseline** — هر خطا باید رفع شود، نه اینکه ignore شود.
🔒 اگر موقتاً لازم است: `@phpstan-ignore` با توضیح + Issue ID.

---

## ۳. قوانین سفارشی PHPStan (برای قواعد تَوان)

```php
// tools/phpstan/rules/NoEvalRule.php
final class NoEvalRule implements Rule
{
    public function getNodeType(): string { return FuncCall::class; }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name->toString() === 'eval') {
            return [RuleErrorBuilder::message('eval() ممنوع است (TV-NO-EVAL)')->build()];
        }
        return [];
    }
}

// rules/: NoEvalRule, NoFloatMoneyRule, NoMtRandForTokenRule,
//         StrictTypesRule, NoDynamicPropertyRule, NoVendorRequireRule,
//         FinalClassRule, MessageIdRule (رشتهٔ خام در استثنا)
```

---

## ۴. پایپ‌لاین CI

```yaml
# .github/workflows/ci.yml  (یا GitLab CI معادل)
name: CI
on: [push, pull_request]

jobs:
  quality:
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
        db: ['mysql:8.0', 'postgres:15', 'sqlite:3']
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo, pdo_mysql, pdo_pgsql, pdo_sqlite, mbstring, openssl, gd, redis
          coverage: pcov
          tools: composer

      # ۱) Syntax
      - name: Lint PHP
        run: find core engines modules app -name '*.php' -print0 | xargs -0 -n1 php -l

      # ۲) No vendor dependency
      - name: Zero-dependency check
        run: bash tools/scripts/check-deps.sh

      # ۳) Style
      - name: Code style
        run: vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php

      # ۴) Static analysis
      - name: PHPStan
        run: vendor/bin/phpstan analyse --error-format=github

      # ۵) Deprecated check (با تبدیل به خطا)
      - name: No deprecations
        run: php -d error_reporting=E_ALL bin/tvn test --fail-on-deprecation

      # ۶) Tests
      - name: Test
        run: php bin/tvn test --coverage --db=${{ matrix.db }}

      # ۷) Architecture
      - name: Arch check
        run: php bin/tvn arch:check

      # ۸) Security scan
      - name: Security
        run: php bin/tvn security:scan

      # ۹) Benchmarks
      - name: Bench
        run: php bin/tvn bench:all --fail-on-regression

      # ۱۰) Docs
      - name: Verify docs
        run: python3 tools/scripts/verify-docs.py

      - name: Upload coverage
        uses: codecov/codecov-action@v4
```

---

## ۵. Gateهای Merge (PR Checks) 🔒

| Gate | شرط | مسدودکننده؟ |
|---|---|---|
| `php -l` | صفر syntax error | 🔒 بله |
| Zero-dependency | بدون `require vendor` | 🔒 بله |
| PHPStan level max | صفر error | 🔒 بله |
| CS-Fixer | صفر diff | 🔒 بله |
| No Deprecated | صفر `E_DEPRECATED` در 8.2/8.3/8.4 | 🔒 بله |
| Unit Tests | همه سبز | 🔒 بله |
| Coverage | ≥ آستانهٔ حوزه | 🔒 بله |
| Arch Check | صفر نقض مرز | 🔒 بله |
| Security Scan | صفر High/Critical | 🔒 بله |
| Benchmarks | بدون regression > ۵٪ | ⚠️ هشدار |
| Docs | مستند موتور به‌روز | ⚠️ هشدار |
| Mutation Score | ≥ ۸۵٪ (هسته) | ⚠️ برای core |

---

## ۶. اسکریپت‌های محلی

### ۶٫۱ `tools/scripts/check-deps.sh`
```bash
#!/usr/bin/env bash
set -euo pipefail
# 🔒 هسته و موتورها نباید vendor/ یا require خارجی داشته باشند
violations=0

# بررسی require/include به vendor
if grep -RnE "require.*vendor/|include.*vendor/" core engines modules app 2>/dev/null; then
    echo "❌ require به vendor در کد اصلی یافت شد"; violations=1
fi

# بررسی use از namespaceهای خارجی (غیر Tavaan/App/Modules)
if grep -RnhoE "^use [A-Za-z0-9_\\\\]+;" core engines \
   | grep -vE "use (Tavaan|App|Modules|Kits|Database|Tests|Plugins)\\\\" \
   | grep -vE "use (function|const) " \
   | sort -u; then
    echo "⚠️  use از namespace خارجی (بررسی شود)"; 
fi

# بررسی composer.json با require پکیج
for f in $(find core engines -name composer.json); do
    if grep -qE '"require"[[:space:]]*:[[:space:]]*\{[^}]*"[a-z]' "$f"; then
        echo "❌ $f دارای require پکیج است"; violations=1
    fi
done

[ $violations -eq 0 ] && echo "✅ Zero-dependency check passed" || exit 1
```

### ۶٫۲ `tools/scripts/verify-docs.py`
```python
#!/usr/bin/env python3
"""بررسی سلامت مستندات: لینک‌ها، DOC-ID، بلوک machine-readable."""
import re, sys, pathlib
ROOT = pathlib.Path(__file__).parent.parent.parent
errors = []

# 1. هر سند موتور باید DOC-ID و بلوک machine-readable داشته باشد
for doc in (ROOT / "docs/03-engines").glob("*.md"):
    text = doc.read_text(encoding="utf-8")
    if doc.name == "README.md":
        continue
    if not re.search(r"DOC-ID", text):
        errors.append(f"{doc}: DOC-ID ندارد")
    if "machine-readable:" not in text:
        errors.append(f"{doc}: بلوک machine-readable ندارد")

# 2. لینک‌های داخلی شکسته
for md in ROOT.rglob("*.md"):
    text = md.read_text(encoding="utf-8")
    for link in re.findall(r"\]\(([^)]+\.md)\)", text):
        if link.startswith("http"):
            continue
        target = (md.parent / link).resolve()
        if not target.exists():
            errors.append(f"{md}: لینک شکسته → {link}")

if errors:
    print("❌ مشکلات مستندات:")
    for e in errors: print("  -", e)
    sys.exit(1)
print("✅ Docs OK")
```

---

## ۷. Security Scanning (`tvn security:scan`)

| بررسی | روش |
|---|---|
| توابع خطرناک | AST scan: `eval`, `exec`, `system`, `unserialize`, `assert` |
| Secret هاردکد | Entropy + pattern (API key, password) |
| SQL خام با الحاق | regex + AST |
| XSS بالقوه | `{!! !!}` بدون کامنت دلیل |
| مجوز ناموجود | route بدون middleware auth/can |
| Deprecated | `E_DEPRECATED` |
| SBOM | تولید فهرست (برای پلاگین‌ها) |

```php
final class SecurityScanner
{
    private const array DANGEROUS_FUNCS = [
        'eval','exec','system','passthru','shell_exec','popen','proc_open',
        'assert','create_function','unserialize','extract','mb_ereg_replace',  // با /e
    ];

    public function scan(string $path): ScanReport
    {
        $findings = [];
        foreach ($this->phpFiles($path) as $file) {
            $findings = \array_merge($findings, $this->scanFile($file));
        }
        return new ScanReport($findings);
    }
}
```

---

## ۸. CD (Continuous Deployment)

```
Merge به main
  → Build: tvn optimize (تولید cacheها)
  → Package: tarball (بدون vendor/, tools/, tests/)
  → Deploy:
      - Atomic release (symlink)
      - tvn migrate --force
      - tvn config:cache && route:cache && view:cache
      - opcache reset / php-fpm reload
  → Health check: GET /health/ready
  → اگر fail → rollback (release قبلی)
```

```bash
# deploy.sh (نمونه)
#!/usr/bin/env bash
set -euo pipefail
RELEASE=releases/$(date +%Y%m%d%H%M%S)
mkdir -p "$RELEASE"
tar xzf tavaan-build.tar.gz -C "$RELEASE"
cd "$RELEASE"
cp ../../.env .
php bin/tvn migrate --force
php bin/tvn optimize
ln -sfn "$(pwd)" ../../current    # atomic symlink
systemctl reload php-fpm
curl -fsS http://localhost/health/ready || { echo "rollback"; ln -sfn ../previous ../../current; exit 1; }
```

---

## ۹. معیار پذیرش 🧪
- AC-CI-01: همهٔ Gateهای §5 در CI اجرا می‌شوند.
- AC-CI-02: PR با هر خطای PHPStan/CS/Deprecated مسدود می‌شود.
- AC-CI-03: ماتریس ۳ نسخهٔ PHP × ۳ دیتابیس سبز.
- AC-CI-04: `check-deps.sh` صفر vendor در هسته.
- AC-CI-05: `security:scan` صفر High/Critical.
- AC-CI-06: CD با atomic release و rollback خودکار.
