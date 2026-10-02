# PHP Deployment Notes

This repository is the framework-free PHP application. Pages and APIs are served by Apache/PHP and use PDO to access the shared MySQL database. Tailwind CSS, Flowbite, Font Awesome, Apache ECharts, and browser parsing libraries are loaded by the frontend pages.

For the current V5.7.0 release changes and required schema migrations, see [`README_V5.7.0.md`](README_V5.7.0.md).

## Local XAMPP

Follow the full setup in [`README.md`](README.md). In brief: provision the supplied normalized `iris_db_3nf` database, configure `config/db.php`, start Apache/MySQL, and open the application under its document-root URL. Do not import the legacy `database.sql` or run historical migrations targeting `iris_db` against the normalized database.

The server-side runtime does not require Laravel, Composer, Node.js, or Python. Node.js is optional and is used only to run the tests with `node --test scanner/test/*.test.js`.

## Browser Processing

The Scanner uses browser-side JavaScript for spreadsheet ingestion and charting. Parser modules also support document text/viewing workflows for PDF and DOCX and image OCR support where the corresponding browser libraries are available. Smart Upload and chart recommendations do not call an external AI API.

## PHP API

- `api/iris.php` handles authenticated record and saved-graph operations.
- `api/dashboard_graphs.php` supplies only explicitly published (`saved_graphs.is_published = 1`) graphs to the public Observatory and sends no-cache headers.
- `config/db.php` centralizes PDO setup and change tracking; it does not create or alter schema objects.
- `api/templates.php` and `api/imports/` provide Super Admin template-driven import profiles, previews, guarded applies, audit batches, and recovery.
- `api/change_signal.php` exposes the authenticated shared write version used by active-page refresh polling.
- `api/iris.php` also handles Super Admin record-merge previews/applies and authenticated File History listing, download, and restore.

## Template-Driven Imports

Office uploads are parsed server-side as CSV, TSV, or XLSX by the existing native reader. The office upload form separates **Data and Report Visualization** from the Summary Cards and Ranking History import destinations. General visualization uploads require an active analytics template and enter the normal record-review workflow; the other destinations use their active import profiles. Summary Card imports require a stable `import_key` Global Label and merge against complete database history. Backfills never replace newer periods. Blank cells preserve values; `__CLEAR__` clears explicitly. New periods are unpublished and only explicit period publication changes the public current period. Ranking rows use their complete identity and block ambiguous matches. Workbook profiles can store expected headers and header-row configuration. Row versions reject stale previews, and import audit rows support recovery. No Composer dependency is required by the current reader.

Historical migrations in this repository target the retired `iris_db` schema and must not be run against `iris_db_3nf`. Apply only reviewed additive migrations explicitly targeting `iris_db_3nf`. Unified Summary Card imports need a stable Global Label column or a configured profile default. Equal period end dates sort by granularity (day, month, quarter, year).

The shared refresh client watches successful data changes on open pages. Super Admin tabs synchronize promptly; other roles poll every five seconds. A page with unsaved form or Studio edits defers the reload and displays a refresh prompt.

## File History Storage

Record merge and restore depend on the `record_file_history` table created by `migrations/20261003_create_record_file_history.sql`. The application stores supported workbook copies in a private `history` directory under the configured upload storage root. Keep that directory outside public web access, preserve it in backups, and ensure the PHP process can write to it. Restore also requires the referenced archived file to remain present.

## Publish and Unpublish

Record approval and graph publication are independent. The Studio Publish action updates `records.status` to `Approved` and saves its active chart with `saved_graphs.is_published = 1`. The Saved Graphs page publishes selected graph IDs without approving their source records. Observatory Unpublish sets that graph's `is_published` field to `0`; it does not delete the graph or its chart data.

Graph publish/unpublish requests use `POST /api/iris.php?resource=graphs&id={graphId}&action=publish` or `action=unpublish` with JSON `{ "published": true|false }`. `api/iris.php` requires authentication and checks for the `admin` role before mutation. The public dashboard endpoint is read-only and exposes only rows with `is_published = 1`.

The records archive also supports file-level publication. `POST /api/iris.php?resource=records&id={recordId}&action=unpublish` returns the record to `Pending Review` and sets every linked saved graph (`saved_graphs.record_id = records.record_id`) to unpublished in one transaction. Bulk archive actions use `POST /api/iris.php?resource=records&action=bulk-publish` or `action=bulk-unpublish` with JSON `{ "ids": ["record-id"] }`; each action updates selected records and their linked graphs atomically. These actions do not delete chart data. Summary cards are managed independently and are not linked to Scanner records.
