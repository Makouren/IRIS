from typing import Dict, Any, List, Optional
import re

class ChartSuggester:
    """
    Analyzes structured fields and tables extracted from a document
    to suggest ONE appropriate draft chart type per numeric grouping:
    - 'bar' : Categorical comparisons (e.g. scores across colleges/departments)
    - 'line': Temporal / continuous trends (e.g. years, semesters, chronological scores)
    - 'pie' : Proportions / parts of a whole (percentages summing near 100%, or <= 6 categories)
    """
    
    TEMPORAL_KEYWORDS = ["year", "date", "month", "quarter", "semester", "period", "yr", "time", "day"]
    PROPORTION_KEYWORDS = ["percentage", "percent", "%", "share", "proportion", "distribution", "rate", "ratio"]
    
    @classmethod
    def suggest_draft_visualization(cls, parsed_data: Dict[str, Any]) -> Dict[str, Any]:
        fields = parsed_data.get("fields", [])
        tables = parsed_data.get("tables", [])
        
        # 1. First priority: Analyze tables if present
        if tables:
            for table in tables:
                headers = table.get("headers", [])
                rows = table.get("rows", [])
                if len(headers) >= 2 and rows:
                    suggestion = cls._analyze_table(headers, rows)
                    if suggestion:
                        return suggestion
        
        # 2. Second priority: Analyze extracted fields
        numeric_fields = [f for f in fields if f.get("type") == "numeric"]
        categorical_fields = [f for f in fields if f.get("type") == "categorical"]
        
        if numeric_fields:
            return cls._analyze_field_groups(numeric_fields, categorical_fields)
            
        # Fallback if only text/unstructured numbers are found
        return {
            "suggested_chart_type": "bar",
            "confidence": 0.50,
            "is_draft": True,
            "status": "draft",
            "rationale": "Default baseline draft visualization pending administrator configuration.",
            "series_config": {
                "x_axis": "Category",
                "y_axis": "Value",
                "unit": None
            }
        }

    @classmethod
    def _analyze_table(cls, headers: List[str], rows: List[List[Any]]) -> Optional[Dict[str, Any]]:
        # Identify column types
        col_types = []
        for col_idx in range(len(headers)):
            vals = [r[col_idx] for r in rows if col_idx < len(r) and r[col_idx] is not None]
            num_count = 0
            for v in vals:
                try:
                    float(str(v).replace(',', '').replace('%', '').strip())
                    num_count += 1
                except (ValueError, AttributeError):
                    pass
            is_num = (num_count / len(vals) >= 0.7) if vals else False
            col_types.append("numeric" if is_num else "categorical")
            
        numeric_cols = [i for i, t in enumerate(col_types) if t == "numeric"]
        categorical_cols = [i for i, t in enumerate(col_types) if t == "categorical"]
        
        if not numeric_cols:
            return None
            
        num_col_idx = numeric_cols[0]
        y_axis_name = headers[num_col_idx]
        
        x_axis_name = headers[categorical_cols[0]] if categorical_cols else "Index"
        x_lower = x_axis_name.lower()
        y_lower = y_axis_name.lower()
        
        # Check for Line chart: Temporal column
        is_temporal = any(k in x_lower for k in cls.TEMPORAL_KEYWORDS)
        if is_temporal:
            return {
                "suggested_chart_type": "line",
                "confidence": 0.92,
                "is_draft": True,
                "status": "draft",
                "rationale": f"Temporal series detected on '{x_axis_name}', suitable for trend analysis over time.",
                "series_config": {
                    "x_axis": x_axis_name,
                    "y_axis": y_axis_name,
                    "data_preview": [{"label": str(r[categorical_cols[0]]), "value": r[num_col_idx]} for r in rows[:10] if len(r) > num_col_idx and categorical_cols]
                }
            }
            
        # Check for Pie chart: Proportion / Part-of-whole
        is_proportion = any(k in y_lower for k in cls.PROPORTION_KEYWORDS) or "%" in y_axis_name
        if is_proportion and len(rows) <= 7:
            return {
                "suggested_chart_type": "pie",
                "confidence": 0.88,
                "is_draft": True,
                "status": "draft",
                "rationale": f"Proportional distribution detected with {len(rows)} categories.",
                "series_config": {
                    "x_axis": x_axis_name,
                    "y_axis": y_axis_name,
                    "unit": "%",
                    "data_preview": [{"label": str(r[categorical_cols[0]]), "value": r[num_col_idx]} for r in rows[:7] if len(r) > num_col_idx and categorical_cols]
                }
            }
            
        # Default for discrete categories: Bar chart
        return {
            "suggested_chart_type": "bar",
            "confidence": 0.90,
            "is_draft": True,
            "status": "draft",
            "rationale": f"Comparative discrete categories in '{x_axis_name}' against numeric measure '{y_axis_name}'.",
            "series_config": {
                "x_axis": x_axis_name,
                "y_axis": y_axis_name,
                "unit": None,
                "data_preview": [{"label": str(r[categorical_cols[0]]), "value": r[num_col_idx]} for r in rows[:10] if len(r) > num_col_idx and categorical_cols]
            }
        }

    @classmethod
    def _analyze_field_groups(cls, numeric_fields: List[Dict[str, Any]], categorical_fields: List[Dict[str, Any]]) -> Dict[str, Any]:
        num_names = [f["name"] for f in numeric_fields]
        
        # Check if field names indicate percentages
        has_percentage = any(f.get("unit") == "%" or "%" in f["name"] for f in numeric_fields)
        if has_percentage and len(numeric_fields) <= 6:
            return {
                "suggested_chart_type": "pie",
                "confidence": 0.85,
                "is_draft": True,
                "status": "draft",
                "rationale": "Extracted metrics contain percentage or distribution indicators with limited categories.",
                "series_config": {
                    "x_axis": "Metric",
                    "y_axis": "Percentage",
                    "unit": "%",
                    "data_preview": [{"label": f["name"], "value": f.get("sample_values", [0])[0]} for f in numeric_fields[:6]]
                }
            }
            
        # Check temporal field names
        has_temporal = any(any(k in f["name"].lower() for k in cls.TEMPORAL_KEYWORDS) for f in numeric_fields + categorical_fields)
        if has_temporal:
            return {
                "suggested_chart_type": "line",
                "confidence": 0.86,
                "is_draft": True,
                "status": "draft",
                "rationale": "Chronological/temporal attributes identified across extracted data points.",
                "series_config": {
                    "x_axis": "Timeline",
                    "y_axis": "Values",
                    "data_preview": [{"label": f["name"], "value": f.get("sample_values", [0])[0]} for f in numeric_fields[:10]]
                }
            }
            
        # Standard Bar chart for KPI and comparative metrics
        return {
            "suggested_chart_type": "bar",
            "confidence": 0.89,
            "is_draft": True,
            "status": "draft",
            "rationale": f"Structured numeric fields extracted ({len(numeric_fields)} metrics), suited for comparative bar visualization.",
            "series_config": {
                "x_axis": "Metrics",
                "y_axis": "Values",
                "data_preview": [{"label": f["name"], "value": f.get("sample_values", [0])[0]} for f in numeric_fields[:10]]
            }
        }
