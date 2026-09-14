"""
Main entry point for the IRIS AI File Scanner microservice.

FastAPI application providing:
  - GET  /health     — Liveness check
  - POST /scan-file  — File extraction, structuring, and visualization draft generation
"""

import logging
import os
import shutil
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, HTTPException, UploadFile, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app.chart_suggestor import suggest_charts
from app.config import settings
from app.dashboard_builder import build_dashboard_widgets
from app.extractors import (
    DocxExtractor,
    ImageExtractor,
    PdfExtractor,
    SpreadsheetExtractor,
)
from app.schemas import ErrorResponse, ScanResponse
from app.structurer import structure_extraction

# Configure logging
logging.basicConfig(
    level=getattr(logging, settings.log_level.upper(), logging.INFO),
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("iris-scanner")

app = FastAPI(
    title="IRIS AI File Scanner",
    description=(
        "Microservice for IRIS (International Rapport Insight System). "
        "Extracts structured metrics from offline files (spreadsheets, PDFs, DOCX, images), "
        "suggests Apache ECharts visualizations, and returns draft dashboard widget payloads "
        "ready for admin review and publishing."
    ),
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
)

# Enable CORS for communication with Laravel frontend / backend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health", tags=["System"])
async def health_check():
    """Liveness check endpoint."""
    return {
        "status": "ok",
        "service": "iris-ai-file-scanner",
        "version": "1.0.0",
        "gemini_enabled": settings.gemini_enabled,
    }


@app.post(
    "/scan-file",
    response_model=ScanResponse,
    responses={
        400: {"model": ErrorResponse, "description": "Unsupported file format"},
        413: {"model": ErrorResponse, "description": "File too large"},
        500: {"model": ErrorResponse, "description": "Extraction error"},
    },
    tags=["Scanner"],
    summary="Scan file and generate visualization draft",
)
async def scan_file(file: UploadFile = File(...)):
    """
    Accepts an uploaded file, extracts structured metrics and charts,
    and returns a DRAFT visualization payload ready for Laravel admin review.
    """
    filename = file.filename or "unknown"
    ext = Path(filename).suffix.lower()

    # 1. Validate file extension
    if ext not in settings.allowed_extensions_set:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=(
                f"Unsupported file type '{ext}'. "
                f"Allowed types: {', '.join(sorted(settings.allowed_extensions_set))}"
            ),
        )

    # 2. Save uploaded content to a temporary file for processing
    tmp_path: Path | None = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=ext) as tmp:
            shutil.copyfileobj(file.file, tmp)
            tmp_path = Path(tmp.name)

        # Check file size limit
        file_size = tmp_path.stat().st_size
        if file_size > settings.max_file_size_bytes:
            raise HTTPException(
                status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
                detail=f"File exceeds maximum allowed size of {settings.max_file_size_mb} MB",
            )

        logger.info(f"Processing uploaded file: '{filename}' ({file_size} bytes, type: {ext})")

        # 3. Dispatch to the appropriate extractor
        if ext in {".xlsx", ".xls", ".csv"}:
            extractor = SpreadsheetExtractor()
            file_category = "spreadsheet"
        elif ext == ".pdf":
            extractor = PdfExtractor()
            file_category = "pdf"
        elif ext == ".docx":
            extractor = DocxExtractor()
            file_category = "docx"
        elif ext in {".png", ".jpg", ".jpeg", ".webp"}:
            extractor = ImageExtractor()
            file_category = "image"
        else:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"No extractor available for extension '{ext}'",
            )

        # Run extraction
        extraction_result = extractor.extract(tmp_path)

        # 4. Structure raw extraction data into ExtractedFields
        extracted_fields = structure_extraction(extraction_result)

        # 5. Suggest Apache ECharts visualizations
        chart_suggestions = suggest_charts(extracted_fields)

        # 6. Assemble integration-ready DashboardWidget objects
        dashboard_ready = build_dashboard_widgets(extracted_fields, chart_suggestions)

        return ScanResponse(
            status="draft",
            filename=filename,
            file_type=file_category,
            extracted_fields=extracted_fields,
            chart_suggestions=chart_suggestions,
            dashboard_ready=dashboard_ready,
            warnings=extraction_result.warnings,
            metadata={
                **extraction_result.metadata,
                "original_filename": filename,
                "file_size_bytes": file_size,
                "extractor_used": extractor.__class__.__name__,
            },
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.exception(f"Failed to process file '{filename}': {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error extracting data from '{filename}': {str(e)}",
        )
    finally:
        # Clean up temporary file
        if tmp_path and tmp_path.exists():
            try:
                os.remove(tmp_path)
            except OSError:
                pass
