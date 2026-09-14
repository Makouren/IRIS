"""
Structurer — converts raw extraction output into structured ExtractedField lists.

Handles two cases:
  1. Spreadsheet data (already structured) — maps columns to fields.
  2. Text-based data (PDF, DOCX, image OCR) — uses regex/heuristic
     patterns to identify label:value pairs, optionally enhanced by
     Gemini for complex layouts.
"""

import logging
import re
from typing import Any, Optional

from app.config import settings
from app.schemas import ExtractedField, ExtractionResult

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Regex patterns for extracting label:value pairs from unstructured text
# ---------------------------------------------------------------------------

# Matches patterns like "Passing Rate: 85.5%" or "Students - 1,234"
LABEL_VALUE_PATTERN = re.compile(
    r"(?P<label>[A-Za-z][A-Za-z0-9 /&\-]{2,50})"  # label (starts with letter)
    r"\s*[:=\-–—]\s*"                                # separator
    r"(?P<value>\d[\d,]*\.?\d*)"                     # numeric value
    r"\s*(?P<unit>[%A-Za-z]*)",                      # optional unit
    re.MULTILINE,
)

# Matches percentage patterns like "85.5%" or "92%"
PERCENTAGE_PATTERN = re.compile(r"(\d+\.?\d*)\s*%")

# Matches year patterns like "2020", "2021-2022"
YEAR_PATTERN = re.compile(r"\b(20\d{2})\b")


def structure_extraction(result: ExtractionResult) -> list[ExtractedField]:
    """
    Convert an ExtractionResult into a list of ExtractedField objects.

    Dispatches to the appropriate structuring strategy based on
    whether the data is already tabular (spreadsheets) or needs
    text parsing (PDFs, DOCX, images).

    Args:
        result: Raw extraction output from an extractor.

    Returns:
        List of ExtractedField objects with names, values, units, and sources.
    """
    fields: list[ExtractedField] = []

    # Strategy 1: Tabular data (spreadsheets, DOCX tables)
    if result.raw_data and _is_tabular_data(result.raw_data):
        fields.extend(_structure_tabular(result.raw_data))

    # Strategy 2: Table structures extracted separately
    for table in result.tables:
        if table:
            fields.extend(_structure_tabular(table))

    # Strategy 3: Text-based extraction (OCR, PDF text)
    if result.raw_text:
        text_fields = _structure_from_text(result.raw_text)
        fields.extend(text_fields)

    # Strategy 4: Text from embedded images
    for img_text in result.images_text:
        if img_text:
            img_fields = _structure_from_text(img_text, source_prefix="embedded_image")
            fields.extend(img_fields)

    # Strategy 5: If Gemini is available and we have text, try AI-based structuring
    if settings.gemini_enabled and result.raw_text and len(fields) < 3:
        gemini_fields = _structure_with_gemini(result.raw_text)
        if gemini_fields:
            fields.extend(gemini_fields)

    # Deduplicate fields by (name, value)
    fields = _deduplicate_fields(fields)

    return fields


def _is_tabular_data(raw_data: list[dict]) -> bool:
    """Check if raw_data looks like structured table rows (from spreadsheets)."""
    if not raw_data:
        return False
    # If rows have consistent keys and more than just _source/text, it's tabular
    first_row = raw_data[0]
    data_keys = [k for k in first_row.keys() if k not in ("_source", "source", "extraction_method")]
    return len(data_keys) >= 2


def _structure_tabular(rows: list[dict[str, Any]]) -> list[ExtractedField]:
    """
    Convert tabular rows (from spreadsheets or DOCX tables) into ExtractedField list.

    For each column that contains numeric values, creates one ExtractedField
    per row. Infers units from column header text.
    """
    if not rows:
        return []

    fields: list[ExtractedField] = []

    # Identify columns
    skip_keys = {"_source", "source", "extraction_method", "text", "style",
                 "is_heading", "ocr_confidence", "page"}
    sample_row = rows[0]
    columns = [k for k in sample_row.keys() if k not in skip_keys]

    for row in rows:
        source = row.get("_source", row.get("source", "unknown"))
        for col in columns:
            value = row.get(col)
            if value is None:
                continue

            # Try to convert to number
            numeric_value = _try_numeric(value)
            unit = _infer_unit(col, value)

            fields.append(ExtractedField(
                name=col,
                value=numeric_value if numeric_value is not None else str(value),
                unit=unit,
                source=str(source),
            ))

    return fields


