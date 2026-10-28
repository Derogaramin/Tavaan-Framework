#!/usr/bin/env python3
"""verify-docs.py — اعتبارسنجی کل مجموعهٔ مستندات (DOC-000 §قواعد).

بررسی‌ها:
  1) `docs/manifest.json` و همهٔ `docs/schemas/*.json` قابل parse باشند؛
  2) هر مسیر ثبت‌شده در catalog وجود داشته باشد؛
  3) doc_id یکتا باشد؛
  4) sha256 هر سند با فایل روی دیسک همگام باشد (وگرنه: build-manifest.py)؛
  5) هر `docs/**/*.md` دارای DOC-ID در catalog ثبت شده باشد؛
  6) هر DOC-ID (جز DOC-000) در جدول شاخص `docs/README.md` آمده باشد؛
  7) `framework.version` با `tavaan.json` یکسان باشد.

خروجی: گزارش ✓/✗ و exit code (۰ = سالم). صفر‌وابستگی (Python ≥ 3.9).
"""

from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "docs" / "manifest.json"
INDEX = ROOT / "docs" / "README.md"


def short_hash(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()[:16]


def doc_id_of(text: str) -> str | None:
    """DOC-ID معتبر: فقط سرفصل `# DOC-xxx` یا سطر جدول متادیتا (نه هر ذکر)."""
    m = (
        re.search(r"^#\s+(DOC-\d{3})\b", text, re.M)
        or re.search(r"\|\s*DOC-ID\s*\|\s*(DOC-\d{3})\s*\|", text)
    )
    return m.group(1) if m else None


def main() -> int:
    errors: list[str] = []
    warnings: list[str] = []

    # 1) JSONها ─────────────────────────────────────────────────────────
    try:
        data = json.loads(MANIFEST.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        print(f"  ✗ manifest قابل خواندن نیست: {exc}")
        return 1
    for schema in sorted((ROOT / "docs" / "schemas").glob("*.json")):
        try:
            json.loads(schema.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            errors.append(f"schema نامعتبر {schema.name}: {exc}")

    catalog: list[dict] = data.get("catalog", [])

    # 2/3) وجود فایل + یکتایی doc_id ────────────────────────────────────
    seen_ids: set[str] = set()
    seen_paths: set[str] = set()
    for entry in catalog:
        path = ROOT / entry["path"]
        if not path.exists():
            errors.append(f"فایل ثبت‌شده وجود ندارد: {entry['path']}")
        if entry["path"] in seen_paths:
            errors.append(f"مسیر تکراری در catalog: {entry['path']}")
        seen_paths.add(entry["path"])
        did = entry.get("doc_id")
        if did:  # ADRها و نظایر با doc_id=null — خارج از بررسی یکتایی
            if did in seen_ids:
                errors.append(f"doc_id تکراری: {did}")
            seen_ids.add(did)

    # 4) همگامی hash ────────────────────────────────────────────────────
    stale = 0
    for entry in catalog:
        path = ROOT / entry["path"]
        if path.exists() and entry.get("sha256") != short_hash(path):
            stale += 1
            warnings.append(f"hash ناهمگام: {entry['path']}")
    if stale:
        errors.append(f"{stale} سند با hash ناهمگام — build-manifest.py اجرا شود")

    # 5) ثبت بودن همهٔ اسناد دارای DOC-ID ───────────────────────────────
    known_paths = {entry["path"] for entry in catalog}
    for path in sorted((ROOT / "docs").rglob("*.md")):
        rel = path.relative_to(ROOT).as_posix()
        if rel in known_paths or rel.startswith("docs/reports/"):
            continue
        did = doc_id_of(path.read_text(encoding="utf-8"))
        if did is not None:
            errors.append(f"سند ثبت‌نشده در manifest: {rel} ({did})")

    # 6) پوشش شاخص docs/README.md ───────────────────────────────────────
    index_text = INDEX.read_text(encoding="utf-8") if INDEX.exists() else ""
    if index_text == "":
        errors.append("docs/README.md یافت نشد")
    else:
        covered = set(re.findall(r"DOC-\d{3}[A-Z]?", index_text))
        # بسط بازه‌ها: مانند «DOC-031…075» یا «DOC-091…098»
        for m in re.finditer(r"DOC-(\d{3})\s*[…\-–—]\s*(\d{3})", index_text):
            lo, hi = int(m.group(1)), int(m.group(2))
            if lo <= hi <= lo + 200:
                covered.update(f"DOC-{i:03d}" for i in range(lo, hi + 1))
        for entry in catalog:
            did = entry.get("doc_id")
            if not did or did == "DOC-000":
                continue
            p = entry.get("path", "")
            if (
                p.startswith("docs/adr/")
                or p.startswith("docs/03-engines/")
                or re.fullmatch(r"DOC-\d{3}E", did)
            ):
                continue  # ADR/اسناد موتور/پیوست‌های E با ردیف‌های بازه‌ای پوشش می‌یابند
            if did not in covered:
                errors.append(f"DOC-ID در شاخص نیست: {did}")

    # 7) نگارش فریمورک + totals ─────────────────────────────────────────
    raw_tj = (ROOT / "tavaan.json").read_text(encoding="utf-8")
    m = re.search(r'"version"\s*:\s*"([^"]+)"', raw_tj)
    tj_version = m.group(1) if m else None
    fw_version = data.get("framework", {}).get("version")
    if tj_version is not None and fw_version != tj_version:
        errors.append(f"framework.version={fw_version} ≠ tavaan.json={tj_version}")
    totals = data.get("totals", {})
    if totals.get("documents") != len(catalog):
        errors.append(f"totals.documents={totals.get('documents')} ≠ len(catalog)={len(catalog)}")

    # گزارش ─────────────────────────────────────────────────────────────
    totals = data.get("totals", {})
    print(f"  اسناد: {len(catalog)} (totals.documents={totals.get('documents')})"
          f" • موتورها: {totals.get('engines')} • ADRها: {totals.get('adrs')}"
          f" • نگارش: {fw_version}")
    for w in warnings[:10]:
        print(f"  ⚠ {w}")
    if errors:
        for e in errors:
            print(f"  ✗ {e}")
        print(f"  verify-docs: {len(errors)} خطا — ناموفق.")
        return 1
    print("  ✓ verify-docs: همهٔ بررسی‌ها سبز.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
