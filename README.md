# IRIS — International Rapport Insight System
## AI File Scanner Microservice & Ingestion Engine

> **Traceability Note**: This module covers the **"Generate Draft Visualization"** use case for IRIS. It is a decoupled, stateless Python microservice designed to ingest uploaded offline files, extract structured data and layout-aware text/OCR, and return a draft visualization suggestion (`bar`, `line`, or `pie`) for administrator review inside the main Laravel/Flowbite admin panel.

---

## 1. System Architecture & Boundaries

```
+-----------------------------------------------------------------------------------+
|                        IRIS Core Application (Laravel & MySQL)                     |
|                                                                                   |
|   +--------------------------+    HTTP multipart/form-data     +---------------+  |
|   | Flowbite Admin Dashboard | =============================>  | Laravel       |  |
|   | (Review & Approve Draft) | <=============================  | Controller    |  |
|   +--------------------------+         JSON Draft Payload      +-------+-------+  |
|                 ^                                                      |          |
|                 | Renders Approved Charts                              | Save     |
|                 v                                                      v          |
|   +--------------------------+                                 +---------------+  |
|   |      Apache ECharts      |                                 | MySQL DB      |  |
|   +--------------------------+                                 +---------------+  |
+------------------------------------------------------------------------|----------+
                                                                         |
                                              POST /scan-file (1 File)   |
                                              <==========================+
                                              ===========================>
                                              Structured JSON Draft
                                                                         |
                                                                         v
                                                       +---------------------------+
                                                       | Python FastAPI            |
                                                       | Microservice (:8000)      |
                                                       | - pandas & openpyxl       |
                                                       | - PyMuPDF & PyMuPDF4LLM   |
                                                       | - python-docx & media OCR |
                                                       | - Tesseract OCR           |
                                                       | - Chart Suggester         |
                                                       +---------------------------+
```

### Core Architecture Principles:
1. **Separation of Concerns**: The Python service handles heavy document parsing, layout recognition, and OCR where PHP lacks native tooling. It maintains **no local database, no authentication, and no custom UI**.
2. **Draft-Only Workflow**: All scanner outputs are returned marked as `is_draft: true` (`pending_admin_review`). The Product Owner / OJT admin reviews and corrects the draft in Flowbite before it is saved or published.
3. **Zero Cost**: Built entirely using local, open-source libraries (`pandas`, `openpyxl`, `PyMuPDF`, `python-docx`, `Tesseract OCR`).

---

## 2. Supported File Formats & Extraction Methods

| Format | Supported Extensions | Extraction Pipeline |
| :--- | :--- | :--- |
| **Spreadsheets** | `.xlsx`, `.xls`, `.csv` | Direct worksheet parsing via `pandas` & `openpyxl`. Extracts column headers, data types, and numeric groups. |
| **PDF Documents** | `.pdf` (native & scanned) | Layout-aware block parsing via `PyMuPDF` / `PyMuPDF4LLM` to preserve multi-column / stat-card structures (e.g., OAD-CLSU Infographs). Scanned pages automatically fall back to OCR. |
| **Word Documents** | `.docx` | Paragraphs, headings, and tables extracted via `python-docx`. Embedded graphics in `word/media/` are unpacked and OCR'd. |
| **Standalone Images** | `.png`, `.jpg`, `.jpeg`, `.webp` | Direct OCR via `Pillow` & `pytesseract` to extract textual KPIs and tabular data from photographed certificates/records. |

---

## 3. REST API Contract

### Base URL: `http://localhost:8000`

### `GET /health`
Returns service status and supported format details.

---

### `POST /scan-file`
Accepts a single uploaded document/image file and returns the draft payload.

- **Request**: `multipart/form-data` with form field `file`.
- **Response**: `application/json`

