import io
import re
from typing import Dict, Any, List
from PIL import Image

try:
    import fitz  # PyMuPDF
except ImportError:
    fitz = None

try:
    import pymupdf4llm
except ImportError:
    pymupdf4llm = None

try:
    import pytesseract
except ImportError:
    pytesseract = None

class PDFParser:
    """
    Parses native and scanned PDF files.
    - Uses PyMuPDF / PyMuPDF4LLM for layout-aware multi-column parsing.
    - Falls back to OCR (PyTesseract) on scanned/image-based pages.
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
            
            # 1. Attempt native text & layout block extraction
            text_blocks = page.get_text("blocks")  # (x0, y0, x1, y1, "text", block_no, block_type)
            page_text = page.get_text("text").strip()
            
            # 2. Check if page is scanned/image-heavy (low text yield)
            is_scanned = len(page_text) < 50
            
            if is_scanned and pytesseract is not None:
                # Render page to high-res image and OCR
                pix = page.get_pixmap(dpi=200)
                img = Image.open(io.BytesIO(pix.tobytes("png")))
                try:
                    ocr_text = pytesseract.image_to_string(img).strip()
                    if ocr_text:
                        page_text = ocr_text
                        embedded_ocr_text.append({
                            "page": page_num,
                            "type": "scanned_page_ocr",
                            "text": ocr_text
                        })
                except Exception as ocr_err:
                    pass
            
            raw_text_parts.append(f"--- Page {page_num} ---\n{page_text}")
            
            # 3. Layout-aware stat-card / key-value extraction
            # Multi-column infographs often have: "Metric Name" followed by "Value" or "Value Unit"
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
            
            # 4. Extract embedded images on this page if present
            image_list = page.get_images(full=True)
            for img_idx, img_info in enumerate(image_list):
                xref = img_info[0]
                try:
                    base_image = doc.extract_image(xref)
                    image_bytes = base_image["image"]
                    if pytesseract is not None and len(image_bytes) > 2048:
                        emb_img = Image.open(io.BytesIO(image_bytes))
                        emb_ocr = pytesseract.image_to_string(emb_img).strip()
                        if len(emb_ocr) > 10:
                            embedded_ocr_text.append({
                                "page": page_num,
                                "image_index": img_idx + 1,
                                "type": "embedded_graphic_ocr",
                                "text": emb_ocr
                            })
                except Exception:
                    pass
            
            pages_content.append({
                "page": page_num,
                "text": page_text,
                "is_scanned": is_scanned
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
        """
        Detects KPI / Stat-card patterns commonly found in university infographics
        e.g., "Passing Rate: 94.5%", "Faculty Count: 142", "Accredited Programs: 35"
        """
        cards = []
        lines = [line.strip() for line in text.split('\n') if line.strip()]
        
        # Regex patterns for key-value / KPI pairs
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
