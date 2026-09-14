"""
Chart Suggestor — suggests Apache ECharts visualizations for extracted fields.

Analyzes extracted data fields, determines the most appropriate ECharts chart
type (bar, line, pie, scatter, radar), and generates a complete Apache ECharts
option configuration dictionary.

Can operate in two modes:
  1. Heuristic mode (default, no external dependencies): Rule-based inference.
  2. Gemini mode (when GEMINI_API_KEY is configured): LLM-driven inference.
"""

import json
import logging
import re
from typing import Any, Optional

from app.config import settings
from app.schemas import ChartSuggestion, ExtractedField

logger = logging.getLogger(__name__)

# Valid Apache ECharts series types supported by this microservice
VALID_ECHARTS_TYPES = {"bar", "line", "pie", "scatter", "radar"}

# Time indicators for heuristic detection of line charts
TIME_INDICATORS = {
    "year", "yr", "month", "quarter", "q1", "q2", "q3", "q4",
    "sem", "semester", "date", "period", "trend", "annual"
}


def suggest_charts(fields: list[ExtractedField]) -> list[ChartSuggestion]:
    """
    Generate chart suggestions from a list of ExtractedField objects.

    Groups numeric fields logically and produces one or more ChartSuggestion
    objects containing full Apache ECharts option configurations.

    Args:
        fields: List of ExtractedField items.

    Returns:
        List of ChartSuggestion objects with chart_type, echarts_series_type,
        and echarts_option.
    """
    if not fields:
        return []

    # Separate numeric vs text fields
    numeric_fields: list[ExtractedField] = []
    text_fields: list[ExtractedField] = []

    for f in fields:
        if isinstance(f.value, (int, float)):
            numeric_fields.append(f)
        else:
            text_fields.append(f)

    if not numeric_fields:
        return []

    # Try Gemini suggestion if enabled and has enough fields
    if settings.gemini_enabled and len(numeric_fields) >= 2:
        gemini_suggestions = _suggest_with_gemini(fields, numeric_fields)
        if gemini_suggestions:
            return gemini_suggestions

    # Fallback to heuristic rule-based suggestions
    return _suggest_heuristic(fields, numeric_fields, text_fields)


def _suggest_heuristic(
    all_fields: list[ExtractedField],
    numeric_fields: list[ExtractedField],
    text_fields: list[ExtractedField],
) -> list[ChartSuggestion]:
    """
    Generate chart suggestions using heuristic rules.
    """
    suggestions: list[ChartSuggestion] = []

    # Group numeric fields by field name (column name)
    groups: dict[str, list[ExtractedField]] = {}
    for f in numeric_fields:
        groups.setdefault(f.name, []).append(f)

    # Check if there are category labels among text fields
    category_labels = [str(f.value) for f in text_fields if len(str(f.value)) < 30]

    for group_name, group_items in groups.items():
        if len(group_items) < 2:
            continue

        values = [float(item.value) for item in group_items]
        count = len(group_items)

        # Determine categories (x-axis or pie labels)
        if len(category_labels) >= count:
            categories = category_labels[:count]
        else:
            categories = [f"Item {i+1}" for i in range(count)]

        # Determine chart type
        chart_type = _determine_chart_type(group_name, categories, values)

        # Build ECharts option config
        echarts_option = _build_echarts_option(
            chart_type=chart_type,
            title=group_name,
            categories=categories,
            series_name=group_name,
            values=values,
            unit=group_items[0].unit,
        )

        suggestion = ChartSuggestion(
            chart_type=chart_type,
            echarts_series_type=chart_type,
            field_group=group_name,
            x_axis=None if chart_type == "pie" else "Category",
            y_axis=[group_name],
            fields=[f.name for f in group_items],
            echarts_option=echarts_option,
        )
        suggestions.append(suggestion)

    # If no multi-item groups, but we have multiple distinct single numeric fields
    if not suggestions and len(numeric_fields) >= 2:
        field_names = [f.name for f in numeric_fields[:10]]
        values = [float(f.value) for f in numeric_fields[:10]]
        title = "Summary Overview"
        chart_type = _determine_chart_type(title, field_names, values)

        echarts_option = _build_echarts_option(
            chart_type=chart_type,
            title=title,
            categories=field_names,
            series_name="Value",
            values=values,
            unit=numeric_fields[0].unit,
        )

        suggestions.append(
            ChartSuggestion(
                chart_type=chart_type,
                echarts_series_type=chart_type,
                field_group=title,
                x_axis=None if chart_type == "pie" else "Metric",
                y_axis=["Value"],
                fields=field_names,
                echarts_option=echarts_option,
            )
        )

    return suggestions


