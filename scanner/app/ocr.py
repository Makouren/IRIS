"""
Shared OCR utility — thin wrapper around pytesseract.

Used by the PDF, DOCX, and image extractors to OCR embedded
or standalone images via Tesseract.
"""

import logging
from pathlib import Path
from typing import Optional, Union

import pytesseract
from PIL import Image

from app.config import settings

logger = logging.getLogger(__name__)


def _configure_tesseract() -> None:
    """Set the Tesseract command path from config if provided."""
    if settings.tesseract_cmd:
        pytesseract.pytesseract.tesseract_cmd = settings.tesseract_cmd


def extract_text(
    image: Union[str, Path, Image.Image],
    lang: str = "eng",
) -> str:
    """
    Extract text from an image using Tesseract OCR.

    Args:
        image: A file path (str/Path) or a PIL Image object.
        lang: Tesseract language code (default: English).

    Returns:
        Extracted text as a string.
    """
    _configure_tesseract()

    if isinstance(image, (str, Path)):
        image = Image.open(image)

    try:
        text = pytesseract.image_to_string(image, lang=lang)
        return text.strip()
    except pytesseract.TesseractNotFoundError:
        logger.error(
            "Tesseract is not installed or not in PATH. "
            "Install it from https://github.com/UB-Mannheim/tesseract/wiki "
            "or set TESSERACT_CMD in your .env file."
        )
        raise
    except Exception as e:
        logger.error(f"OCR failed: {e}")
        raise


def extract_text_with_confidence(
    image: Union[str, Path, Image.Image],
    lang: str = "eng",
) -> dict:
    """
    Extract text from an image with per-word confidence scores.

    Args:
        image: A file path (str/Path) or a PIL Image object.
        lang: Tesseract language code (default: English).

    Returns:
        Dict with keys:
            - text: Full extracted text.
            - avg_confidence: Average confidence score (0-100).
            - word_count: Number of words detected.
    """
    _configure_tesseract()

    if isinstance(image, (str, Path)):
        image = Image.open(image)

    try:
        data = pytesseract.image_to_data(image, lang=lang, output_type=pytesseract.Output.DICT)

        # Filter out empty entries and compute average confidence
        confidences = [
            int(conf)
            for conf, text in zip(data["conf"], data["text"])
            if text.strip() and int(conf) > -1
        ]

        full_text = " ".join(
            word for word in data["text"] if word.strip()
        )

        avg_confidence = sum(confidences) / len(confidences) if confidences else 0.0

        return {
            "text": full_text.strip(),
            "avg_confidence": round(avg_confidence, 1),
            "word_count": len(confidences),
        }
    except pytesseract.TesseractNotFoundError:
        logger.error(
            "Tesseract is not installed or not in PATH. "
            "Install it from https://github.com/UB-Mannheim/tesseract/wiki "
            "or set TESSERACT_CMD in your .env file."
        )
        raise
    except Exception as e:
        logger.error(f"OCR with confidence failed: {e}")
        raise


def is_tesseract_available() -> bool:
    """Check whether Tesseract OCR is installed and accessible."""
    _configure_tesseract()
    try:
        pytesseract.get_tesseract_version()
        return True
    except pytesseract.TesseractNotFoundError:
        return False
