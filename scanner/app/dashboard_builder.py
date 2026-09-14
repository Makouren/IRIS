"""
Dashboard Builder — generates integration-ready dashboard widget payloads.

Transforms extracted fields and chart suggestions into structured DashboardWidget
objects that the Laravel admin panel can directly consume, preview, edit,
and publish to the main IRIS dashboard.
"""

import json
import logging
from typing import Any

from app.schemas import ChartSuggestion, DashboardWidget, ExtractedField

logger = logging.getLogger(__name__)

# Section mapping rules based on keywords found in field names or titles
SECTION_KEYWORDS: dict[str, list[str]] = {
    "Academic Quality": [
        "score", "rate", "passing", "accreditation", "qao", "academic",
        "exam", "grade", "gpa", "performance", "curriculum", "assessment",
        "board", "licensure", "competency"
    ],
    "Student Data": [
        "enrollment", "student", "enrollee", "population", "gender",
        "demographic", "admission", "retention", "graduating", "alumni",
        "cohort", "applicant"
    ],
    "Faculty & Staff": [
        "faculty", "teacher", "instructor", "staff", "personnel",
        "professor", "employee", "tenure", "plantilla", "teaching load"
    ],
    "Finance & Operations": [
        "budget", "revenue", "expense", "cost", "expenditure", "funding",
        "fee", "tuition", "php", "peso", "disbursement", "allocation",
        "financial", "audit"
    ],
    "Research & Extension": [
        "research", "publication", "citation", "journal", "extension",
        "community", "project", "grant", "patent", "innovation", "outreach"
    ],
}


def build_dashboard_widgets(
    fields: list[ExtractedField],
    chart_suggestions: list[ChartSuggestion],
) -> list[DashboardWidget]:
    """
    Assemble dashboard-ready widget payloads from chart suggestions and fields.

    Args:
        fields: Complete list of extracted fields from the file.
        chart_suggestions: List of generated chart suggestions.

    Returns:
        List of DashboardWidget models ready for Laravel consumption.
    """
    widgets: list[DashboardWidget] = []

    for idx, suggestion in enumerate(chart_suggestions, start=1):
        # 1. Title
        title = suggestion.field_group

        # 2. Section classification
        section = _infer_section(title, suggestion.fields)

        # 3. Description summary
        description = _generate_description(suggestion)

        # 4. JSON-serialized ECharts option
        echarts_option_json = json.dumps(
            suggestion.echarts_option,
            ensure_ascii=False,
            separators=(",", ":"),
        )

        # 5. Tabular data extraction for admin review table
        data_table = _build_data_table(suggestion, fields)

        widgets.append(
            DashboardWidget(
                suggested_title=title,
                suggested_description=description,
                suggested_section=section,
                display_order=idx,
                echarts_option_json=echarts_option_json,
                data_table=data_table,
            )
        )

    return widgets


def _infer_section(title: str, field_names: list[str]) -> str:
    """Infer the most suitable dashboard category / section from keywords."""
    combined_text = f"{title} {' '.join(field_names)}".lower()

    for section, keywords in SECTION_KEYWORDS.items():
        if any(kw in combined_text for kw in keywords):
            return section

    return "General"


def _generate_description(suggestion: ChartSuggestion) -> str:
    """Generate a readable description of what the visualization presents."""
    chart_type_names = {
        "bar": "Bar chart",
        "line": "Line chart",
        "pie": "Pie chart",
        "scatter": "Scatter plot",
        "radar": "Radar chart",
    }
    type_label = chart_type_names.get(suggestion.echarts_series_type, "Visualization")
    fields_count = len(suggestion.fields)

    return (
        f"{type_label} displaying {suggestion.field_group} "
        f"across {fields_count} data point{'s' if fields_count != 1 else ''}."
    )


def _build_data_table(
    suggestion: ChartSuggestion,
    all_fields: list[ExtractedField],
) -> list[dict[str, Any]]:
    """
    Extract a clean tabular representation of the data used in the chart
    so the admin UI can render an editable HTML table.
    """
    option = suggestion.echarts_option
    series_list = option.get("series", [])
    if not series_list:
        return []

    series = series_list[0]
    series_type = series.get("type", suggestion.echarts_series_type)

    # For Pie charts: series.data is a list of {name, value}
    if series_type == "pie":
        data = series.get("data", [])
        return [
            {"Label": str(d.get("name", f"Item {i+1}")), "Value": d.get("value", 0)}
            for i, d in enumerate(data)
        ]

    # For Radar charts: radar.indicator gives labels, series.data[0].value gives values
    if series_type == "radar":
        indicators = option.get("radar", {}).get("indicator", [])
        values = series.get("data", [{}])[0].get("value", [])
        table = []
        for i, ind in enumerate(indicators):
            val = values[i] if i < len(values) else 0
            table.append({
                "Metric": ind.get("name", f"Metric {i+1}"),
                "Value": val,
                "Max": ind.get("max", 100),
            })
        return table

    # For Bar and Line charts: xAxis.data gives categories, series.data gives values
    xaxis_data = option.get("xAxis", {}).get("data", [])
    series_data = series.get("data", [])
    series_name = series.get("name", suggestion.field_group or "Value")
    category_header = suggestion.x_axis or "Category"

    if xaxis_data and series_data:
        table = []
        for cat, val in zip(xaxis_data, series_data):
            table.append({
                category_header: cat,
                series_name: val,
            })
        return table

    # Fallback to field names and values
    matched_fields = [f for f in all_fields if f.name in suggestion.fields]
    if matched_fields:
        return [
            {"Field": f.name, "Value": f.value, "Unit": f.unit or ""}
            for f in matched_fields
        ]

    return []