#### Example Response:
```json
{
  "status": "success",
  "document_type": "spreadsheet",
  "file_name": "qao_scores.xlsx",
  "file_size_bytes": 24576,
  "is_draft": true,
  "approval_status": "pending_admin_review",
  "data": {
    "fields": [
      {
        "sheet": "Sheet1",
        "name": "College",
        "type": "categorical",
        "unit": null,
        "sample_values": ["CAS", "CBA", "COE", "CED"],
        "distinct_count": 4,
        "total_count": 4
      },
      {
        "sheet": "Sheet1",
        "name": "QAO Rating (%)",
        "type": "numeric",
        "unit": "%",
        "sample_values": [94.5, 88.2, 96.0, 91.4],
        "distinct_count": 4,
        "total_count": 4
      }
    ],
    "tables": [
      {
        "sheet_name": "Sheet1",
        "headers": ["College", "QAO Rating (%)"],
        "row_count": 4,
        "rows": [
          ["CAS", 94.5],
          ["CBA", 88.2],
          ["COE", 96.0],
          ["CED", 91.4]
        ]
      }
    ],
    "embedded_ocr_text": [],
    "raw_text_preview": "Sheet Sheet1 Column College: 4 records..."
  },
  "visualization_draft": {
    "suggested_chart_type": "bar",
    "confidence": 0.90,
    "is_draft": true,
    "status": "draft",
    "rationale": "Comparative discrete categories in 'College' against numeric measure 'QAO Rating (%)'.",
    "series_config": {
      "x_axis": "College",
      "y_axis": "QAO Rating (%)",
      "unit": "%",
      "data_preview": [
        { "label": "CAS", "value": 94.5 },
        { "label": "CBA", "value": 88.2 },
        { "label": "COE", "value": 96.0 },
        { "label": "CED", "value": 91.4 }
      ]
    }
  },
  "scan_metadata": {
    "engine": "iris-ai-file-scanner-v1",
    "processed_at": "2026-09-14T20:00:00Z"
  }
}
```

---

## 4. Draft Chart Suggestion Engine Logic

The scanner classifies data patterns into one of three standard chart types:
- **`bar`**: Discrete categories mapped to numerical metrics (e.g., Department vs Rating, Faculty Counts).
- **`line`**: Time-series or sequential data (e.g., Academic Years, Semesters, Monthly Records).
- **`pie`**: Proportional distributions or percentage fields with $\le 6$ categories.

---

## 5. Quick Start & Execution

### Option A: Running with Docker (Recommended)
```bash
cd scanner_service
docker compose up --build
```
The microservice will be available at `http://localhost:8000`. Access Swagger UI docs at `http://localhost:8000/docs`.

### Option B: Running with Local Python
1. Ensure Python 3.10+ and [Tesseract OCR](https://github.com/UB-Mannheim/tesseract/wiki) are installed.
2. Install dependencies:
   ```bash
   cd scanner_service
   pip install -r requirements.txt
   ```
3. Run the service:
   ```bash
   uvicorn main:app --host 0.0.0.0 --port 8000 --reload
   ```

---

## 6. Database Extractability for Product Owner (Ms. Hidalgo)

> [!IMPORTANT]
> **Data Ownership & Backup Note**:
> This capability is a deliberate administrator/owner provision for Ms. Hidalgo to retrieve and back up her own database, distinct from general dashboard viewers who have no export capabilities.

### Storage & Schema Standards:
1. **Standard Relational Schema**: All extracted drafts saved by Laravel must reside in standard MySQL tables (e.g., `draft_visualizations`, `extracted_series`) rather than opaque binary blobs or custom encoded formats.
2. **Database Configuration**: Standard `.env` connection settings in Laravel (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
3. **Database Backup / Export Guide**:
   - **Command Line (`mysqldump`)**:
     ```bash
     mysqldump -u root -p iris_database > iris_backup_$(date +%Y%m%d).sql
     ```
   - **phpMyAdmin**: Navigate to `http://localhost/phpmyadmin` -> Select `iris_database` -> Click **Export** -> Format: **SQL** -> Click **Go**.

---

## 7. Out of Scope Boundaries (Version 1.0)
- ❌ PII / Security audit engine, risk scoring, redactions.
- ❌ Executive summarization, document Q&A / chat interface.
- ❌ Standalone admin database portal inside Python (managed by Laravel Flowbite).
- ❌ General-viewer data export features.
- ❌ Live IAO synchronization (slated for future phase).
