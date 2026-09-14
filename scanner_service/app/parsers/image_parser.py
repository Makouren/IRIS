import io
import re
from typing import Dict, Any, List
from PIL import Image

try:
    import pytesseract
except ImportError:
    pytesseract = None

class ImageParser:
    """
    Parses Standalone Images (PNG, JPG, JPEG, WEBP) via OCR.
    Extracts text, numbers, and KPI metrics from photographed certificates,
    infographics, or scanned documents.
    """
    
    @staticmethod
    def parse(file_bytes: bytes, filename: str) -> Dict[str, Any]:
        img = Image.open(io.BytesIO(file_bytes))
        width, height = img.size
        
        extracted_text = ""
        fields = []
        
        if pytesseract is not None:
            try:
                extracted_text = pytesseract.image_to_string(img).strip()
            except Exception as e:
                extracted_text = f"OCR Error: {str(e)}"
        else:
            extracted_text = "PyTesseract engine not installed on host."
            
        # Extract KPI patterns from OCR text
        lines = [l.strip() for l in extracted_text.split('\n') if l.strip()]
        for line in lines:
            # Check for patterns like "Passing Rate: 95%", "Total Students: 1,450", "Year: 2024"
            match = re.match(r'^([A-Za-z\s\(\)\-\/]{3,35})\s*[:\-\=]\s*([0-9\.,]+)\s*([%\w]*)$', line)
            if match:
                name, val_str, unit = match.groups()
                try:
                    clean_val = float(val_str.replace(',', ''))
                    fields.append({
                        "name": name.strip(),
                        "type": "numeric",
                        "unit": unit.strip() if unit else None,
                        "sample_values": [clean_val],
                        "distinct_count": 1,
                        "total_count": 1
                    })
                except ValueError:
                    fields.append({
                        "name": name.strip(),
                        "type": "categorical",
                        "unit": None,
                        "sample_values": [val_str.strip()],
                        "distinct_count": 1,
                        "total_count": 1
                    })

        return {
            "format": "image",
            "filename": filename,
            "dimensions": {"width": width, "height": height},
            "fields": fields,
            "raw_text": extracted_text,
            "embedded_ocr_text": [
                {
                    "type": "standalone_image_ocr",
                    "text": extracted_text
                }
            ]
        }