def _determine_chart_type(
    group_name: str,
    categories: list[str],
    values: list[float],
) -> str:
    """
    Select the most appropriate Apache ECharts series type based on data heuristics.
    """
    count = len(values)
    name_lower = group_name.lower()

    # Rule 1: Line chart for time series
    has_time_category = any(
        any(t in cat.lower() for t in TIME_INDICATORS)
        or re.search(r"\b20\d{2}\b", cat)
        for cat in categories
    )
    if has_time_category or any(t in name_lower for t in TIME_INDICATORS):
        return "line"

    # Rule 2: Pie chart for percentage distribution or small composition (<= 6 items)
    is_percentage = "%" in group_name or all(0 <= v <= 100 for v in values)
    sum_val = sum(values)
    if 2 <= count <= 6 and (95 <= sum_val <= 105 or (is_percentage and count <= 5)):
        return "pie"

    # Rule 3: Radar chart for multi-dimensional evaluation (scores / ratings)
    if 3 <= count <= 8 and all(0 <= v <= 100 for v in values) and any(
        kw in name_lower for kw in ["rating", "score", "evaluation", "competency", "rubric"]
    ):
        return "radar"

    # Default: Bar chart
    return "bar"


def _build_echarts_option(
    chart_type: str,
    title: str,
    categories: list[str],
    series_name: str,
    values: list[float],
    unit: Optional[str] = None,
) -> dict[str, Any]:
    """
    Build a complete, ready-to-use Apache ECharts option configuration dictionary.
    """
    unit_str = f" ({unit})" if unit else ""

    if chart_type == "pie":
        return {
            "title": {
                "text": title,
                "left": "center",
            },
            "tooltip": {
                "trigger": "item",
                "formatter": "{a} <br/>{b}: {c}" + (f" ({unit})" if unit else " ({d}%)"),
            },
            "legend": {
                "orient": "vertical",
                "left": "left",
            },
            "series": [
                {
                    "name": series_name,
                    "type": "pie",
                    "radius": "55%",
                    "center": ["50%", "60%"],
                    "data": [
                        {"name": cat, "value": val}
                        for cat, val in zip(categories, values)
                    ],
                    "emphasis": {
                        "itemStyle": {
                            "shadowBlur": 10,
                            "shadowOffsetX": 0,
                            "shadowColor": "rgba(0, 0, 0, 0.5)",
                        }
                    },
                }
            ],
        }

    elif chart_type == "radar":
        max_val = max(values) * 1.2 if values else 100
        if max_val <= 0:
            max_val = 100
        max_val = round(max_val, -1) if max_val > 10 else round(max_val, 1)

        return {
            "title": {
                "text": title,
            },
            "tooltip": {
                "trigger": "item",
            },
            "radar": {
                "indicator": [
                    {"name": cat, "max": max_val} for cat in categories
                ]
            },
            "series": [
                {
                    "name": series_name,
                    "type": "radar",
                    "data": [
                        {
                            "value": values,
                            "name": series_name,
                        }
                    ],
                }
            ],
        }

    elif chart_type == "scatter":
        # Pairs categories (indexed) and values
        scatter_data = [[i, v] for i, v in enumerate(values)]
        return {
            "title": {
                "text": title,
            },
            "tooltip": {
                "trigger": "item",
                "formatter": f"{{b}}: {{c}}{unit_str}",
            },
            "xAxis": {
                "type": "value",
                "name": "Index",
            },
            "yAxis": {
                "type": "value",
                "name": f"{series_name}{unit_str}",
            },
            "series": [
                {
                    "name": series_name,
                    "type": "scatter",
                    "symbolSize": 12,
                    "data": scatter_data,
                }
            ],
        }

    elif chart_type == "line":
        return {
            "title": {
                "text": title,
            },
            "tooltip": {
                "trigger": "axis",
            },
            "legend": {
                "data": [series_name],
            },
            "grid": {
                "left": "3%",
                "right": "4%",
                "bottom": "3%",
                "containLabel": True,
            },
            "xAxis": {
                "type": "category",
                "boundaryGap": False,
                "data": categories,
            },
            "yAxis": {
                "type": "value",
                "name": f"{series_name}{unit_str}",
            },
            "series": [
                {
                    "name": series_name,
                    "type": "line",
                    "smooth": True,
                    "data": values,
                }
            ],
        }

    else:  # Default: "bar"
        return {
            "title": {
                "text": title,
            },
            "tooltip": {
                "trigger": "axis",
                "axisPointer": {
                    "type": "shadow",
                },
            },
            "legend": {
                "data": [series_name],
            },
            "grid": {
                "left": "3%",
                "right": "4%",
                "bottom": "3%",
                "containLabel": True,
            },
            "xAxis": {
                "type": "category",
                "data": categories,
                "axisLabel": {
                    "interval": 0,
                    "rotate": 30 if any(len(c) > 8 for c in categories) else 0,
                },
            },
            "yAxis": {
                "type": "value",
                "name": f"{series_name}{unit_str}",
            },
            "series": [
                {
                    "name": series_name,
                    "type": "bar",
                    "data": values,
                }
            ],
        }


