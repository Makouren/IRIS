# Frontend Modules

The frontend is initialized by [../app.js](../app.js), which creates a shared context containing the scanner, database manager, state, and module APIs.

## Shared state

[state.js](state.js) owns mutable cross-feature state, including:

- scan queue and active scan,
- Chart.js and ECharts instances,
- active studio record and filters,
- document viewer page, zoom, sheet, and search state.

Modules communicate through `ctx.api` callbacks. This avoids circular imports while keeping DOM lookups local to the module that owns the behavior.

## Module responsibilities

| Module | Responsibility |
| --- | --- |
| `navigation.js` | Scanner and admin view switching |
| `navigationTabs.js` | Scanner and admin tab switching |
| `fileIngestion.js` | File input, drag/drop, samples, progress, scanning |
| `queue.js` | Ingestion queue and active scan selection |
| `overviewTab.js` | Summary, extracted fields, takeaways |
| `viewerTab.js` | Basic scan viewer for image, spreadsheet, DOCX, and fallback text |
| `graphsTab.js` | Draft chart cards and draft export actions |
| `savedGraphsTab.js` | Saved chart list, export, print, and delete actions |
| `adminPortal.js` | Record statistics, archive table, search, and status filters |
| `studioWorkbench.js` | Studio record setup, field mapping, and studio orchestration |
| `documentViewer.js` | Acrobat-style document window, paging, zoom, and copy behavior |
| `tableGrid.js` | Editable studio table and row/column operations |
| `chartEngine.js` | ECharts rendering, filters, sorting, grouping, and row limits |
| `studioActions.js` | Studio add-field, add-row, save, and approve actions |
| `recordEditModal.js` | Record edit modal, table edits, raw text, save, and approval |

## Adding a module

1. Keep DOM queries inside the owning module.
2. Put cross-module mutable values in `state.js`.
3. Expose only the callback needed by another module through `ctx.api`.
4. Preserve existing DOM IDs and event semantics.
5. Run `npm test` after changing behavior.
