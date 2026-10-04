# IRIS V5.7.0 Release Notes

> Current release: [V6.7.0](README_V6.7.0.md). The V5.7.0 notes below are historical.

V5.7.0 extends the PHP/MySQL observatory with Super Admin record merge and File History, expanded import mapping, and improvements to the Review Editor and public dashboards.

## File Archives and Record Merge

- Adds explicit source-to-target spreadsheet merge previews. The selected target remains the surviving record; source data is merged into it and the source record is removed only when the merge is applied.
- Captures pre-merge and post-merge record snapshots and supported source workbooks in File History.
- Adds File History inspection, archived workbook download, and restore actions. Restore records the current and restored states rather than silently replacing history.
- Adds private workbook snapshot storage and checksum metadata for archived files.

## Imports and Administration

- Adds Super Admin-configurable custom fields to Summary Card and Ranking History import profiles, allowing extra workbook columns to be mapped and retained with imported data.
- Lists destination-profile template workbooks in Office Upload and improves destination-specific import mapping and validation.
- Adds category management and a public default-category selector for Summary Cards. The default selector reflects the available categories and disallows categories without published cards.
- Keeps Summary Card, Ranking History, and analytics uploads in their separate destination workflows.

## Review Editor and Observatory

- Adds editing and reopening of saved graph configurations from the Review Editor.
- Adds standalone per-field chart-color saving and consistent rendering of saved colors.
- Improves Field Mapping spacing, chart color controls, and responsive Review Editor layout.
- Refines the Summary Card manager dialog and category actions without requiring a page reload after category creation.
- Retains explicit publication state for records, saved graphs, Summary Cards, and Ranking History.

## Database and Deployment

The application uses the normalized `iris_db_3nf` database. `config/db.php` does not create or alter database objects. Back up the database and verify the target schema before applying migrations.

New V5.7.0 feature storage requires these migrations:

- [`migrations/20261003_create_record_file_history.sql`](../../migrations/20261003_create_record_file_history.sql) creates the File History table used by record merge, history, and restore.
- [`migrations/20261005_custom_import_fields.sql`](../../migrations/20261005_custom_import_fields.sql) adds nullable JSON storage for custom profile, ranking, and Summary Card fields.

Do not apply historical migrations that target the retired `iris_db` schema to `iris_db_3nf`. File History also needs writable private upload storage for supported workbook snapshots; do not expose that directory through the web server.

## Verification

From the repository root:

```powershell
node --test scanner/test/*.test.js
php scanner/test/templateImportMapping.test.php
php scanner/test/recordFileHistory.test.php
```

Also lint the changed PHP files with `php -l`. Authenticated XAMPP/MySQL checks should cover upload destinations and custom-field mappings, merge previews and direction, File History download and restore, Review Editor/Saved Graphs behavior, and the public dashboards. Browser and database smoke checks must be recorded separately from automated test results.

See [README_V6.7.0.md](README_V6.7.0.md) for the current release, [README.md](../../README.md) for setup and architecture, and [README_PHP.md](../README_PHP.md) for deployment notes.
