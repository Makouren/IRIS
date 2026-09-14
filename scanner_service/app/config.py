import os
from typing import List
from dotenv import load_dotenv

load_dotenv()

class Settings:
    SERVICE_NAME: str = "IRIS AI File Scanner Microservice"
    SERVICE_VERSION: str = "1.0.0"
    HOST: str = os.getenv("HOST", "0.0.0.0")
    PORT: int = int(os.getenv("PORT", "8000"))
    
    # CORS Origins (Allow Laravel application frontend/backend)
    ALLOWED_ORIGINS: List[str] = [
        origin.strip() 
        for origin in os.getenv("ALLOWED_ORIGINS", "*").split(",") 
        if origin.strip()
    ]
    
    # OCR Settings
    TESSERACT_CMD: str = os.getenv("TESSERACT_CMD", "tesseract")
    MAX_FILE_SIZE_MB: int = int(os.getenv("MAX_FILE_SIZE_MB", "50"))

settings = Settings()
