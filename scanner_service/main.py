from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import uvicorn

from app.config import settings
from app.services.dispatcher import FileScanDispatcher

app = FastAPI(
    title="IRIS AI File Scanner Microservice",
    description="Stateless document parsing, layout-aware OCR, and draft visualization suggestion for IRIS (International Rapport Insight System).",
    version=settings.SERVICE_VERSION,
    docs_url="/docs",
    redoc_url="/redoc"
)

# Setup CORS for Laravel Frontend/Backend Integration
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/health", summary="Health Check")
async def health_check():
    return {
        "status": "online",
        "service": settings.SERVICE_NAME,
        "version": settings.SERVICE_VERSION,
        "supported_formats": [
            "Spreadsheets (XLSX, XLS, CSV)",
            "PDF Documents (Native & Text-layer)",
            "Word Documents (DOCX structure & tables)"
            # Note: Image and embedded OCR scanning slated for review and revision
        ]
    }

@app.post("/scan-file", summary="Scan File and Generate Draft Visualization")
async def scan_file(file: UploadFile = File(...)):
    """
    Accepts one uploaded file, parses structured data / layout text / OCR,
    and returns a draft JSON payload with suggested chart type (bar, line, pie)
    for admin review in Laravel.
    """
    if not file.filename:
        raise HTTPException(status_code=400, detail="No file uploaded.")
    
    try:
        contents = await file.read()
        max_bytes = settings.MAX_FILE_SIZE_MB * 1024 * 1024
        if len(contents) > max_bytes:
            raise HTTPException(
                status_code=413, 
                detail=f"File exceeds maximum allowed size ({settings.MAX_FILE_SIZE_MB}MB)."
            )

        result = FileScanDispatcher.scan_file(
            file_bytes=contents,
            filename=file.filename,
            content_type=file.content_type or ""
        )
        return JSONResponse(status_code=200, content=result)

    except ValueError as ve:
        raise HTTPException(status_code=422, detail=str(ve))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Scanner execution failed: {str(e)}")

if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=True
    )
