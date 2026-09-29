# PHP Deployment Notes

This repository is the framework-free PHP application. Pages and APIs are served by Apache/PHP and use PDO to access the shared MySQL database. Tailwind CSS, Flowbite, Font Awesome, Apache ECharts, and browser parsing libraries are loaded by the frontend pages.

## Local XAMPP

Follow the full setup in [`README.md`](README.md). In brief: create `iris_db`, import `database.sql`, adjust `config/db.php` for the local connection, start Apache/MySQL, then open `http://localhost/iris/`.

The server-side runtime does not require Laravel, Composer, Node.js, or Python. Node.js is optional and is used only to run the tests with `node --test scanner/test/*.test.js`.

## Browser Processing

The Scanner uses browser-side JavaScript for spreadsheet ingestion and charting. Parser modules also support document text/viewing workflows for PDF and DOCX and image OCR support where the corresponding browser libraries are available. Smart Upload and chart recommendations do not call an external AI API.

## PHP API

- `api/iris.php` handles authenticated record and saved-graph operations.
- `api/dashboard_graphs.php` supplies only explicitly published (`saved_graphs.is_published = 1`) graphs to the public Observatory and sends no-cache headers.
- `config/db.php` centralizes PDO setup and scanner table creation/compatibility migration.

## Publish and Unpublish

Record approval and graph publication are independent. The Studio Publish action updates `records.status` to `Approved` and saves its active chart with `saved_graphs.is_published = 1`. The Saved Graphs page publishes selected graph IDs without approving their source records. Observatory Unpublish sets that graph's `is_published` field to `0`; it does not delete the graph or its chart data.

Graph publish/unpublish requests use `POST /api/iris.php?resource=graphs&id={graphId}&action=publish` or `action=unpublish` with JSON `{ "published": true|false }`. `api/iris.php` requires authentication and checks for the `admin` role before mutation. The public dashboard endpoint is read-only and exposes only rows with `is_published = 1`.

The records archive also supports file-level publication. `POST /api/iris.php?resource=records&id={recordId}&action=unpublish` returns the record to `Pending Review` and sets every linked saved graph (`saved_graphs.record_id = records.id`) to unpublished in one transaction. Bulk archive actions use `POST /api/iris.php?resource=records&action=bulk-publish` or `action=bulk-unpublish` with JSON `{ "ids": ["record-id"] }`; each action updates selected records and their linked graphs atomically. These actions do not delete chart data. Summary cards are managed independently in `summary_cards` and are not linked to Scanner records.
