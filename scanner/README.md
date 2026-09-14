# IRIS — AI File Scanner Microservice

A standalone Python (FastAPI) microservice for the **IRIS (International Rapport Insight System)** dashboard.

---

## 1. Purpose & Overview

The **IRIS AI File Scanner** replaces the manual process where OJTs / staff manually read offline files and hardcoded values into the system. 

When an offline file (spreadsheet, PDF report, Word document, or image) is uploaded, this microservice:
1. **Extracts** structured data fields (metric names, values, units, and source locations).
2. **Suggests** the best visualization chart type using **Apache ECharts** (`bar`, `line`, `pie`, `scatter`, `radar`).
3. **Generates** an integration-ready `dashboard_ready` payload for the main IRIS dashboard.
4. **Returns** a single JSON payload marked with `status: "draft"`.

> [!IMPORTANT]
> **Draft Only — Human in the loop:** This microservice produces a **DRAFT only**. It does **not** auto-publish to the live dashboard. The Laravel admin panel presents the extracted fields and chart preview to the admin / PO / OJT team to review, edit, and approve before saving to MySQL.

---

## 2. Architecture & Integration Flow

```
┌────────────────────────────────────────────────────────────────────────┐
│                          Laravel Web Application                       │
│                                                                        │
│  1. User uploads offline file (PDF, XLSX, DOCX, IMG)                   │
│  2. Laravel forwards file to Scanner microservice                      │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ POST /scan-file (multipart/form-data)
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                   IRIS AI File Scanner (Python Microservice)           │
│                                                                        │
│  ┌───────────────────┐  ┌────────────────────┐  ┌───────────────────┐  │
│  │ Extractor Engine  │─►│ Structurer Engine  │─►│ Chart Suggestor   │  │
│  │ (PDF/DOCX/XLS/OCR)│  │ (Regex + Gemini AI)│  │ (Apache ECharts)  │  │
│  └───────────────────┘  └────────────────────┘  └─────────┬─────────┘  │
│                                                           │            │
│  ┌────────────────────────────────────────────────────────▼─────────┐  │
│  │ Dashboard Builder: Assembles integration-ready widget payloads   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Response: JSON (status: "draft")
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        Laravel Admin Review Screen                     │
│                                                                        │
│  - Instant Apache ECharts Live Preview (rendered from JSON)           │
│  - Editable data table & form fields (Title, Section, Metrics)         │
│  - [ Approve & Publish to MySQL ]    [ Reject / Discard ]              │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Supported File Formats

| Format | Extensions | Extraction Method |
| :--- | :--- | :--- |
| **Spreadsheets** | `.xlsx`, `.xls`, `.csv` | Native table parsing via `pandas` & `openpyxl` |
| **PDF Documents** | `.pdf` | Native text extraction via `PyMuPDF` (`fitz`); scanned page & image OCR fallback |
| **Word Documents**| `.docx` | Paragraphs & tables via `python-docx`; embedded image OCR via `pytesseract` |
| **Images** | `.png`, `.jpg`, `.jpeg`, `.webp` | OCR text extraction via Tesseract |

---

## 4. Quick Start & Setup

### Option A: Running with Docker (Recommended)

Docker automatically installs Python, system dependencies, and Tesseract OCR:

```bash
# 1. Navigate to the scanner directory
cd scanner

# 2. Copy the environment template
cp .env.example .env

# 3. Build and start the container
docker-compose up --build
```

The API will be live at `http://localhost:8000`. Interactive OpenAPI documentation is available at `http://localhost:8000/docs`.

---

### Option B: Running Locally with Python