def _suggest_with_gemini(
    all_fields: list[ExtractedField],
    numeric_fields: list[ExtractedField],
) -> list[ChartSuggestion]:
    """
    Use Google Gemini to analyze fields and recommend ECharts configurations.
    """
    try:
        import google.generativeai as genai

        genai.configure(api_key=settings.gemini_api_key)
        model = genai.GenerativeModel("gemini-2.0-flash")

        fields_summary = [
            {"name": f.name, "value": f.value, "unit": f.unit, "source": f.source}
            for f in all_fields[:30]
        ]

        prompt = f"""You are a data visualization expert specializing in Apache ECharts.
Analyze the following extracted data fields and suggest 1-3 appropriate visualizations.
Only use valid ECharts series types: 'bar', 'line', 'pie', 'scatter', or 'radar'.

Data fields:
{json.dumps(fields_summary, indent=2)}

Return ONLY valid JSON (no markdown formatting, no code block backticks) in the following format:
[
  {{
    "chart_type": "bar",
    "echarts_series_type": "bar",
    "field_group": "Title or metric group name",
    "x_axis": "Category column or field name",
    "y_axis": ["Metric name"],
    "fields": ["Field1", "Field2"],
    "categories": ["Cat1", "Cat2", "Cat3"],
    "series_name": "Series Name",
    "values": [10.5, 20.0, 15.2]
  }}
]"""

        response = model.generate_content(prompt)
        response_text = response.text.strip()

        if response_text.startswith("```"):
            response_text = response_text.split("\n", 1)[1]
            if response_text.endswith("```"):
                response_text = response_text[:-3]
            response_text = response_text.strip()

        items = json.loads(response_text)
        suggestions: list[ChartSuggestion] = []

        for item in items:
            chart_type = item.get("chart_type", "bar").lower()
            if chart_type not in VALID_ECHARTS_TYPES:
                chart_type = "bar"

            field_group = item.get("field_group", "Visualization")
            categories = [str(c) for c in item.get("categories", [])]
            values = [float(v) for v in item.get("values", []) if isinstance(v, (int, float))]
            series_name = item.get("series_name", field_group)

            if not values:
                continue

            if len(categories) != len(values):
                categories = [f"Item {i+1}" for i in range(len(values))]

            echarts_option = _build_echarts_option(
                chart_type=chart_type,
                title=field_group,
                categories=categories,
                series_name=series_name,
                values=values,
            )

            suggestions.append(
                ChartSuggestion(
                    chart_type=chart_type,
                    echarts_series_type=chart_type,
                    field_group=field_group,
                    x_axis=item.get("x_axis"),
                    y_axis=item.get("y_axis", [series_name]),
                    fields=item.get("fields", [series_name]),
                    echarts_option=echarts_option,
                )
            )

        return suggestions

    except Exception as e:
        logger.warning(f"Gemini chart suggestion failed: {e}")
        return []
