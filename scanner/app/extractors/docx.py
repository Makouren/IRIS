"""
DOCX extractor — handles Word documents.

Uses python-docx for headings, paragraphs, and tables.
Unpacks the DOCX archive to extract embedded images from
word/media/ and runs Tesseract OCR on them.
"""

import logging
import zipfile
import io
from pathlib import Path
from typing import Any

from docx import Document
from docx.table import Table as DocxTable
from PIL import Image

from app.extractors.base import BaseExtractor
from app.ocr import extract_text_with_confidence
from app.schemas import ExtractionResult

logger = logging.getLogger(__name__)

# Image extensions to OCR from word/media/
IMAGE_EXTENSIONS = {".png", ".jpg", ".jpeg", ".bmp", ".tiff", ".tif", ".webp"}


class DocxExtractor(BaseExtractor):
    """Extract text, tables, and embedded image text from DOCX files."""

    SUPPORTED_EXTENSIONS = {".docx"}

    def extract(self, file_path: Path) -> ExtractionResult:
        """
        Extract content from a DOCX file.

        Steps:
          1. Parse headings and paragraph text (python-docx).
          2. Parse tables as list-of-dicts.
          3. Unpack the DOCX ZIP to find word/media/* images → OCR them.
          4. Merge all content into a single ExtractionResult.
        """
        warnings: list[str] = []
        all_text_parts: list[str] = []
        all_raw_data: list[dict[str, Any]] = []
        all_tables: list[list[dict[str, Any]]] = []
        images_text: list[str] = []
        metadata: dict[str, Any] = {"file_type": "docx"}

        # --- Step 1 & 2: Parse document structure ---
        try:
            doc = Document(str(file_path))
        except Exception as e:
            logger.error(f"Failed to open DOCX: {e}")
            return ExtractionResult(
                warnings=[f"Could not open DOCX file: {str(e)}"],
                metadata={"file_type": "docx", "error": True},
            )

        # Extract headings and paragraphs
        paragraph_count = 0
        for para in doc.paragraphs:
            text = para.text.strip()
            if not text:
                continue

            paragraph_count += 1
            style_name = para.style.name if para.style else "Normal"
            is_heading = style_name.lower().startswith("heading")

            all_text_parts.append(text)
            all_raw_data.append({
                "source": f"paragraph_{paragraph_count}",
                "style": style_name,
                "is_heading": is_heading,
                "text": text,
            })

        metadata["paragraph_count"] = paragraph_count

        # Extract tables
        for table_idx, table in enumerate(doc.tables):
            table_data = self._parse_table(table, table_idx)
            if table_data:
                all_tables.append(table_data)
                # Also add to raw_data for structuring
                for row in table_data:
                    row_with_source = {**row, "_source": f"table_{table_idx + 1}"}
                    all_raw_data.append(row_with_source)

        metadata["table_count"] = len(doc.tables)

        # --- Step 3: Extract and OCR embedded images ---
        embedded_images = self._extract_embedded_images(file_path, warnings)
        for img_name, img_text in embedded_images:
            images_text.append(img_text)
            all_raw_data.append({
                "source": f"embedded_image:{img_name}",
                "extraction_method": "ocr",
                "text": img_text,
            })

        metadata["embedded_images_count"] = len(embedded_images)

        raw_text = "\n\n".join(all_text_parts)

        return ExtractionResult(
            raw_data=all_raw_data,
            raw_text=raw_text,
            tables=all_tables,
            images_text=images_text,
            warnings=warnings,
            metadata=metadata,
        )

    def _parse_table(
        self,
        table: DocxTable,
        table_idx: int,
    ) -> list[dict[str, Any]]:
        """
        Parse a DOCX table into a list of row dicts.

        Uses the first row as headers. If the first row looks like data
        (all numeric), generates generic column headers.
        """
        rows = table.rows
        if len(rows) < 2:
            return []

        # Use first row as headers
        headers = [cell.text.strip() for cell in rows[0].cells]

        # Deduplicate headers (DOCX merged cells can produce duplicates)
        seen = {}
        clean_headers = []
        for h in headers:
            if not h:
                h = f"Column_{len(clean_headers)}"
            if h in seen:
                seen[h] += 1
                h = f"{h}_{seen[h]}"
            else:
                seen[h] = 0
            clean_headers.append(h)

        records = []
        for row in rows[1:]:
            cells = [cell.text.strip() for cell in row.cells]
            # Skip empty rows
            if not any(cells):
                continue
            record = {}
            for col_idx, header in enumerate(clean_headers):
                if col_idx < len(cells):
                    record[header] = cells[col_idx]
                else:
                    record[header] = None
            records.append(record)

        return records

    def _extract_embedded_images(
        self,
        file_path: Path,
        warnings: list[str],
    ) -> list[tuple[str, str]]:
        """
        Unpack the DOCX as a ZIP and OCR any images in word/media/.

        Returns:
            List of (image_filename, ocr_text) tuples.
        """
        results: list[tuple[str, str]] = []

        try:
            with zipfile.ZipFile(str(file_path), "r") as z:
                media_files = [
                    name for name in z.namelist()
                    if name.startswith("word/media/")
                    and Path(name).suffix.lower() in IMAGE_EXTENSIONS
                ]

                for media_file in media_files:
                    try:
                        image_data = z.read(media_file)
                        img = Image.open(io.BytesIO(image_data))

                        # Skip tiny images (icons, bullets, etc.)
                        if img.width < 100 or img.height < 100:
                            continue

                        ocr_result = extract_text_with_confidence(img)
                        if ocr_result["text"] and ocr_result["word_count"] >= 3:
                            filename = Path(media_file).name
                            results.append((filename, ocr_result["text"]))

                            if ocr_result["avg_confidence"] < 60:
                                warnings.append(
                                    f"Embedded image '{filename}': OCR confidence is low "
                                    f"({ocr_result['avg_confidence']}%)."
                                )
                    except Exception as e:
                        logger.debug(f"Could not OCR {media_file}: {e}")
        except zipfile.BadZipFile:
            warnings.append("DOCX file appears corrupted (invalid ZIP structure).")
        except Exception as e:
            logger.warning(f"Failed to extract embedded images: {e}")
            warnings.append(f"Could not extract embedded images: {str(e)}")

        return results
