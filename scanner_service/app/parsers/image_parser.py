import io
from typing import Dict, Any

try:
    from PIL import Image
    import pytesseract
except ImportError:
    Image = None
    pytesseract = None


class ImageParser:
    @staticmethod
    def parse(file_bytes: bytes, filename: str) -> Dict[str, Any]:
        if Image is None or pytesseract is None:
            raise RuntimeError("Pillow and pytesseract are required for image parsing.")

        try:
            image = Image.open(io.BytesIO(file_bytes))
            raw_text = pytesseract.image_to_string(image).strip()
        except Exception as exc:
            raise ValueError(f"Failed to OCR image {filename}: {exc}") from exc

        return {
            "format": "image",
            "filename": filename,
            "status": "success",
            "fields": [],
            "raw_text": raw_text,
            "embedded_ocr_text": []
        }
