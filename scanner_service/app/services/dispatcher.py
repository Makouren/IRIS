from typing import Dict, Any
from datetime import datetime

from app.parsers.spreadsheet_parser import SpreadsheetParser
from app.parsers.pdf_parser import PDFParser
from app.parsers.docx_parser import DOCXParser
from app.parsers.image_parser import ImageParser
from app.services.chart_suggester import ChartSuggester

class FileScanDispatcher:
    """
    Orchestrates format detection, invokes appropriate parser,
    runs draft chart suggestion, and returns standardized IRIS JSON.
    [NOTE]: Standalone image and embedded media OCR scanning are commented out and slated for review/revision.
    """
    
    SPREADSHEET_EXTS = {'xlsx', 'xls', 'csv'}
    PDF_EXTS = {'pdf'}
    DOCX_EXTS = {'docx'}
    IMAGE_EXTS = {'png', 'jpg', 'jpeg', 'webp', 'bmp', 'tiff'}
    
    @classmethod
    def scan_file(cls, file_bytes: bytes, filename: str, content_type: str = "") -> Dict[str, Any]:
        ext = filename.lower().split('.')[-1] if '.' in filename else ""
        
        # 1. Route to format parser
        if ext in cls.SPREADSHEET_EXTS or 'spreadsheet' in content_type or 'csv' in content_type:
            doc_type = "spreadsheet"
            parsed_data = SpreadsheetParser.parse(file_bytes, filename)
            
        elif ext in cls.PDF_EXTS or 'pdf' in content_type:
            doc_type = "pdf"
            parsed_data = PDFParser.parse(file_bytes, filename)
            
        elif ext in cls.DOCX_EXTS or 'wordprocessingml' in content_type:
            doc_type = "docx"
            parsed_data = DOCXParser.parse(file_bytes, filename)
            
        elif ext in cls.IMAGE_EXTS or content_type.startswith('image/'):
            doc_type = "image"
            parsed_data = ImageParser.parse(file_bytes, filename)
            
        else:
            raise ValueError(f"Unsupported file format '.{ext}'. Supported formats: XLSX, XLS, CSV, PDF, DOCX, PNG, JPG, JPEG, WEBP, BMP, TIFF.")

        # 2. Generate Draft Chart Suggestion
        visualization_draft = ChartSuggester.suggest_draft_visualization(parsed_data)

        # 3. Assemble Unified JSON Output for Laravel
        return {
            "status": "success",
            "document_type": doc_type,
            "file_name": filename,
            "file_size_bytes": len(file_bytes),
            "is_draft": True,
            "approval_status": "pending_admin_review",
            "data": {
                "fields": parsed_data.get("fields", []),
                "tables": parsed_data.get("tables", []),
                "embedded_ocr_text": [],
                "raw_text_preview": parsed_data.get("raw_text", "")[:2000]
            },
            "visualization_draft": visualization_draft,
            "scan_metadata": {
                "engine": "clsu-iris-ai-file-scanner-v1",
                "processed_at": datetime.utcnow().isoformat() + "Z"
            }
        }
