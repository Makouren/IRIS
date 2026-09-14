"""
Abstract base extractor.

All format-specific extractors inherit from BaseExtractor and implement
the extract() method.
"""

from abc import ABC, abstractmethod
from pathlib import Path

from app.schemas import ExtractionResult


class BaseExtractor(ABC):
    """
    Abstract base class for file extractors.

    Each subclass handles one file format (spreadsheet, PDF, DOCX, image)
    and returns a standardized ExtractionResult.
    """

    @abstractmethod
    def extract(self, file_path: Path) -> ExtractionResult:
        """
        Extract structured data from a file.

        Args:
            file_path: Path to the uploaded file on disk.

        Returns:
            ExtractionResult with raw data, text, tables, and metadata.
        """
        ...

    @staticmethod
    def _safe_str(value) -> str:
        """Safely convert a value to string, handling None and NaN."""
        if value is None:
            return ""
        try:
            import math
            if isinstance(value, float) and math.isnan(value):
                return ""
        except (TypeError, ValueError):
            pass
        return str(value)
