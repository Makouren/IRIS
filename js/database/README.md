# Database and Persistence

[dbManager.js](dbManager.js) is the browser-side persistence adapter. The server-side implementation is [../../server.js](../../server.js).

## Persistence layers

1. MySQL through the Express REST API is the canonical store when available.
2. IndexedDB stores browser records for offline continuity.
3. localStorage provides a lightweight backup for records.

Record reads prefer the server. Failed server calls fall back to browser storage where supported.

## Stored entities

### `records`

Stores file identity, type, size, scan date, status, extracted data, graph drafts, metadata, and admin notes.

### `saved_graphs`

Stores cleaned chart snapshots linked to a record through `record_id`, including title, chart type, labels, values, and creation time.

## Graph operations

`DatabaseManager` exposes:

- `saveGraph()` for cleaned studio charts,
- `getGraphsByRecord()` for draft/saved chart rendering,
- `getAllSavedGraphs()` for the saved graph admin view,
- `exportGraph()` for normalized draft export,
- `printGraphSheet()` for free printable output,
- `deleteGraph()` for saved graph removal.

## API endpoints

```text
GET    /api/records
POST   /api/records
PUT    /api/records/:id
DELETE /api/records/:id
GET    /api/graphs
GET    /api/graphs/:recordId
POST   /api/graphs
DELETE /api/graphs/:id
```

The server creates the required tables during startup. The default connection is configured directly in `server.js` for the current internal deployment.
