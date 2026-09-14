"""
Pydantic models defining the request/response contract for the scanner API.

These schemas define the structure of data flowing through the pipeline:
  File Upload → ExtractionResult → ExtractedField → ChartSuggestion → DashboardWidget → ScanResponse
"""

from pydantic import BaseModel, Field
from typing import Optional, Any


# ---------------------------------------------------------------------------
# Extraction output
# ---------------------------------------------------------------------------

class ExtractedField(BaseModel):
    """A single extracted field (label + value + optional unit)."""

    name: str = Field(..., description="Field label, e.g., 'Passing Rate'")
    value: str | float | int = Field(..., description="Extracted value, e.g., 85.5")
    unit: Optional[str] = Field(None, description="Unit if identifiable, e.g., '%', 'students'")
    source: str = Field(
        ...,
        description="Where in the document this was found, e.g., 'Sheet1!B2', 'page_1', 'paragraph_3'",
    )


class ExtractionResult(BaseModel):
    """Raw output from an extractor before structuring."""

    raw_data: list[dict[str, Any]] = Field(
        default_factory=list,
        description="Raw structured data extracted from the file (rows, key-value pairs, etc.)",
    )
    raw_text: str = Field(
        default="",
        description="Raw concatenated text (for text-based extractions like OCR)",
    )
    tables: list[list[dict[str, Any]]] = Field(
        default_factory=list,
        description="Tables extracted from the document, each as a list of row dicts",
    )
    images_text: list[str] = Field(
        default_factory=list,
        description="Text extracted from embedded images via OCR",
    )
    warnings: list[str] = Field(
        default_factory=list,
        description="Any warnings generated during extraction",
    )
    metadata: dict[str, Any] = Field(
        default_factory=dict,
        description="File-level metadata (page count, sheet names, etc.)",
    )


# ---------------------------------------------------------------------------
# Chart suggestion
# ---------------------------------------------------------------------------

class ChartSuggestion(BaseModel):
    """A suggested Apache ECharts visualization for a group of extracted fields."""

    chart_type: str = Field(
        ...,
        description="Apache ECharts series type: 'bar', 'line', 'pie', 'scatter', or 'radar'",
    )
    echarts_series_type: str = Field(
        ...,
        description="Direct ECharts series.type value, e.g., 'bar'",
    )
    field_group: str = Field(
        ...,
        description="Label for the grouping, e.g., 'QAO Scores by Program'",
    )
    x_axis: Optional[str] = Field(
        None,
        description="Suggested xAxis field name (None for pie charts)",
    )
    y_axis: list[str] = Field(
        default_factory=list,
        description="Suggested yAxis / value field names",
    )
    fields: list[str] = Field(
        default_factory=list,
        description="Names of ExtractedFields used in this suggestion",
    )
    echarts_option: dict[str, Any] = Field(
        default_factory=dict,
        description="Complete Apache ECharts option config ready for echarts.setOption()",
    )


# ---------------------------------------------------------------------------
# Dashboard-ready widget
# ---------------------------------------------------------------------------

class DashboardWidget(BaseModel):
    """
    A pre-built dashboard widget payload that the Laravel admin panel
    can consume directly for review, editing, and publishing.
    """

    suggested_title: str = Field(
        ...,
        description="Suggested chart title, e.g., 'QAO Scores by Program'",
    )
    suggested_description: str = Field(
        ...,
        description="Brief summary of what the chart shows",
    )
    suggested_section: str = Field(
        default="General",
        description="Suggested dashboard section/category, e.g., 'Academic Quality'",
    )
    display_order: Optional[int] = Field(
        None,
        description="Suggested display position (null = let admin decide)",
    )
    echarts_option_json: str = Field(
        ...,
        description="JSON string of the ECharts option, ready to store in the database",
    )
    data_table: list[dict[str, Any]] = Field(
        default_factory=list,
        description="Tabular data for admin review, e.g., [{'Program': 'BSIT', 'Score': 85.5}]",
    )


# ---------------------------------------------------------------------------
# API response
# ---------------------------------------------------------------------------

class ScanResponse(BaseModel):
    """Top-level response from POST /scan-file."""

    status: str = Field(
        default="draft",
        description="Always 'draft' — indicates this is pending admin review",
    )
    filename: str = Field(..., description="Original uploaded filename")
    file_type: str = Field(..., description="Detected file type, e.g., 'xlsx', 'pdf'")
    extracted_fields: list[ExtractedField] = Field(
        default_factory=list,
        description="All extracted field-value pairs",
    )
    chart_suggestions: list[ChartSuggestion] = Field(
        default_factory=list,
        description="Suggested ECharts visualizations",
    )
    dashboard_ready: list[DashboardWidget] = Field(
        default_factory=list,
        description="Integration-ready widget payloads for the main dashboard",
    )
    warnings: list[str] = Field(
        default_factory=list,
        description="Extraction warnings, e.g., 'Page 3 was image-only; OCR confidence low'",
    )
    metadata: dict[str, Any] = Field(
        default_factory=dict,
        description="File-level metadata (page count, sheet names, etc.)",
    )


class ErrorResponse(BaseModel):
    """Error response model."""

    detail: str = Field(..., description="Human-readable error message")
