"""
Tests for ImageExtractor.
"""

from pathlib import Path
from unittest.mock import patch
from PIL import Image
import pytest

from app.extractors.image import ImageExtractor
from app.structurer import structure_extraction


def test_image_extraction_with_mocked_ocr(tmp_path: Path):
    """Test image extraction with mocked OCR response."""
    img_path = tmp_path / "test_chart.png"
    img = Image.new("RGB", (200, 200), color="white")
    img.save(img_path)

    mock_ocr_output = {
        "text": "Passing Rate: 88.5%\nRetention Rate: 92.0%",
        "avg_confidence": 85.0,
        "word_count": 6,
        "words": [],
    }

    with patch("app.extractors.image.extract_text_with_confidence", return_value=mock_ocr_output):
        extractor = ImageExtractor()
        result = extractor.extract(img_path)

        assert result.metadata["file_type"] == "png"
        assert result.metadata["ocr_confidence"] == 85.0
        assert "Passing Rate" in result.raw_text

        fields = structure_extraction(result)
        assert len(fields) >= 2
