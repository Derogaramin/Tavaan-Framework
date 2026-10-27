#!/usr/bin/env bash
# تَوان | Tavaan — یکپارچه‌سازی محیط توسعه و Git (DOC-135 §3.1)
# idempotent: هر بار قابل اجراست — فقط کمبودها را تکمیل می‌کند.
#
# کاربرد:
#   bash tools/scripts/git-setup.sh                       # هویت پیش‌فرض محلی
#   bash tools/scripts/git-setup.sh "نام شما" you@ex.com  # هویت دلخواه
#
# آنچه انجام می‌دهد:
#   ۱) git init -b main (اگر مخزن وجود ندارد)
#   ۲) تنظیم هویت محلی (user.name/user.email — فقط همین مخزن)
#   ۳) فعال‌سازی hookها: git config core.hooksPath tools/git-hooks
#   ۴) راستی‌آزمایی .gitignore (فایل‌های زمان‌اجرا هرگز track نشوند)
#   ۵) گزارش وضعیت و راهنمای گیت‌های کیفیت
set -eu

# ریشهٔ مخزن = دو سطح بالاتر از tools/scripts
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

NAME="${1:-Tavaan Framework}"
EMAIL="${2:-dev@tavaan.dev}"

echo "🔧 یکپارچه‌سازی محیط توسعه و Git — تَوان"
echo "   ریشه: $ROOT"

# ۱) init
if [ ! -d .git ]; then
  git init -b main >/dev/null
  echo "✅ مخزن git ساخته شد (شاخهٔ main)"
else
  echo "ℹ️  مخزن git از قبل موجود است"
fi

# ۲) هویت محلی (بدون دستبرد به config سراسری کاربر)
git config user.name  "$NAME"
git config user.email "$EMAIL"
echo "✅ هویت محلی: $NAME <$EMAIL>"

# ۳) hookها
git config core.hooksPath tools/git-hooks
chmod +x tools/git-hooks/pre-commit tools/git-hooks/commit-msg 2>/dev/null || true
echo "✅ hookها فعال: tools/git-hooks (pre-commit + commit-msg)"
echo "   دروازهٔ کامل پیش از کامیت:  TVN_HOOK_FULL=1 git commit …"

# ۴) راستی‌آزمایی خروج‌سازی فایل‌های زمان‌اجرا
LEAK=0
for f in .env tavaan; do
  if git ls-files --error-unmatch "$f" >/dev/null 2>&1; then
    echo "❌ $f در git track شده است — نباید می‌شد! (git rm --cached $f)" >&2
    LEAK=1
  fi
done
if git check-ignore -q .env && git check-ignore -q tavaan; then
  echo "✅ .env و فایل SQLite زمان‌اجرا مطابق .gitignore خارج‌اند"
elif [ "$LEAK" -eq 0 ]; then
  echo "⚠️  .gitignore را بررسی کنید (.env و /tavaan باید ignore شوند)"
fi
[ "$LEAK" -eq 0 ] || exit 1

# ۵) وضعیت و راهنما
echo ""
if git rev-parse HEAD >/dev/null 2>&1; then
  echo "📌 آخرین کامیت: $(git log -1 --format='%h %s')"
  echo "🏷️  تگ‌ها: $(git tag | tail -3 | tr '\n' ' ')"
else
  echo "📌 هنوز کامیتی وجود ندارد — نخستین کامیت:"
  echo "     git add -A && git commit   (hookها خودکار اجرا می‌شوند)"
fi
cat <<'GUIDE'

گیت‌های کیفیت پیش از هر PR (DOC-135 §2.4):
  bash tools/scripts/lint.sh          # php -l سراسری
  php tools/scripts/check-forbidden.php
  php bin/tvn arch:check
  php bin/tvn security:scan --level=high
  php bin/tvn engine:validate && php bin/tvn module:validate
  php bin/tvn test                    # ~۲ دقیقه — ۱۷۰۴ تست
GUIDE
