# IRIS AI File Scanner and Admin Data Engine

IRIS is a browser-based document intake and review system for institutional files. It parses spreadsheets, Word documents, and PDFs, extracts structured data, generates draft chart suggestions, and provides an administrator workflow for reviewing and cleaning records.

The active application is a static HTML/CSS/ES module frontend served by Node.js and Express, with MySQL as the server-side store and IndexedDB/localStorage as browser fallbacks.

## Documentation map

| Area | Documentation |
| --- | --- |
| Frontend module architecture | [js/modules/README.md](js/modules/README.md) |
| File parsing and document viewers | [js/parsers/README.md](js/parsers/README.md) |
| Database and REST persistence | [js/database/README.md](js/database/README.md) |
| Graph generation and chart data | [js/ai/README.md](js/ai/README.md) |
| Tests and regression checks | [test/README.md](test/README.md) |
| Secondary Python scanner service | [scanner_service/README.md](scanner_service/README.md) |

## v7 Integration Readiness

The `v7` branch documents and prepares the frontend and scanner service for a future dashboard integration without wiring them together yet:

- `scanner_service/` exposes a documented, synchronous FastAPI upload contract.
- `DatabaseManager` keeps REST endpoint paths in configurable endpoint definitions while retaining IndexedDB/localStorage fallbacks.
- `chartEngine.js` accepts host-provided state, active-sheet access, and UI elements so chart rendering can be reused by another host page.
- Parser, graph, and module READMEs describe the current boundaries and contracts used by a future orchestrator.

No new backend integration, queue, authentication flow, or dashboard adapter is included in this branch.

## Runtime flow

1. The browser loads the legacy vendor-facing utilities and the ES module entry point, [js/app.js](js/app.js).
2. `app.js` creates the scanner, database manager, shared application state, and module context.
3. The file-ingestion module accepts uploads, drag-and-drop files, or generated samples.
4. `ScannerOrchestrator` selects a parser and builds a scan package.
5. `GraphEngine` creates draft chart suggestions from tables or extracted text.
6. `DatabaseManager` persists records through the Express API and local browser fallbacks.
7. The scanner workspace renders the overview, viewer, queue, and draft charts.
8. The admin portal supports editing, cleaning, saving, approving, exporting, and deleting data.

## Supported files

| Format | Extensions | Status |
| --- | --- | --- |
| Spreadsheet | `.xlsx`, `.xls`, `.csv` | Active |
| Word document | `.docx` | Active |
| PDF document | `.pdf` | Active |
| Image/OCR | `.png`, `.jpg`, `.jpeg`, `.webp` | Disabled in the active browser flow |

## Project structure

```text
IRIS/
├── index.html
├── server.js
├── package.json
├── README.md
├── css/
├── js/
│   ├── app.js                 # ES module bootstrap
│   ├── modules/               # UI modules and shared state
│   ├── utils/                 # Small frontend helpers
│   ├── ai/                    # Graph suggestion engine
│   ├── database/              # Persistence client
│   ├── parsers/               # File parsers and viewers
│   └── *.js                   # Legacy-compatible utilities
├── scanner_service/           # Secondary FastAPI implementation
└── test/                      # Node test runner tests
```

## Requirements

- Node.js 18 or newer
- MySQL running locally
- A database named `iris_db`, or a MySQL user allowed to create/use it

The default server connection is:

```js
host: 'localhost'
user: 'root'
password: ''
database: 'iris_db'
```

The server creates `records` and `saved_graphs` automatically when the database is reachable.

### Replace the database password

Do not commit passwords to this repository. The Node server reads its MySQL password from `DB_PASSWORD`.

For a local PowerShell session, set the password before starting the server:

```powershell
$env:DB_PASSWORD = 'your-new-password'
npm start
```

For Docker, update both `DB_PASSWORD` under the `app` service and `MYSQL_ROOT_PASSWORD` under the `mysql` service in [docker-compose.yml](docker-compose.yml) to the same value, then recreate the containers:

```powershell
docker compose down
docker compose up --build
```

If the MySQL volume already exists, changing `MYSQL_ROOT_PASSWORD` does not change the existing root account. Apply the new password inside MySQL first, or remove the development volume if its data can be discarded:

```sql
ALTER USER 'root'@'%' IDENTIFIED BY 'your-new-password';
ALTER USER 'root'@'localhost' IDENTIFIED BY 'your-new-password';
FLUSH PRIVILEGES;
```

## v5 Session Changes

This `v5` branch contains the Saved Dashboard Graphs workflow changes from this session:

1. **File filtering:** The FILE dropdown now preserves the selected record and filters the graph list so every displayed card belongs to that source file.
2. **Data-only Export:** The former `Export to MySQL` action is now labeled `Export`. Its non-database option downloads a `.txt` file containing SQL-formatted `CREATE TABLE` and `INSERT INTO` blocks generated only from saved graph metadata and category/value data. It does not render or serialize charts.
3. **Bulk Export:** The header `Export` button works with Select All and per-graph checkboxes to export multiple selected graphs in one operation.
4. **Print All:** The Saved Dashboard Graphs header includes `Print All`, which opens one combined printable view containing the existing chart and table print sheet for every selected graph.
5. **Unchanged paths:** `Database Export (write to live MySQL)` and the individual `Print Sheet` action were left unchanged while these features were added.

The text export keeps its `.txt` delivery format for compatibility, but its contents are valid SQL-style statements with title, source, and chart type comments. Print All reuses the existing single-graph printable template rather than the export path.

## Quick start

From the `IRIS` directory:

```powershell
npm install
npm start
```

Open the URL printed by the server, normally `http://localhost:3000`.

If port 3000 is already in use, the server selects another available port and prints it in the terminal.

## API

### Health

```text
GET /api/health
```

### Records

```text
GET    /api/records
POST   /api/records
PUT    /api/records/:id
DELETE /api/records/:id
```

### Saved graphs

```text
GET    /api/graphs
GET    /api/graphs/:recordId
POST   /api/graphs
DELETE /api/graphs/:id
```

## Testing

```powershell
npm test
```

The tests cover chart mapping, chart data aggregation, text pairing, filtering, pagination, graph exports, and structural contracts for the frontend.

## Design notes

- `app.js` is intentionally a small bootstrap and does not contain feature logic.
- Shared mutable frontend state lives in `js/modules/state.js`.
- Existing parser and utility scripts remain browser-compatible globals so the migration does not require a bundler.
- Draft visualizations are not automatically published. An administrator must review and save or approve them.
- `scanner_service/` is a separate Python/FastAPI path and is not required by the active Node.js runtime.
