# IRIS V5.3.0 Release Notes

V5.3.0 builds on the normalized PHP/MySQL application with a destination-aware File Archives workspace, shared CLSU visual styling, richer Ranking History defaults, and grouped published visualizations.

## Office Uploads and File Archives

- Reuses the shared CLSU Observatory header, dotted background, light/dark theme, container sizing, cards, form controls, table, and status badges on the IAO Office Upload page.
- Office users select Data & Report Visualization, Summary Cards, or Ranking History from a template-category dropdown. Summary Card and Ranking History uploads use their active destination profiles automatically; general visualization keeps its template selection.
- Adds an authenticated IAO-admin-only Back to Uploads control in the public header. Super Admins retain their Edit control.
- Moves imported files into a dedicated Super Admin File Archives tab with purpose, office, status, and search filters. The Records & Dashboard Studio list remains separate from Summary Card and Ranking History imports.

## Observatory and Ranking History

- Widens aligned public, scanner, and admin containers for large displays while retaining fluid gutters and responsive layouts.
- Adds a CLSU green/gold cursor-reactive dotted background with static touch and reduced-motion fallbacks.
- Groups published graphs by editable scope; unassigned graphs appear under General.
- Orders ranking bodies with editable sort order, initially prioritizing WURI, QS, and Webometrics. Ranking years remain chronological.
- Allows Super Admins to choose a default ranking list from any organization. A viewer's organization/list filters take precedence.
- Presents summary snapshots and Ranking History in distinct containers, adds accessible information popovers, and separates ranking charts into individual cards.

## Scanner and Administration

- Adds editable saved-graph scope and ranking-body display order.
- Lets the active Studio color picker close by selecting its current swatch or managed field again.
- Removes summary-card editing from the public dashboard; creation and management remain in Review Editor.
- Adds a bulk Publish All action to the Review Editor summary-card manager.

## Database Migrations

The runtime uses the normalized `iris_db_3nf` schema; `database.sql` and historical migrations targeting `iris_db` are not its setup path. Review each migration against the deployed schema before applying it. Migrations are one-time schema operations; deleting a migration file does not roll back changes already applied to MySQL.

V5.3.0 schema changes include:

- `migrations/20261002_allow_builtin_import_profiles.sql`
- `migrations/20261002_seed_super_admin_role.sql`
- `migrations/20261002_summary_card_values_text.sql`
- `migrations/20261004_add_normalized_ranking_display_values.sql`
- `migrations/20261004_add_ranking_body_order_and_graph_scope.sql`

The final V5.3.0 migration adds `ranking_bodies.sort_order` and `saved_graphs.scope`; the application expects both columns. Apply it only to the configured database after confirming it has the corresponding tables and current columns.

## Verification

Manual checks should cover the IAO upload destinations and theme toggle, role-gated Back to Uploads navigation, File Archives purpose filtering and row actions, ranking defaults and viewer filters, saved-graph scope editing, and public visualization grouping. Browser automation and automated tests were not run for this release preparation.

See [README.md](README.md) for setup and architecture and [README_PHP.md](README_PHP.md) for PHP deployment notes. Previous release notes remain in [README_V4.5.0.md](README_V4.5.0.md), [README_V3.4.7.md](README_V3.4.7.md), [README_V3.4.5.md](README_V3.4.5.md), and [README_V3.3.0.md](README_V3.3.0.md).
