# IRIS V3.4.5 Release Notes

> Current release: [V6.7.0](README_V6.7.0.md). The V3.4.5 notes below are historical.

V3.4.5 extends the Super Admin and office workflows with template-driven spreadsheet imports and cross-page data refresh signaling.

## Template Imports

- Super Admins configure import profiles with header mappings, required fields, and defaults.
- Office uploads can be previewed before approval; writes require the explicit “I reviewed this diff” acknowledgement.
- Summary cards require an explicit Global Label (`import_key`) as the stable identity. Periods are canonicalized and compared against complete database history; older imports backfill history without moving current state.
- Canonical keys distinguish year, quarter, month, day, and legacy display periods; equal end dates sort by granularity, with day after month, month after quarter, and quarter after year. Excel serial 60 is rejected because it is not a real date.
- Snapshot rows contain period-specific titles, labels, values, content, publication, and provenance. New periods remain unpublished; the public card uses the latest explicitly published period.
- Blank fields preserve prior period values; the explicit `__CLEAR__` marker clears a field.
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
- `migrations/20261002_summary_card_history_v2.sql`

Built-in Snapshot files must include a `Global Label` column (or the template profile must provide an explicit default). Do not use changing descriptive labels as card identities.

The app also ensures `app_change_state` exists when connecting to the database.

## Manual Verification

1. With 2026 published, import only 2024. Confirm 2024 is added as a backfill and 2026 stays public/current.
2. Import a newer period. Confirm it stays unpublished until explicitly published; publishing it makes it current and unpublishing restores the prior published period.
3. Import unchanged data repeatedly and confirm no duplicate or unnecessary update occurs.
4. Test quarter, month, date, mixed-granularity, and Excel-serial periods, including an invalid date and serial 60.
5. Correct and publish a period in the Admin history manager; verify provenance and rollback records.
6. Open published history on the Observatory and confirm unpublished periods are hidden and viewing history leaves the current card unchanged.
7. Revert an import batch and confirm complete period state and provenance are restored.

See [README_V6.7.0.md](README_V6.7.0.md) for the current release, [README.md](README.md) for setup and architecture, and [README_PHP.md](README_PHP.md) for PHP deployment details.