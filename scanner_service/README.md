# Scanner Service Interface Contract

This folder contains the Python document-scanning service used for file parsing and chart suggestion. It is a separate HTTP service, not a direct dependency of the browser frontend. The current contract is intentionally small and synchronous: a caller uploads a file, the service parses it, and returns a single JSON payload with draft visualization data.

## v7 Integration Boundary

The service is integration-ready at the HTTP contract level, but remains independently deployable. A future dashboard adapter should call `POST /scan-file`, validate the returned payload, and map its draft visualization into the host application's record model. This branch does not add that adapter, persistence wiring, authentication, queues, or callbacks.

## Service role

The service currently exposes these responsibilities:

- parse uploaded file content
- detect document type by filename or MIME type
- route to the corresponding parser
- extract table/field structure and raw text preview
- suggest a draft chart type using `ChartSuggester`
- return a single response payload for a dashboard or admin review workflow

This service is designed to be callable by a backend/dashboard orchestrator, but it is not yet a fully general-purpose shared service contract. It is still IRIS-oriented in naming and payload semantics.

## Entry points

### 1) FastAPI application in `main.py`

The service app is created in `main.py` and is exposed as a standard FastAPI app.

Available routes:

- `GET /health`
- `POST /scan-file`

#### `GET /health`

Response shape:

```json
{
  "status": "online",
  "service": "IRIS AI File Scanner Microservice",
  "version": "1.0.0",
  "supported_formats": [
    "Spreadsheets (XLSX, XLS, CSV)",
    "PDF Documents (Native & Text-layer)",
    "Word Documents (DOCX structure & tables)"
  ]
}
```

#### `POST /scan-file`

This is the primary trigger point for a parse job.

Request type: multipart form upload

Required form field:

- `file`: uploaded file object, as a standard `UploadFile`

Notes:

- filename is read from `file.filename`
- MIME type is read from `file.content_type`
- file size is checked against `MAX_FILE_SIZE_MB` from `app/config.py`
- there is no queue submission or async job ID
- the response is returned directly in the HTTP request

The route calls:

```python
result = FileScanDispatcher.scan_file(
    file_bytes=contents,
    filename=file.filename,
    content_type=file.content_type or ""
)
```

This means the immediate integration contract is: upload one file and receive one JSON result.

### 2) `FileScanDispatcher.scan_file()`

The actual orchestration entry point is in `app/services/dispatcher.py`:

```python
FileScanDispatcher.scan_file(file_bytes, filename, content_type="")
```

This method:

1. determines the file extension or MIME type
2. calls the appropriate parser:
   - spreadsheet parser for `.xlsx`, `.xls`, `.csv`
   - PDF parser for `.pdf`
   - DOCX parser for `.docx`
3. asks `ChartSuggester.suggest_draft_visualization(parsed_data)` for a single suggested chart
4. returns a unified response dictionary

This is not a message queue consumer and there is no callback, poll, or job status endpoint in the current implementation.

## Input contract

### Accepted input

A caller must send a file upload via multipart form data, with:

```text
file=<binary file>
```

The service expects enough metadata to identify the file:

- `filename`
- `content_type` (optional but used to help route parsing)

Additional metadata is not defined as an explicit schema in the API today. The implementation treats the file itself as the source of truth.

### Supported formats in current code

- spreadsheets: `.xlsx`, `.xls`, `.csv`
- PDFs: `.pdf`
- DOCX: `.docx`
- image scanning is currently commented out and explicitly marked as slated for review/revision

### Size constraints

Defined in `app/config.py`:

```python
MAX_FILE_SIZE_MB = 50
```

If the file exceeds that value, the endpoint returns `413`.

## Output contract

The HTTP response from `/scan-file` is a JSON object shaped like this:

```json
{
  "status": "success",
  "document_type": "pdf",
  "file_name": "example.pdf",
  "file_size_bytes": 12345,
  "is_draft": true,
  "approval_status": "pending_admin_review",
  "data": {
    "fields": [
      {
        "page": 1,
        "name": "Enrollment",
        "type": "numeric",
        "unit": null,
        "sample_values": [1250],
        "distinct_count": 1,
        "total_count": 1
      }
    ],
    "tables": [],
    "embedded_ocr_text": [],
    "raw_text_preview": "...first 2000 chars of extracted text..."
  },
  "visualization_draft": {
    "suggested_chart_type": "bar",
    "confidence": 0.9,
    "is_draft": true,
    "status": "draft",
    "rationale": "Comparative discrete categories in 'Department' against numeric measure 'Enrollment'.",
    "series_config": {
      "x_axis": "Department",
      "y_axis": "Enrollment",
      "unit": null,
      "data_preview": [
        { "label": "CS", "value": 1200 }
      ]
    }
  },
  "scan_metadata": {
    "engine": "clsu-iris-ai-file-scanner-v1",
    "processed_at": "2026-09-20T12:00:00Z"
  }
}
```

### Parser output contract

Each parser returns a normalized dictionary with a similar shape:

```python
{
  "format": "pdf" | "docx" | "spreadsheet",
  "filename": "...",
  "fields": [...],
  "tables": [...],
  "raw_text": "...",
  "embedded_ocr_text": []
}
```

The `fields` array is the common extracted metadata contract. A typical field is:

```json
{
  "name": "Enrollment",
  "type": "numeric",
  "unit": null,
  "sample_values": [1250],
  "distinct_count": 1,
  "total_count": 1
}
```

The `tables` array is structured as a document/table payload with headers and row values. This is the data that `ChartSuggester` reads when it tries to infer a draft chart.

## `ChartSuggester` output contract

`ChartSuggester.suggest_draft_visualization(parsed_data)` returns a single suggestion object that always includes:

- `suggested_chart_type`: one of `bar`, `line`, or `pie`
- `confidence`: numeric score
- `is_draft`: boolean
- `status`: usually `draft`
- `rationale`: plain-language justification
- `series_config`: chart metadata and preview data

This is intentionally not a full chart rendering schema. It is a draft recommendation object for downstream admin approval or dashboard rendering.

## Current coupling / IRIS-specific notes

This service is not fully decoupled from project-specific assumptions. The current implementation still reflects IRIS-specific semantics, such as:

- `approval_status`: `pending_admin_review`
- `is_draft`: `true`
- `scan_metadata.engine`: `clsu-iris-ai-file-scanner-v1`
- route descriptions mention Laravel/admin review workflows
- output uses IRIS-oriented field names like `visualization_draft`
- the document type and workflow names assume the current app context

This is not a blocker for a dashboard integration pass, but it is a coupling point to flag for future generalization. The service is functionally a stateless parse-and-suggest API, but its naming and policy fields still assume the IRIS environment.

## Current limitations / explicit “not implemented” areas

The code comments clearly note incomplete or disabled features:

- image parsing / OCR route is commented out
- embedded OCR for PDFs and DOCX is disabled
- no queue system, job polling, or result retrieval endpoint exists
- there is no callback/webhook contract for asynchronous processing
- no reusable retrieval endpoint besides direct upload response

## Run/usage

### Docker

```bash
docker compose up --build
```

### Local development

```bash
pip install -r requirements.txt
uvicorn main:app --reload
```

The app exposes interactive docs at:

- `/docs`
- `/redoc`

## Integration readiness verdict

Ready for a dashboard/backend orchestration pass as a synchronous upload-processing service, with the caveat that its payload is still IRIS-shaped and not yet fully generalized. It is ready for a caller to upload a file and consume a JSON result, but the service contract should still be treated as an IRIS-specific interface until a broader shared backend contract is defined.
