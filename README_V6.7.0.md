# IRIS V6.7.0 Release Notes

V6.7.0 improves office workbook management, Ranking History imports, and the public Observatory while retaining the normalized PHP/MySQL application architecture.

## Office Templates and Imports

- Adds a readable saved-workbook preview for Super Admins, with sheet names, headers, sample rows, and explicit close controls.
- Keeps workbook profile configuration available for Summary Cards and Ranking History and applies configured custom fields during import mapping.
- Improves import diagnostics to identify the blocked field and its mapped worksheet column.
- Skips rows whose mapped values are all blank and does not persist empty custom-field values.
- Supports long Ranking History context text and raises Organization handling to 512 characters.

## Ranking History and Observatory

- Keeps Ranking Trend Matrix separate from explanatory Ranking Context.
- Shows only the latest non-empty Ranking Context in the collapsible “What this ranking means” section.
- Keeps the ⓘ Information popup separate from Ranking Context and excludes it from the popup's content checks.
- Retains chart filtering and matrix popouts while avoiding duplicate matrix-style context panels.

## Database and Deployment

IRIS connects to the normalized `iris_db_3nf` database. The PHP bootstrap does not create or alter database objects. The repository's `database.sql` is a retired-schema warning stub, not a current database export. This release includes reviewed migration scripts rather than a live-data backup; the available backup contains data and is not part of the release.

Back up the target database and inspect its schema before applying any migration. Apply only those changes not already present:

- [`migrations/20261003_create_record_file_history.sql`](migrations/20261003_create_record_file_history.sql) adds File History storage for merge and restore.
- [`migrations/20261003_ranking_history_published_flag.sql`](migrations/20261003_ranking_history_published_flag.sql) adds Ranking History visibility control.
- [`migrations/20261003_template_destination.sql`](migrations/20261003_template_destination.sql) adds office-upload template destinations.
- [`migrations/20261003_template_profile_workbooks.sql`](migrations/20261003_template_profile_workbooks.sql) stores workbook profile metadata.
- [`migrations/20261005_custom_import_fields.sql`](migrations/20261005_custom_import_fields.sql) adds custom-field storage for Ranking History and Summary Card imports.
- [`migrations/20261006_expand_ranking_context_text.sql`](migrations/20261006_expand_ranking_context_text.sql) expands organization names and Ranking History information text in `iris_db_3nf`.

Do not run historical migrations that select or modify the retired `iris_db` schema against `iris_db_3nf`. The migration folder is not read at application startup, but it is needed to bring an existing database schema up to the version expected by enabled features.

## Verification

From the repository root:

```powershell
node --test scanner/test/*.test.js
php scanner/test/templateImportMapping.test.php
php scanner/test/recordFileHistory.test.php
```

Also run `php -l` on changed PHP files and verify the reviewed migrations and upload, preview, and Ranking History flows against a backed-up local `iris_db_3nf` database. Automated tests do not apply migrations or replace authenticated browser/database smoke tests.

See [README.md](README.md) for setup and architecture and [README_PHP.md](README_PHP.md) for deployment details.
