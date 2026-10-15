#!/usr/bin/env bash
# تَوان | Tavaan — lint همهٔ فایل‌های PHP (php -l)
set -u
BASE="$(cd "$(dirname "$0")/../.." && pwd)"
FAIL=0
COUNT=0
while IFS= read -r -d '' f; do
  COUNT=$((COUNT+1))
  if ! OUT=$(php -l "$f" 2>&1); then
    echo "$OUT"
    FAIL=$((FAIL+1))
  fi
done < <(find "$BASE/core" "$BASE/engines" "$BASE/tests" "$BASE/config" "$BASE/bin" "$BASE/public" "$BASE/tools" -type f \( -name '*.php' -o -name 'tvn' \) -print0)
echo "Linted $COUNT files — $FAIL failure(s)."
exit $((FAIL > 0 ? 1 : 0))