def _structure_from_text(
    text: str,
    source_prefix: str = "text",
) -> list[ExtractedField]:
    """
    Extract label:value pairs from unstructured text using regex patterns.

    Looks for patterns like:
      - "Passing Rate: 85.5%"
      - "Students = 1,234"
      - "Score - 92"
    """
    fields: list[ExtractedField] = []
    seen: set[tuple[str, str]] = set()

    for match in LABEL_VALUE_PATTERN.finditer(text):
        label = match.group("label").strip()
        value_str = match.group("value").replace(",", "")
        unit = match.group("unit").strip() or None

        # Infer percentage unit
        if unit == "%" or "percent" in label.lower() or "rate" in label.lower():
            unit = "%"

        # Convert value
        numeric_value = _try_numeric(value_str)
        final_value = numeric_value if numeric_value is not None else value_str

        # Deduplicate within this text block
        key = (label.lower(), str(final_value))
        if key in seen:
            continue
        seen.add(key)

        fields.append(ExtractedField(
            name=label,
            value=final_value,
            unit=unit,
            source=source_prefix,
        ))

    return fields


def _structure_with_gemini(text: str) -> list[ExtractedField]:
    """
    Use Google Gemini to extract structured fields from unstructured text.

    Only called when:
      - GEMINI_API_KEY is set
      - Simple regex extraction yielded few results (< 3 fields)

    Returns:
        List of ExtractedField objects, or empty list if Gemini fails.
    """
    try:
        import google.generativeai as genai

        genai.configure(api_key=settings.gemini_api_key)
        model = genai.GenerativeModel("gemini-2.0-flash")

        prompt = f"""Extract all data fields from the following text. Return ONLY valid JSON — no markdown, no explanation.

Format: a JSON array of objects, each with:
- "name": field label (string)
- "value": extracted value (number or string)
- "unit": unit if identifiable (string or null)

Text:
---
{text[:4000]}
---

Return ONLY the JSON array:"""

        response = model.generate_content(prompt)
        response_text = response.text.strip()

        # Clean up response (remove markdown code fences if present)
        if response_text.startswith("```"):
            response_text = response_text.split("\n", 1)[1]
            if response_text.endswith("```"):
                response_text = response_text[:-3]
            response_text = response_text.strip()

        import json
        data = json.loads(response_text)

        fields = []
        for item in data:
            if "name" in item and "value" in item:
                fields.append(ExtractedField(
                    name=str(item["name"]),
                    value=item["value"],
                    unit=item.get("unit"),
                    source="gemini_extraction",
                ))

        logger.info(f"Gemini extracted {len(fields)} fields from text")
        return fields

    except ImportError:
        logger.warning("google-generativeai package not installed")
        return []
    except Exception as e:
        logger.warning(f"Gemini extraction failed: {e}")
        return []


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _try_numeric(value: Any) -> Optional[float | int]:
    """Try to convert a value to a number."""
    if isinstance(value, (int, float)):
        return value
    if isinstance(value, str):
        clean = value.strip().replace(",", "").rstrip("%")
        try:
            num = float(clean)
            return int(num) if num == int(num) else num
        except (ValueError, TypeError):
            return None
    return None


def _infer_unit(column_name: str, value: Any) -> Optional[str]:
    """Infer the unit from a column name or value format."""
    col_lower = column_name.lower()

    # Check column name for common unit indicators
    if any(kw in col_lower for kw in ["percent", "rate", "ratio", "pct"]):
        return "%"
    if any(kw in col_lower for kw in ["count", "number", "total", "enrollment"]):
        return "count"
    if "peso" in col_lower or "php" in col_lower or "budget" in col_lower:
        return "PHP"

    # Check if value is formatted as percentage
    if isinstance(value, str) and value.strip().endswith("%"):
        return "%"

    return None


def _deduplicate_fields(fields: list[ExtractedField]) -> list[ExtractedField]:
    """Remove duplicate fields (same name + value combination)."""
    seen: set[tuple[str, str]] = set()
    unique: list[ExtractedField] = []

    for field in fields:
        key = (field.name.lower().strip(), str(field.value))
        if key not in seen:
            seen.add(key)
            unique.append(field)

    return unique
