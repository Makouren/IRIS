# IRIS V4.5.0 Release Notes

V4.5.0 expands template-driven office uploads and Ranking History management while keeping ranking and Summary Card import sources separate from the general Review Editor dataset.

## Office Upload Destinations

- Adds **Data and Report Visualization** as the general office upload purpose. It uses the existing analytics record and chart-review workflow and requires an active analytics template.
- Keeps **Summary Cards** and **Ranking History** as dedicated import destinations that use their active profiles.
- Filters Ranking History and Summary Card source workbooks out of the main Review Editor's dataset selector, counts, archive, and bulk record actions.

## Ranking History

- Adds the built-in Unified Ranking History import profile and profile-driven workbook parsing.
- Supports workbook upload, worksheet and header-row selection, expected-header mapping, and validation before office uploads are accepted.
- Adds selected-row import review with stale-preview checks, audit batches, and guarded recovery.
- Adds Ranking History search across organization, ranking type, year, rank, and information text.
- Adds ranking information text and persisted public chart defaults for organization and list selection.

## Summary Cards and Administration

- Adds profile workbook metadata and validation to the Summary Card import workflow.
- Retains stable Global Label identities, period history, explicit publication, and guarded import recovery.
- Updates the public dashboard and Super Admin tools to use the dedicated Ranking History and Summary Card workflows.
- Adds Apache rules to restrict access to migrations and non-public project files.

## Database Migrations

Historical migrations from before the 3NF conversion target `iris_db`; do not apply them to `iris_db_3nf`. The V4.5.0 application expects the normalized schema to be provisioned separately. Its reviewed additive migrations are:

- `migrations/20261002_seed_super_admin_role.sql`
- `migrations/20261004_add_normalized_ranking_display_values.sql`

The normalized ranking-display migration preserves source text such as rank ranges and suffixes separately from numeric sort values. The PHP bootstrap does not create or modify schema objects.

## Verification

Run the PHP import-mapping test and the dependency-free JavaScript suite from the repository root:

```powershell
php scanner/test/templateImportMapping.test.php
node --test scanner/test/*.test.js
```

Against local XAMPP/MySQL, verify all three upload purposes, template filtering, import preview and apply, Ranking History search and defaults, and the Review Editor's separation of import-only workbooks.

See [README.md](README.md) for setup and architecture, [README_PHP.md](README_PHP.md) for deployment details, and [README_V3.4.7.md](README_V3.4.7.md) for the preceding documented release.