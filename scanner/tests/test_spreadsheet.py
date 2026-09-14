"""
Tests for SpreadsheetExtractor (CSV and Excel formats).
"""

from pathlib import Path
import pytest
from app.extractors.spreadsheet import SpreadsheetExtractor
from app.structurer import structure_extraction
from app.chart_suggestor import suggest_charts
from app.dashboard_builder import build_dashboard_widgets


def test_csv_extraction(tmp_path: Path):
    """Test extracting structured metrics from a CSV file."""
    csv_file = tmp_path / "test_data.csv"
    csv_file.write_text(
        "Program,Score,Passing Rate\n"
        "BSIT,85.5,90%\n"
        "BSCS,90.2,95%\n"
        "BSBA,78.1,82%\n",
        encoding="utf-8",
    )

    extractor = SpreadsheetExtractor()
    result = extractor.extract(csv_file)

    assert len(result.raw_data) == 3
    assert result.metadata["sheet_count"] == 1
    assert result.metadata["total_rows"] == 3

    # Structure into ExtractedFields
    fields = structure_extraction(result)
    assert len(fields) > 0

    # Verify chart suggestions
    suggestions = suggest_charts(fields)
    assert len(suggestions) > 0
    assert suggestions[0].chart_type in {"bar", "line", "pie", "scatter", "radar"}
    assert "series" in suggestions[0].echarts_option

    # Verify dashboard widgets
    widgets = build_dashboard_widgets(fields, suggestions)
    assert len(widgets) == len(suggestions)
    assert widgets[0].suggested_title
    assert widgets[0].echarts_option_json
    assert isinstance(widgets[0].data_table, list)
