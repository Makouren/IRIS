# Database and Persistence

> Current application release: [V6.7.0](../../../README_V6.7.0.md).

IRIS supports Super Admin record merge and File History snapshots, including private source-workbook copies for supported formats. Ranking and Summary Card source files remain separate from the general Review Editor record list. Custom mapped import fields, Summary Card history, saved graph configuration, and publication state persist through the PHP/MySQL pipeline. The authenticated `api/change_signal.php` endpoint lets active pages detect successful writes; Super Admin pages synchronize promptly and other roles poll every five seconds. See [`README_V6.7.0.md`](../../../README_V6.7.0.md) for current migration guidance and verification.

[`dbManager.js`](dbManager.js) is the Scanner's browser-side persistence adapter. It communicates with the PHP/PDO API in `api/iris.php`; there is no Express server.

## Storage

- MySQL is the canonical store when the authenticated PHP API is available.
- IndexedDB and localStorage provide browser-side fallback for records; IndexedDB also carries pending upload files between admin pages.
- Saved graph snapshots are linked to records through `record_id`.
- `records.status` tracks record review/approval. `saved_graphs.is_published` independently controls public visibility; approving a record does not publish every graph attached to it.

The PHP runtime connects to the normalized `iris_db_3nf` schema through `config/db.php`; it does not create or alter tables. `database.sql` and historical migrations targeting `iris_db` describe a retired schema and must not be used to provision the current release.

## Saved Graph Data

Saved graphs store chart type, labels, real values, chart configuration, rank and axis metadata, colors, publication state, and creation time. Supported types are Line, Stacked Area, Bar, Pie, Doughnut, and Nested Pie. Unknown or retired chart types render as Bar. Publication is explicit and is not inferred from record status.

## PHP API Routes

All routes use `api/iris.php` with query parameters:

```text
GET/POST       /api/iris.php?resource=records
GET/PUT/DELETE /api/iris.php?resource=records&id={recordId}
GET/POST       /api/iris.php?resource=graphs
GET/DELETE     /api/iris.php?resource=graphs&id={graphId}
GET            /api/iris.php?resource=graphs&record_id={recordId}
POST           /api/iris.php?resource=graphs&id={graphId}&action=publish
POST           /api/iris.php?resource=graphs&id={graphId}&action=unpublish
POST           /api/iris.php?resource=graphs&action=export
```

Graph publish/unpublish requests send JSON `{ "published": true|false }`. `api/iris.php` requires a signed-in admin for mutations and returns the persisted graph ID and publication state. The public Observatory reads only rows with `saved_graphs.is_published = 1` from `api/dashboard_graphs.php`; that endpoint disables caching so state changes are visible on refresh.

## Graph Operations

`DatabaseManager` provides record CRUD, graph save/read/delete/publish, database-copy export, and printable sheet helpers. Studio Publish approves the record and saves only the active chart as published. Saved Graphs Publish changes only selected graph rows; Observatory Unpublish clears the same `is_published` field without deleting chart data. SQL-formatted text export is built in `../modules/savedGraphsTab.js` from loaded graph data and is separate from database export. Print Sheet and Print All use `../graphExport.js`.

Super Admin record merge and File History use the authenticated `record_file_history` API resource. Merge writes pre-merge and post-merge snapshots; restores append pre-restore and restore-result snapshots. Workbook copies are stored under private upload storage and referenced by generated keys and SHA-256 checksums. The table migration and the private upload directory are required for these operations.
