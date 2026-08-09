#!/usr/bin/env python3
"""Extract NHIS Medicines List 2025 rows from pdftotext -layout output.

Usage:
  pdftotext -layout "docs/superpowers/NHIS/2025 NHIS ML.pdf" /tmp/nhis-ml-2025.txt
  python3 Modules/Insurance/scripts/extract_nhis_ml_2025.py /tmp/nhis-ml-2025.txt \\
      Modules/Insurance/database/data/nhis_medicines_list_2025.csv
"""

from __future__ import annotations

import csv
import re
import sys
from pathlib import Path

FORM_CODES = {
    "CA": "Capsule",
    "CR": "Cream",
    "DT": "Dispersible Tablet",
    "DR": "Drops",
    "ED": "Ear Drops",
    "EL": "Elixir",
    "EO": "Eye Ointment",
    "GA": "Gaseous",
    "GE": "Gel",
    "ID": "Eye Drops",
    "IN": "Injection",
    "LI": "Liquid",
    "LO": "Lotion",
    "MI": "Mixture",
    "MW": "Mouth Wash",
    "OG": "Oral Gel",
    "OI": "Ointment",
    "PO": "Powder/Granules",
    "RE": "Suppository",
    "RS": "Rectal Solution",
    "SH": "Shampoo",
    "SP": "Spray",
    "SU": "Suspension",
    "SY": "Syrup",
    "TA": "Tablet",
    "VC": "Vaginal Cream",
    "VP": "Vaginal Pessary",
    "ND": "Nasal Drops",
}

UNIT_TOKENS = {
    "ampoule",
    "vial",
    "tablet",
    "capsule",
    "sachet",
    "supp",
    "pessary",
    "course",
    "1 course",
}

FIELDS = [
    "code",
    "name",
    "strength",
    "form",
    "unit_of_pricing",
    "price",
    "prescribing_level_code",
    "effective_from",
    "is_active",
]


def is_noise(line: str) -> bool:
    return (
        not line
        or line.startswith("NHIS Medicines")
        or line.startswith("CODE")
        or line.startswith("GENERIC")
        or line.startswith("UNIT OF")
        or line.startswith("PRICING")
        or line.startswith("Copyright")
        or line.startswith("10.0 List of Medicines")
        or "Page " in line
    )


def is_unit(value: str) -> bool:
    value = value.strip()
    if not value:
        return False
    if value.lower() in UNIT_TOKENS:
        return True

    return bool(re.fullmatch(r"[\d.]+\s*(?:mL|ml|G|g|mg)", value, re.I))


def extract(text: str) -> list[dict[str, str]]:
    starts = [match.start() for match in re.finditer(r"10\.0 List of Medicines and Prices", text)]
    chunk = text[starts[-1] :] if starts else text
    lines = chunk.splitlines()
    rows: list[dict[str, str]] = []
    index = 0

    while index < len(lines):
        match = re.match(r"^([A-Z0-9]{9})\s*(.*)$", lines[index])
        if not match:
            index += 1
            continue

        code = match.group(1)
        rest = match.group(2).rstrip()

        leading: list[str] = []
        lookback = index - 1
        while lookback >= 0 and len(leading) < 3:
            previous = lines[lookback].strip()
            if is_noise(previous):
                lookback -= 1
                continue
            if re.match(r"^[A-Z0-9]{9}\b", previous):
                break
            if re.search(r"([\d]+(?:\.\d+)?)\s+(SM|B1|B2|[AMCD])\s*$", previous):
                break
            leading.insert(0, re.sub(r"\s+", " ", previous))
            lookback -= 1

        name_parts = list(leading)
        unit = ""
        parts = [part for part in re.split(r"\s{2,}", rest.strip()) if part] if rest.strip() else []

        if len(parts) >= 2 and is_unit(parts[-1]):
            unit = parts[-1].strip()
            if parts[:-1]:
                name_parts.append(" ".join(parts[:-1]).strip())
        elif len(parts) == 1:
            if is_unit(parts[0]):
                unit = parts[0].strip()
            else:
                name_parts.append(parts[0].strip())
        elif parts:
            if is_unit(parts[-1]) or len(parts[-1]) <= 16:
                unit = parts[-1].strip()
                if parts[:-1]:
                    name_parts.append(" ".join(parts[:-1]).strip())
            else:
                name_parts.append(" ".join(parts).strip())

        price: float | None = None
        level: str | None = None
        cursor = index + 1
        while cursor < len(lines) and cursor <= index + 6:
            line = lines[cursor].strip()
            if is_noise(line):
                cursor += 1
                continue
            if re.match(r"^[A-Z0-9]{9}\b", line):
                break

            price_match = re.search(r"([\d]+(?:\.\d+)?)\s+(SM|B1|B2|[AMCD])\s*$", line)
            if price_match:
                price = float(price_match.group(1))
                level = price_match.group(2)
                before = line[: price_match.start()].strip()
                if before:
                    if is_unit(before) and not unit:
                        unit = before
                    else:
                        name_parts.append(re.sub(r"\s+", " ", before))
                cursor += 1
                break

            name_parts.append(re.sub(r"\s+", " ", line))
            cursor += 1

        if price is None or level is None:
            raise RuntimeError(f"Failed to parse price/level for {code}")

        full_name = re.sub(r"\s+", " ", " ".join(part for part in name_parts if part)).strip(" ,")
        form = FORM_CODES.get(code[6:8], code[6:8])
        strength = ""
        display = full_name

        if "," in full_name:
            left, right = full_name.rsplit(",", 1)
            right = right.strip()
            if re.search(r"\d", right):
                strength = right
                display = left.strip()

        for form_word in sorted(set(FORM_CODES.values()) | {"Granular Powder", "Infusion"}, key=len, reverse=True):
            if re.search(rf"\b{re.escape(form_word)}$", display, re.I):
                display = re.sub(rf"\s*{re.escape(form_word)}\s*$", "", display, flags=re.I).strip(" ,")
                break

        if not unit and re.search(r"\bcourse\b", full_name, re.I):
            unit = "1 Course"

        rows.append(
            {
                "code": code,
                "name": display or full_name or code,
                "strength": strength,
                "form": form,
                "unit_of_pricing": unit,
                "price": f"{price:.2f}",
                "prescribing_level_code": level,
                "effective_from": "2025-03-01",
                "is_active": "1",
            }
        )
        index = max(cursor, index + 1)

    return rows


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__, file=sys.stderr)
        return 1

    source = Path(sys.argv[1])
    destination = Path(sys.argv[2])
    rows = extract(source.read_text())

    if len(rows) != 551:
        raise SystemExit(f"Expected 551 medicines, got {len(rows)}")

    by_code = {row["code"]: row for row in rows}
    if by_code["PARACETA1"]["price"] != "0.12" or by_code["PARACETA1"]["prescribing_level_code"] != "A":
        raise SystemExit("Spot check failed for PARACETA1")
    if by_code["AMOARTTA1"]["price"] != "5.15" or by_code["AMOARTTA1"]["unit_of_pricing"] != "1 Course":
        raise SystemExit("Spot check failed for AMOARTTA1")
    if by_code["5FLUORIN1"]["price"] != "14.47":
        raise SystemExit("Spot check failed for 5FLUORIN1")

    destination.parent.mkdir(parents=True, exist_ok=True)
    with destination.open("w", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS)
        writer.writeheader()
        writer.writerows(rows)

    print(f"Wrote {len(rows)} medicines to {destination}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
