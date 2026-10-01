# IRIS V3.4.5 Release Notes

V3.4.5 extends the Super Admin and office workflows with template-driven spreadsheet imports and cross-page data refresh signaling.

## Template Imports

- Super Admins configure import profiles with header mappings, required fields, and defaults.
- Office uploads can be previewed before approval; writes require the explicit “I reviewed this diff” acknowledgement.
- Summary cards use their Global Label (`import_key`) as the stable identity. Changed periods are archived in `summary_card_snapshots`; one row per label is selected for the current card, defaulting to the newest period.
- Ranking History previews use the full identity tuple and block ambiguous matches. Legacy ranking candidates are opt-in.
- Row versions reject stale approvals. Import batches and row states provide guarded recovery.
- Server-side imports support CSV, TSV, and XLSX through the existing native reader.

## Refresh Behavior

Successful data writes increment the shared app version. Super Admin tabs synchronize promptly, while other roles poll every five seconds. Pages with unsaved edits defer refresh and show a manual refresh prompt.

## Database Updates

After the earlier schema migrations, apply:

- `migrations/20261002_template_driven_imports.sql`
- `migrations/20261002_allow_builtin_snapshot_imports.sql`
- `migrations/20261002_create_app_change_state.sql`

The app also ensures `app_change_state` exists when connecting to the database.

## Manual Verification

1. Configure a Summary Cards profile and import several years under one Global Label. Confirm one live row is selected and other changed periods are archived.
2. Change the selected row and approve; confirm the existing card updates without a duplicate.
3. Re-preview unchanged data and confirm it reports no changes.
4. Preview ranking data with a duplicate identity and confirm the row is blocked.
5. Revert an import batch and confirm prior values are restored.
6. Open multiple role sessions, make a Super Admin change, and confirm Super Admin pages refresh promptly and other roles refresh within five seconds. Unsaved edits should defer refresh.

See [README.md](README.md) for setup and architecture, and [README_PHP.md](README_PHP.md) for PHP deployment details.