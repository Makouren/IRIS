"""
Tests for PdfExtractor.
"""

from pathlib import Path
import pytest
import fitz  # PyMuPDF

from app.extractors.pdf import PdfExtractor
from app.structurer import structure_extraction


def test_pdf_native_text_extraction(tmp_path: Path):
    """Test extracting native text from a generated PDF file."""
    pdf_path = tmp_path / "test_doc.pdf"

    # Create a simple PDF using PyMuPDF
    doc = fitz.open()
    page = doc.new_page()
    text = (
        "Academic Performance Report\n"
        "Passing Rate: 88.5%\n"
        "Total Enrollees: 1250\n"
        "Retention Rate: 94.2%\n"
        "Faculty Count: 45\n"
    )
    page.insert_text((50, 72), text, fontsize=12)
    doc.save(str(pdf_path))
    doc.close()

    extractor = PdfExtractor()
    result = extractor.extract(pdf_path)

    assert result.metadata["file_type"] == "pdf"
    assert result.metadata["page_count"] == 1
    assert "Passing Rate" in result.raw_text

    fields = structure_extraction(result)
    assert len(fields) >= 2
    field_names = [f.name for f in fields]
    assert any("Passing Rate" in name for name in field_names)
