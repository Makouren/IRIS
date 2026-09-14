"""
Image extractor — handles standalone image files (PNG, JPG, WEBP).

Runs Tesseract OCR directly on the image to extract visible text.
"""

import logging
from pathlib import Path

from PIL import Image

from app.extractors.base import BaseExtractor
from app.ocr import extract_text_with_confidence
from app.schemas import ExtractionResult

logger = logging.getLogger(__name__)


class ImageExtractor(BaseExtractor):
    """Extract text from standalone images via OCR."""

    SUPPORTED_EXTENSIONS = {".png", ".jpg", ".jpeg", ".webp"}

    def extract(self, file_path: Path) -> ExtractionResult:
        """
        Run OCR on a standalone image file.

        Returns:
            ExtractionResult with extracted text and confidence metadata.
        """
        warnings: list[str] = []
        metadata: dict = {
            "file_type": file_path.suffix.lstrip(".").lower(),
        }

        try:
            img = Image.open(file_path)
            metadata["image_size"] = {"width": img.width, "height": img.height}
            metadata["image_mode"] = img.mode
        except Exception as e:
            logger.error(f"Failed to open image: {e}")
            return ExtractionResult(
                warnings=[f"Could not open image file: {str(e)}"],
                metadata={**metadata, "error": True},
            )

        # Run OCR with confidence scoring
        try:
            ocr_result = extract_text_with_confidence(img)
        except Exception as e:
            logger.error(f"OCR failed on image: {e}")
            return ExtractionResult(
                warnings=[f"OCR failed: {str(e)}"],
                metadata={**metadata, "error": True},
            )

        text = ocr_result["text"]
        avg_confidence = ocr_result["avg_confidence"]
        word_count = ocr_result["word_count"]

        metadata["ocr_confidence"] = avg_confidence
        metadata["word_count"] = word_count

        if not text:
            warnings.append(
                "No text could be extracted from the image. "
                "The image may not contain readable text."
            )
        elif avg_confidence < 60:
            warnings.append(
                f"OCR confidence is low ({avg_confidence}%). "
                "Extracted text may contain errors."
            )

        raw_data = []
        if text:
            raw_data.append({
                "source": "image_ocr",
                "extraction_method": "ocr",
                "text": text,
                "ocr_confidence": avg_confidence,
            })

        return ExtractionResult(
            raw_data=raw_data,
            raw_text=text,
            warnings=warnings,
            metadata=metadata,
        )
