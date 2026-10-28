#!/usr/bin/env python3
"""build-manifest.py — بازتولید/همگام‌سازی `docs/manifest.json` (DOC-000 §منابع ماشین‌خوان).

کارها:
  1) بازمحاسبهٔ sha256 (۱۶ نویسهٔ نخست) برای همهٔ اسناد ثبت‌شده؛
  2) ثبت اسناد جدیدِ دارای DOC-ID زیر `docs/` (ورودی تازه پس از آخرین هم‌بخشی)؛
  3) همگام‌سازی `framework.version` (از `tavaan.json`) و `totals.documents`.

استفاده:
    python3 tools/scripts/build-manifest.py            # نوشتن manifest
    python3 tools/scripts/build-manifest.py --check    # فقط گزارش drift (بدون نوشتن؛ exit=1 اگر ناهمگام)

صفر‌وابستگی (فقط کتابخانهٔ استاندارد Python ≥ 3.9).
"""

from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "docs" / "manifest.json"


def short_hash(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()[:16]


def doc_id_of(text: str) -> str | None:
    """DOC-ID معتبر: فقط سرفصل `# DOC-xxx` یا سطر جدول متادیتا (نه هر ذکر)."""
    m = (
        re.search(r"^#\s+(DOC-\d{3})\b", text, re.M)
        or re.search(r"\|\s*DOC-ID\s*\|\s*(DOC-\d{3})\s*\|", text)
    )
    return m.group(1) if m else None


def title_of(text: str) -> str:
    for line in text.splitlines()[:12]:
        if line.startswith("# "):
            heading = line[2:].strip()
            return re.sub(r"^DOC-\d+\s*—\s*", "", heading)
    return ""


def framework_version() -> str | None:
    raw = (ROOT / "tavaan.json").read_text(encoding="utf-8")
    m = re.search(r'"version"\s*:\s*"([^"]+)"', raw)
    return m.group(1) if m else None


def main() -> int:
    check_only = "--check" in sys.argv

    data = json.loads(MANIFEST.read_text(encoding="utf-8"))
    catalog: list[dict] = data["catalog"]
    changes: list[str] = []

    # 1) تازه‌سازی hash ورودیهای موجود ────────────────────────────────
    for entry in catalog:
        path = ROOT / entry["path"]
        if not path.exists():
            print(f"  ✗ فایل ثبت‌شده وجود ندارد: {entry['path']}")
            continue
        digest = short_hash(path)
        if entry.get("sha256") != digest:
            entry["sha256"] = digest
            changes.append(f"~ {entry['doc_id']} ({entry['path']})")

    # 2) ثبت اسناد تازه ────────────────────────────────────────────────
    known = {entry["path"] for entry in catalog}
    labels: dict[str, str] = {}
    for entry in catalog:
        labels.setdefault(entry["section"], entry.get("section_label", ""))

    for path in sorted((ROOT / "docs").rglob("*.md")):
        rel = path.relative_to(ROOT).as_posix()
        if rel in known or rel.startswith("docs/reports/"):
            continue  # گزارش‌های پیاده‌سازی سند کاتالوگ نیستند (فقط به DOC-ID اشاره دارند)
        text = path.read_text(encoding="utf-8")
        did = doc_id_of(text)
        if did is None:
            continue
        parts = rel.split("/")
        section = parts[1] if len(parts) > 2 else "index"
        entry = {
            "section": section,
            "section_label": labels.get(section, ""),
            "doc_id": did,
            "title": title_of(text),
            "path": rel,
            "machine_readable": "machine-readable:" in text,
            "sha256": short_hash(path),
        }
        idx = max(
            (i for i, e in enumerate(catalog) if e["section"] == section),
            default=len(catalog) - 1,
        )
        catalog.insert(idx + 1, entry)
        known.add(rel)
        changes.append(f"+ {did} ({rel})")

    # 3) نگارش و جمع‌ها ─────────────────────────────────────────────────
    version = framework_version()
    if version is not None and data.get("framework", {}).get("version") != version:
        data["framework"]["version"] = version
        changes.append(f"~ framework.version → {version}")

    totals = data.setdefault("totals", {})
    if totals.get("documents") != len(catalog):
        totals["documents"] = len(catalog)
        changes.append(f"~ totals.documents → {len(catalog)}")

    if not changes:
        print("  ✓ manifest همگام است — تغییری لازم نبود.")
        return 0

    print(f"  {'بررسی' if check_only else 'به‌روزرسانی'} manifest — {len(changes)} تغییر:")
    for line in changes:
        print(f"    {line}")

    if check_only:
        print("  ✗ manifest ناهمگام است — build-manifest.py را اجرا کنید.")
        return 1

    MANIFEST.write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print(f"  ✓ docs/manifest.json نوشته شد ({len(catalog)} سند).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
