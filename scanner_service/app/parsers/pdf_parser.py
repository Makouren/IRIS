import re
from typing import Dict, Any, List

try:
    import fitz  # PyMuPDF
except ImportError:
    fitz = None

class PDFParser:
    """
    Parses native and text-layer PDF files.
    - Uses PyMuPDF for layout-aware multi-column and stat-card parsing.
    - [NOTE]: Embedded image extraction and OCR fallback are commented out and slated for review/revision.
    """
    
    @staticmethod
    def parse(file_bytes: bytes, filename: str) -> Dict[str, Any]:
        if fitz is None:
            raise RuntimeError("PyMuPDF (fitz) is required for PDF parsing.")
        
        doc = fitz.open(stream=file_bytes, filetype="pdf")
        pages_content = []
        fields = []
        tables = []
        embedded_ocr_text = []
        raw_text_parts = []
        
        total_pages = len(doc)
        
        for page_idx in range(total_pages):
            page = doc[page_idx]
            page_num = page_idx + 1
            
            # 1. Native text & layout block extraction
            page_text = page.get_text("text").strip()
            
            # [SLATED FOR REVIEW & REVISION]: Scanned page OCR fallback disabled
            # if len(page_text) < 50 and pytesseract is not None:
            #     pix = page.get_pixmap(dpi=200)
            #     img = Image.open(io.BytesIO(pix.tobytes("png")))
            #     ocr_text = pytesseract.image_to_string(img).strip()
            #     if ocr_text: page_text = ocr_text

            raw_text_parts.append(f"--- Page {page_num} ---\n{page_text}")
            
            # 2. Layout-aware stat-card / key-value extraction
            stat_cards = PDFParser._extract_stat_cards(page_text)
            for card in stat_cards:
                fields.append({
                    "page": page_num,
                    "name": card["name"],
                    "type": "numeric" if card["is_numeric"] else "categorical",
                    "unit": card.get("unit"),
                    "sample_values": [card["value"]],
                    "distinct_count": 1,
                    "total_count": 1
                })
            
            # [SLATED FOR REVIEW & REVISION]: Embedded image extraction and OCR disabled
            # image_list = page.get_images(full=True)
            # for img_idx, img_info in enumerate(image_list): ...
            
            pages_content.append({
                "page": page_num,
                "text": page_text
            })
        
        doc.close()
        
        return {
            "format": "pdf",
            "filename": filename,
            "total_pages": total_pages,
            "fields": fields,
            "tables": tables,
            "raw_text": "\n\n".join(raw_text_parts),
            "embedded_ocr_text": embedded_ocr_text
        }

    @staticmethod
    def _extract_stat_cards(text: str) -> List[Dict[str, Any]]:
        cards = []
        lines = [line.strip() for line in text.split('\n') if line.strip()]
        
        pattern_kv = re.compile(r'^([A-Za-z\s\(\)\-\/]{3,40})\s*[:\-\=]\s*([0-9\.,]+)\s*([%\w]*)$')
        pattern_num_first = re.compile(r'^([0-9\.,]+)\s*([%\w]*)\s+([A-Za-z\s]{3,40})$')
        
        for line in lines:
            m1 = pattern_kv.match(line)
            if m1:
                name, val_str, unit = m1.groups()
                try:
                    clean_val = float(val_str.replace(',', ''))
                    cards.append({
                        "name": name.strip(),
                        "value": clean_val,
                        "unit": unit.strip() if unit else None,
                        "is_numeric": True
                    })
                except ValueError:
                    cards.append({
                        "name": name.strip(),
                        "value": val_str.strip(),
                        "unit": unit.strip() if unit else None,
                        "is_numeric": False
                    })
                continue
            
            m2 = pattern_num_first.match(line)
            if m2:
                val_str, unit, name = m2.groups()
                try:
                    clean_val = float(val_str.replace(',', ''))
                    cards.append({
                        "name": name.strip(),
                        "value": clean_val,
                        "unit": unit.strip() if unit else None,
                        "is_numeric": True
                    })
                except ValueError:
                    pass
                    
        return cards
