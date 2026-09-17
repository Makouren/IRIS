# IRIS AI File Scanner & Admin Data Engine

IRIS is a browser-based document intake and review system for scanning institutional files, extracting structured data, generating draft chart suggestions, and allowing administrators to review and edit saved records.

This version reflects the actual implementation currently present in this workspace: a Node.js + Express server, a static front-end dashboard, and a MySQL-backed admin database.

---

## What the current system does

The application allows a user to:

- upload spreadsheet, Word, and PDF files,
- parse the document content and detect tabular data,
- generate chart draft recommendations such as bar, line, and pie charts,
- review the extracted document summary in the workspace UI,
- save records to a database for admin review,
- edit, delete, and manage scanned records through the admin portal.

The system is designed as a draft-review workflow rather than a final publishing engine. It prepares data and visual suggestions for approval by an administrator.

---

## Current architecture

The active codebase in this workspace is:

- Front-end: static HTML/CSS/JS app served by Express
- Backend: Node.js server in `server.js`
- Database: MySQL via `mysql2/promise`
- Local/offline fallback: IndexedDB + localStorage
- Chart generation: client-side graph suggestion engine

Runtime flow:

1. User uploads a file in the UI.
2. The scanner chooses the correct parser based on file type.
3. Extracted text, table data, and metadata are combined into a scan package.
4. A draft chart suggestion is generated from the data pattern.
5. The result is saved to the database and shown in the admin portal.

---

## Supported file types

The current implementation supports these formats in the active application:

| Format | Extensions | Current support |
| --- | --- | --- |
| Spreadsheet | `.xlsx`, `.xls`, `.csv` | Supported |
| Word document | `.docx` | Supported |
| PDF document | `.pdf` | Supported |
| Image/OCR files | `.png`, `.jpg`, `.jpeg`, `.webp` | Not actively enabled in the current build |

> The repository still contains a separate `scanner_service/` directory with a Python-based microservice, but the active runtime in this workspace is the Node/Express app rather than the Python service.

---

## Project structure

```text
IRIS/
├── index.html
├── server.js
├── package.json
├── README.md
├── css/
│   └── styles.css
├── js/
│   ├── app.js
│   ├── scanner.js
│   ├── chartData.js
│   ├── chartMapping.js
│   ├── documentPagination.js
│   ├── samples.js
│   ├── tableFilter.js
│   ├── sourceIngestion.js
│   ├── ai/
│   │   └── graphEngine.js
│   ├── database/
│   │   └── dbManager.js
│   └── parsers/
│       ├── docxParser.js
│       ├── excelParser.js
│       ├── pdfParser.js
│       ├── imageParser.js
│       └── ...
├── scanner_service/
│   └── Python FastAPI microservice (legacy/secondary implementation)
└── test/
    └── JavaScript tests
```

---

## Main behavior

### Scanner workspace
The UI supports a scan queue and file drop zone. Users can:

- drag and drop files,
- browse for files,
- run sample document generation,
- view a document overview,
- inspect extracted fields,
- review draft chart recommendations.

### Admin portal
The admin dashboard reads records from the server and displays:

- total records,
- pending review counts,
- verified records,
- tables captured,
- searchable and filterable record list.

Admins can update record metadata, revise statuses, and delete records.

### Draft chart generation
The graph engine inspects headers and row structures and recommends one of the following based on the detected pattern:

- `bar` for categorical comparisons,
- `line` for time-series or sequential trends,
- `pie` for proportional distributions.

---

## API endpoints

The server exposes a simple REST API on the same app host.

### Health check

```bash
GET /api/health
```

Returns service status and the current number of records in the database.

### Records API

```bash
GET /api/records
POST /api/records
PUT /api/records/:id
DELETE /api/records/:id
```

These endpoints are used by the front-end to:

- fetch all saved scans,
- insert a new record,
- update a record after admin review,
- delete a record.

---

## Database setup

The current implementation expects a MySQL database named `iris_db` on `localhost`.

The server creates these tables automatically if they do not exist:

- `records`
- `saved_graphs`

Example default connection from the app:

```js
host: 'localhost',
user: 'root',
password: '',
database: 'iris_db'
```

If MySQL is not available, the app falls back to local browser storage, but the server is the canonical store when available.

---

## Quick start

### 1. Install dependencies

```bash
npm install
```

### 2. Start MySQL
Ensure a local MySQL server is running and that the database `iris_db` exists or is accessible.

### 3. Run the app

```bash
npm start
```

The app will start on the default port configured in the server. The server chooses an available port if the configured one is busy.

---

## Development notes

This project is a working internal tool, not a polished SaaS product. It includes:

- UI-driven processing,
- draft chart recommendations,
- local persistence fallbacks,
- a lightweight admin management workflow.

It currently does not include full image OCR processing in the active browser app, and some of the older microservice features remain in the repository as separate legacy code.

---

## Testing

The project includes JavaScript tests under `test/`.

Run them with:

```bash
npm test
```

---

## Summary

The current IRIS application is a practical document scanning and draft visualization review system built around:

- file upload and parsing,
- structured data extraction,
- chart suggestion generation,
- MySQL-backed record storage,
- admin validation and record management.

It is a real working implementation of an internal dashboard and review workflow, and the README has been updated to reflect that current state.
