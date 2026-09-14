"""
PDF extractor — handles native-text and scanned/image-based PDFs.

Uses PyMuPDF (fitz) for text and image extraction. Falls back to
Tesseract OCR for pages with little or no selectable text.
"""

import io
import logging
from pathlib import Path
from typing import Any

import fitz  # PyMuPDF
from PIL import Image

from app.extractors.base import BaseExtractor
from app.ocr import extract_text_with_confidence
from app.schemas import ExtractionResult

logger = logging.getLogger(__name__)

# Minimum character count per page to consider it "has selectable text"
MIN_TEXT_LENGTH = 50


class PdfExtractor(BaseExtractor):
    """Extract text and data from PDF files (native text + OCR fallback)."""

    SUPPORTED_EXTENSIONS = {".pdf"}

    def extract(self, file_path: Path) -> ExtractionResult:
        """
        Extract content from a PDF file.

        Strategy per page:
          1. Try to extract selectable text via PyMuPDF.
          2. If text is too short (< MIN_TEXT_LENGTH chars), treat the page
             as image-based: render it as a bitmap and OCR it.
          3. Also extract embedded images and OCR them separately.

        Returns:
            ExtractionResult with merged text from all sources.
        """
        warnings: list[str] = []
        all_text_parts: list[str] = []
        all_raw_data: list[dict[str, Any]] = []
        images_text: list[str] = []
        metadata: dict[str, Any] = {"file_type": "pdf"}

        try:
            doc = fitz.open(str(file_path))
        except Exception as e:
            logger.error(f"Failed to open PDF: {e}")
            return ExtractionResult(
                warnings=[f"Could not open PDF file: {str(e)}"],
                metadata={"file_type": "pdf", "error": True},
            )

        metadata["page_count"] = len(doc)
        ocr_pages: list[int] = []

        for page_num in range(len(doc)):
            page = doc[page_num]
            page_label = f"page_{page_num + 1}"

            # --- Step 1: Try native text extraction ---
            native_text = page.get_text("text").strip()

            if len(native_text) >= MIN_TEXT_LENGTH:
                # Page has sufficient selectable text
                all_text_parts.append(f"--- {page_label} ---\n{native_text}")
                all_raw_data.append({
                    "page": page_num + 1,
                    "source": page_label,
                    "extraction_method": "native_text",
                    "text": native_text,
                })
            else:
                # --- Step 2: OCR the page as a rendered image ---
                ocr_pages.append(page_num + 1)
                try:
                    pix = page.get_pixmap(dpi=300)
                    img = Image.open(io.BytesIO(pix.tobytes("png")))
                    ocr_result = extract_text_with_confidence(img)

                    if ocr_result["text"]:
                        all_text_parts.append(
                            f"--- {page_label} (OCR) ---\n{ocr_result['text']}"
                        )
                        all_raw_data.append({
                            "page": page_num + 1,
                            "source": page_label,
                            "extraction_method": "ocr",
                            "text": ocr_result["text"],
                            "ocr_confidence": ocr_result["avg_confidence"],
                        })

                        if ocr_result["avg_confidence"] < 60:
                            warnings.append(
                                f"{page_label}: OCR confidence is low "
                                f"({ocr_result['avg_confidence']}%). "
                                "Extracted text may be inaccurate."
                            )
                    else:
                        warnings.append(
                            f"{page_label}: No text could be extracted "
                            "(page may be blank or contain only graphics)."
                        )
                except Exception as e:
                    logger.warning(f"OCR failed for {page_label}: {e}")
                    warnings.append(f"{page_label}: OCR failed — {str(e)}")

            # --- Step 3: Extract embedded images and OCR them ---
            try:
                image_list = page.get_images(full=True)
                for img_idx, img_info in enumerate(image_list):
                    xref = img_info[0]
                    try:
                        base_image = doc.extract_image(xref)
                        image_bytes = base_image["image"]
                        img = Image.open(io.BytesIO(image_bytes))

                        # Only OCR images of reasonable size (skip tiny icons)
                        if img.width >= 100 and img.height >= 100:
                            ocr_result = extract_text_with_confidence(img)
                            if ocr_result["text"] and ocr_result["word_count"] >= 3:
                                images_text.append(ocr_result["text"])
                    except Exception as e:
                        logger.debug(
                            f"Could not process embedded image {img_idx} "
                            f"on {page_label}: {e}"
                        )
            except Exception as e:
                logger.debug(f"Could not extract images from {page_label}: {e}")

        doc.close()

        if ocr_pages:
            metadata["ocr_pages"] = ocr_pages

        raw_text = "\n\n".join(all_text_parts)

        return ExtractionResult(
            raw_data=all_raw_data,
            raw_text=raw_text,
            images_text=images_text,
            warnings=warnings,
            metadata=metadata,
        )
