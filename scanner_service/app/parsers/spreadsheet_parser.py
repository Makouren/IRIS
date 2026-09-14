import io
import pandas as pd
from typing import Dict, Any, List

class SpreadsheetParser:
    """
    Parses structured spreadsheet formats (XLSX, XLS, CSV) directly
    using pandas and openpyxl without requiring heavy OCR or external AI.
    """
    
    @staticmethod
    def parse(file_bytes: bytes, filename: str) -> Dict[str, Any]:
        ext = filename.lower().split('.')[-1]
        tables = []
        fields = []
        all_text_fragments = []
        
        try:
            if ext == 'csv':
                df = pd.read_csv(io.BytesIO(file_bytes))
                sheets = {"Sheet1": df}
            else:
                # Excel file with potentially multiple sheets
                excel_file = pd.ExcelFile(io.BytesIO(file_bytes))
                sheets = {sheet: excel_file.parse(sheet) for sheet in excel_file.sheet_names}
        except Exception as e:
            # Fallback for CSV encoding issues
            if ext == 'csv':
                df = pd.read_csv(io.BytesIO(file_bytes), encoding='latin1')
                sheets = {"Sheet1": df}
            else:
                raise ValueError(f"Failed to parse spreadsheet {filename}: {str(e)}")
        
        for sheet_name, df in sheets.items():
            # Clean dataframe (drop entirely empty rows and cols)
            df = df.dropna(how='all').dropna(axis=1, how='all')
            if df.empty:
                continue
            
            # Sanitize column names
            headers = [str(col).strip() if pd.notna(col) else f"Column_{i+1}" for i, col in enumerate(df.columns)]
            df.columns = headers
            
            rows = []
            for _, row in df.iterrows():
                row_vals = []
                for val in row:
                    if pd.isna(val):
                        row_vals.append(None)
                    elif isinstance(val, (int, float)):
                        row_vals.append(val)
                    else:
                        row_vals.append(str(val).strip())
                rows.append(row_vals)
            
            tables.append({
                "sheet_name": sheet_name,
                "headers": headers,
                "row_count": len(rows),
                "rows": rows[:100]  # sample/limit rows for API payload efficiency
            })
            
            # Analyze columns / fields
            for col in df.columns:
                col_data = df[col].dropna()
                is_numeric = pd.api.types.is_numeric_dtype(col_data)
                
                # Check for unit identifiers in header (e.g. "Score (%)", "Revenue ($)", "Count (units)")
                unit = None
                header_str = str(col)
                if "(" in header_str and ")" in header_str:
                    unit = header_str[header_str.find("(")+1:header_str.find(")")]
                elif "%" in header_str or "percent" in header_str.lower():
                    unit = "%"
                elif "count" in header_str.lower() or "qty" in header_str.lower():
                    unit = "count"
                
                sample_values = col_data.head(5).tolist()
                
                fields.append({
                    "sheet": sheet_name,
                    "name": str(col),
                    "type": "numeric" if is_numeric else "categorical",
                    "unit": unit,
                    "sample_values": sample_values,
                    "distinct_count": int(col_data.nunique()),
                    "total_count": int(len(col_data))
                })
                
                all_text_fragments.append(f"Sheet {sheet_name} Column {col}: {len(col_data)} records")

        return {
            "format": "spreadsheet",
            "filename": filename,
            "fields": fields,
            "tables": tables,
            "raw_text": "\n".join(all_text_fragments),
            "embedded_ocr_text": []
        }
