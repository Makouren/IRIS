"""
Extractors package — one extractor per supported file format.

Each extractor implements BaseExtractor and returns an ExtractionResult.
"""

from app.extractors.spreadsheet import SpreadsheetExtractor
from app.extractors.pdf import PdfExtractor
from app.extractors.docx import DocxExtractor
from app.extractors.image import ImageExtractor

__all__ = [
    "SpreadsheetExtractor",
    "PdfExtractor",
    "DocxExtractor",
    "ImageExtractor",
]