#### Prerequisites
1. **Python 3.10+**
2. **Tesseract OCR Engine** installed on your system:
   - **Windows:** Download and install from [UB-Mannheim Tesseract OCR](https://github.com/UB-Mannheim/tesseract/wiki).
   - **Ubuntu/Debian:** `sudo apt-get install tesseract-ocr tesseract-ocr-eng`
   - **macOS:** `brew install tesseract`

#### Installation Steps

```bash
# 1. Navigate to scanner root
cd scanner

# 2. Create and activate a virtual environment
python -m venv venv

# Windows:
venv\Scripts\activate
# Linux / macOS:
source venv/bin/activate

# 3. Install Python dependencies
pip install -r requirements.txt

# 4. Copy environment configuration
cp .env.example .env

# 5. Start the FastAPI development server
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

---

## 5. Environment Variables (`.env`)

| Variable | Default | Description |
| :--- | :--- | :--- |
| `PORT` | `8000` | Port for the FastAPI server |
| `HOST` | `0.0.0.0` | Binding host address |
| `GEMINI_API_KEY` | *(empty)* | Optional Google Gemini API key for advanced OCR structuring & chart selection |
| `TESSERACT_CMD` | `""` | Path to `tesseract.exe` (e.g. `C:\Program Files\Tesseract-OCR\tesseract.exe`) |
| `MAX_FILE_SIZE_MB`| `25` | Maximum upload size in megabytes |
| `LOG_LEVEL` | `INFO` | Logging level (`DEBUG`, `INFO`, `WARNING`, `ERROR`) |

---

## 6. API Endpoints

### 1. `GET /health`
Liveness check.
```json
{
  "status": "ok",
  "service": "iris-ai-file-scanner",
  "version": "1.0.0",
  "gemini_enabled": false
}
```

### 2. `POST /scan-file`
Scans an uploaded file and returns structured draft visualizations.

- **Request:** `multipart/form-data` with key `file`.
- **Response:** Single JSON object conforming to `ScanResponse`.

#### Example Response Body:
```json
{
  "status": "draft",
  "filename": "QAO_Evaluation_2026.csv",
  "file_type": "spreadsheet",
  "extracted_fields": [
    {
      "name": "QAO Score",
      "value": 92.5,
      "unit": "%",
      "source": "Sheet1!Row2"
    },
    {
      "name": "QAO Score",
      "value": 89.0,
      "unit": "%",
      "source": "Sheet1!Row3"
    }
  ],
  "chart_suggestions": [
    {
      "chart_type": "bar",
      "echarts_series_type": "bar",
      "field_group": "QAO Score",
      "x_axis": "Category",
      "y_axis": ["QAO Score"],
      "fields": ["QAO Score"],
      "echarts_option": {
        "title": { "text": "QAO Score" },
        "tooltip": { "trigger": "axis" },
        "legend": { "data": ["QAO Score"] },
        "xAxis": { "type": "category", "data": ["BSCS", "BSIT"] },
        "yAxis": { "type": "value", "name": "QAO Score (%)" },
        "series": [
          { "name": "QAO Score", "type": "bar", "data": [92.5, 89.0] }
        ]
      }
    }
  ],
  "dashboard_ready": [
    {
      "suggested_title": "QAO Score",
      "suggested_description": "Bar chart displaying QAO Score across 2 data points.",
      "suggested_section": "Academic Quality",
      "display_order": 1,
      "echarts_option_json": "{\"title\":{\"text\":\"QAO Score\"},\"tooltip\":{\"trigger\":\"axis\"},\"legend\":{\"data\":[\"QAO Score\"]},\"xAxis\":{\"type\":\"category\",\"data\":[\"BSCS\",\"BSIT\"]},\"yAxis\":{\"type\":\"value\",\"name\":\"QAO Score (%)\"},\"series\":[{\"name\":\"QAO Score\",\"type\":\"bar\",\"data\":[92.5,89.0]}]}",
      "data_table": [
        { "Category": "BSCS", "QAO Score": 92.5 },
        { "Category": "BSIT", "QAO Score": 89.0 }
      ]
    }
  ],
  "warnings": [],
  "metadata": {
    "sheet_count": 1,
    "total_rows": 2,
    "original_filename": "QAO_Evaluation_2026.csv",
    "file_size_bytes": 1024,
    "extractor_used": "SpreadsheetExtractor"
  }
}
```

---

## 7. Apache ECharts Integration

The microservice outputs standard Apache ECharts configurations. In your frontend (Blade/Vue/React), you can render the chart immediately:

```javascript
// Example Laravel Blade / JavaScript preview
const widget = response.dashboard_ready[0];
const chartDom = document.getElementById('chart-container');
const myChart = echarts.init(chartDom);

// Directly pass the serialized option
const option = JSON.parse(widget.echarts_option_json);
myChart.setOption(option);
```

### Supported Chart Types
- **`bar`**: Category vs value comparisons.
- **`line`**: Time-series trends and sequential data.
- **`pie`**: Proportional distribution and percentage breakdowns.
- **`scatter`**: Multi-variable distribution.
- **`radar`**: Multi-metric competency / rubric evaluations.

---

## 8. Running the Tests

```bash
cd scanner
pytest tests/ -v
```

---

## 9. Database & Architectural Note

- **Stateless Microservice:** This microservice does **not** connect directly to the MySQL database. It acts as a pure compute/transformation pipeline.
- **Persistence:** Saving approved draft widgets is handled by the main Laravel application upon admin approval.
