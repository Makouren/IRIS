"""
Spreadsheet extractor — handles XLSX, XLS, and CSV files.

Uses pandas + openpyxl for Excel formats and pandas for CSV.
No AI/OCR needed — spreadsheet data is already structured.
"""

import logging
from pathlib import Path
from typing import Any

import pandas as pd

from app.extractors.base import BaseExtractor
from app.schemas import ExtractionResult

logger = logging.getLogger(__name__)


class SpreadsheetExtractor(BaseExtractor):
    """Extract structured data from spreadsheet files (XLSX, XLS, CSV)."""

    # Extensions this extractor handles
    SUPPORTED_EXTENSIONS = {".xlsx", ".xls", ".csv"}

    def extract(self, file_path: Path) -> ExtractionResult:
        """
        Parse a spreadsheet file and extract all sheet data.

        For Excel files: reads every sheet individually.
        For CSV files: reads as a single-sheet workbook.

        Returns:
            ExtractionResult with row data in raw_data and tables,
            plus metadata about sheets and dimensions.
        """
        ext = file_path.suffix.lower()
        warnings: list[str] = []
        all_raw_data: list[dict[str, Any]] = []
        all_tables: list[list[dict[str, Any]]] = []
        metadata: dict[str, Any] = {"file_type": ext.lstrip(".")}

        if ext == ".csv":
            sheet_data = self._read_csv(file_path, warnings)
            all_raw_data.extend(sheet_data)
            all_tables.append(sheet_data)
            metadata["sheet_names"] = ["Sheet1"]
            metadata["sheet_count"] = 1
        else:
            sheet_names = self._get_sheet_names(file_path)
            metadata["sheet_names"] = sheet_names
            metadata["sheet_count"] = len(sheet_names)

            for sheet_name in sheet_names:
                sheet_data = self._read_excel_sheet(file_path, sheet_name, warnings)
                all_raw_data.extend(sheet_data)
                all_tables.append(sheet_data)

        metadata["total_rows"] = len(all_raw_data)

        return ExtractionResult(
            raw_data=all_raw_data,
            tables=all_tables,
            warnings=warnings,
            metadata=metadata,
        )

    def _get_sheet_names(self, file_path: Path) -> list[str]:
        """Get all sheet names from an Excel file."""
        try:
            xl = pd.ExcelFile(file_path, engine="openpyxl")
            return xl.sheet_names
        except Exception as e:
            logger.warning(f"Could not read sheet names with openpyxl, trying xlrd: {e}")
            try:
                xl = pd.ExcelFile(file_path)
                return xl.sheet_names
            except Exception as e2:
                logger.error(f"Failed to read sheet names: {e2}")
                return ["Sheet1"]

    def _read_excel_sheet(
        self,
        file_path: Path,
        sheet_name: str,
        warnings: list[str],
    ) -> list[dict[str, Any]]:
        """Read a single Excel sheet into a list of row dicts."""
        try:
            df = pd.read_excel(
                file_path,
                sheet_name=sheet_name,
                engine="openpyxl",
                header=0,
            )
            return self._dataframe_to_records(df, sheet_name, warnings)
        except Exception as e:
            logger.warning(f"Failed to read sheet '{sheet_name}': {e}")
            warnings.append(f"Could not read sheet '{sheet_name}': {str(e)}")
            return []

    def _read_csv(
        self,
        file_path: Path,
        warnings: list[str],
    ) -> list[dict[str, Any]]:
        """Read a CSV file into a list of row dicts."""
        try:
            # Try common encodings
            for encoding in ["utf-8", "latin-1", "cp1252"]:
                try:
                    df = pd.read_csv(file_path, encoding=encoding, header=0)
                    return self._dataframe_to_records(df, "Sheet1", warnings)
                except UnicodeDecodeError:
                    continue

            # Fallback: read with error handling
            df = pd.read_csv(file_path, encoding="utf-8", errors="replace", header=0)
            warnings.append("File encoding was uncertain; some characters may be garbled.")
            return self._dataframe_to_records(df, "Sheet1", warnings)
        except Exception as e:
            logger.error(f"Failed to read CSV: {e}")
            warnings.append(f"Could not read CSV file: {str(e)}")
            return []

    def _dataframe_to_records(
        self,
        df: pd.DataFrame,
        sheet_name: str,
        warnings: list[str],
    ) -> list[dict[str, Any]]:
        """
        Convert a pandas DataFrame to a list of row dicts with source tracking.

        Drops fully empty rows and columns. Adds a _source field to each row
        indicating the sheet name and row number.
        """
        # Drop fully empty rows and columns
        df = df.dropna(how="all", axis=0).dropna(how="all", axis=1)

        if df.empty:
            warnings.append(f"Sheet '{sheet_name}' is empty after removing blank rows/columns.")
            return []

        # Clean column names
        df.columns = [
            str(col).strip() if pd.notna(col) else f"Column_{i}"
            for i, col in enumerate(df.columns)
        ]

        records = []
        for row_idx, row in df.iterrows():
            record = {}
            for col in df.columns:
                value = row[col]
                # Convert numpy types to native Python types
                if pd.isna(value):
                    record[col] = None
                elif hasattr(value, "item"):
                    record[col] = value.item()
                else:
                    record[col] = value
            # Track source location
            record["_source"] = f"{sheet_name}!Row{int(row_idx) + 2}"  # +2: header + 0-index
            records.append(record)

        return records
