# IRIS V3.3.0 Change History

This is the historical V3.3.0 record. Current release changes are documented in [README_V3.4.5.md](README_V3.4.5.md).

## Scope

This document summarizes the IRIS phases beginning with account separation and office spreadsheet review, continuing through the ranked-chart correction, and including the latest chart, publication, and navigation work present on branch `V3.3.0`.

The existing [README.md](README.md) remains the general setup and architecture guide. This file is the release-oriented history for this phase.

## Version Control Context

- Branch: `V3.3.0`
- Branch point: commit `cbfdb95` (`Fix ranked chart value handling`, 2026-10-01)
- Account-separation phase: commit `4f61f72` (`Implement IRIS account separation and spreadsheet review`, 2026-10-01)
- The worktree already contained other modified and untracked files when the branch was created. Those files were preserved; this document attributes only the phases described below and does not claim every dirty file is part of this release.
- No tests or application/browser checks were run during the latest chart changes under the task's verification restriction. Editor diagnostics and source inspection were used instead.

## Earlier Context: Rankings and Ranking History

These ranking-data commits are earlier than the account-separation phase below, but form part of the V3.3.0 codebase foundation.

- Added ranking scopes, ranking history data, and seeded CLSU ranking records.
- Added Super Admin ranking-body and ranking-review controls.
- Updated the public dashboard to display ranking history with Apache ECharts.
- Corrected Ranking History series mapping and display behavior.

Key files:

- `migrations/20260930_add_ranking_scopes.sql`
- `migrations/20260930_extend_rankings_for_history_seed.sql`
- `migrations/20260930_seed_demo_ranking_history.sql`
- `migrations/seed_clsu_ranking_history.php`
- `api/admin_rankings.php`
- `admin/js/rankingBodyManager.js`
- `admin/js/rankingReview.js`
- `user/dashboard.php`

## Phase 1: Account Separation and Office Review

The account-separation phase introduced the office-based account and record-upload workflow while retaining a separate Super Admin role.

- Expanded user roles to distinguish `super_admin`, `admin`, and `user`.
- Added office account metadata and active/inactive account status.
- Associated uploaded records with the uploading user and office, with timestamps and review status.
- Added the office upload flow: spreadsheets are validated, parsed, stored outside the web root, and queued as `Pending Review`.
- Added account management, office templates, template downloads, and template-to-ranking-body association.
- Added/updated authentication and password-change flows for the separated account model.
- Connected reviewed records to the Review Editor and spreadsheet data grid.

Key files:

- `migrations/20261001_account_separation.sql`
- `admin/office_upload.php`
- `admin/upload_process.php`
- `admin/upload_source.php`
- `includes/SpreadsheetReader.php`
- `admin/js/officeTemplates.js`
- `admin/js/accountManager.js`
- `admin/js/templateManager.js`
- `api/accounts.php`
- `api/templates.php`
- `api/template_reviews.php`
- `auth/register.php`, `auth/login.php`, and `auth/change_password.php`
- `admin/review_editor.php`
- `scanner/js/modules/adminPortal.js`
- `scanner/js/modules/studioWorkbench.js`

## Phase 2: Ranked Chart Value Correction

Commit `cbfdb95` corrected how ranked chart values are transformed and displayed.

- Preserved real rank values separately from visual values used to draw bars.
- Updated ranked chart mapping and axis behavior so lower rank numbers retain their intended meaning.
- Kept rank labels/tooltips tied to real values rather than transformed drawing values.

Key files:

- `scanner/js/chartMapping.js`
- `scanner/js/modules/chartEngine.js`

## Phase 3: Shared Chart Builder and Persistence Alignment

The current chart work consolidates Studio, Saved Graphs, and the public Observatory around one shared ECharts option builder.

- Added `buildChartOption` and `buildSavedGraphOption` to `chartEngine.js`.
- Routed Studio preview and saved graph cards through the shared builder.
- Changed the public Observatory graph cards to use the same saved-graph builder instead of an independent chart-type fallback.
- Added/retained chart configuration in `chart_data.irisConfig`, including field mappings, axis settings, rank metadata, and display precision.
- Made labels and `values_data` the saved source of truth for modern chart records; saved ECharts output remains available for legacy rows and multi-series reconstruction.
- Expanded the public graph payload to include `chart_data`, graph metadata, and source file information.
- Added `chart_data` to the base saved-graph schema and an idempotent migration for installations that lack that column.
- Updated exports and print data to retain real values and include group/category/value rows for Nested Pie.

Key files:

- `scanner/js/modules/chartEngine.js`
- `scanner/js/modules/studioWorkbench.js`
- `scanner/js/modules/savedGraphsTab.js`
- `scanner/js/modules/graphsTab.js`
- `scanner/js/chartData.js`
- `scanner/js/graphExport.js`
- `scanner/js/database/dbManager.js`
- `api/iris.php`
- `api/dashboard_graphs.php`
- `database.sql`
- `migrations/20261001_unify_saved_graph_types.sql`

## Phase 4: Supported Chart Types and Legacy Chart Handling

The current supported chart types are Bar, Line, Pie, Doughnut, Ranked Bar, and Nested Pie.

- Added Nested Pie with a group-field mapping and an explicit empty state when no group field is selected.
- Removed Nightingale Rose from selectors, rendering, draft choices, color controls, documentation, and print/export code.
- Legacy `polarArea`, `rose`, and `nightingale` saved type identifiers now fall back to Bar at API/read/render boundaries.
- Added a follow-up migration to convert already-saved Rose/Nightingale rows to Bar and remove the old `roseMode` config property.
- Kept the existing saved-graph `chart_type` column as `VARCHAR`; it does not require an enum alteration.

Key files:

- `admin/review_editor.php`
- `scanner/index.php`
- `scanner/js/modules/chartEngine.js`
- `scanner/js/modules/studioWorkbench.js`
- `scanner/js/modules/studioColorCustomizer.js`
- `scanner/js/modules/graphsTab.js`
- `scanner/js/modules/savedGraphsTab.js`
- `scanner/js/graphExport.js`
- `api/iris.php`
- `api/dashboard_graphs.php`
- `migrations/20261001_unify_saved_graph_types.sql`
- `migrations/20261001_remove_nightingale_chart.sql`

## Phase 5: Saved Graph and Theme Behavior

- Changed Select All to update checkboxes and selection state without rebuilding all chart cards.
- Changed Saved Graphs theme handling to rebuild options on existing chart instances rather than rerendering the full list.
- Added chart disposal/resize-observer cleanup before Saved Graph cards are replaced.
- Updated Saved Graph type labels and ensured the current type list excludes Nightingale.
- Updated dashboard chart resizing to use the ranking-chart instance collection that actually exists.
- Applied shared theme tokens to Ranking History chart colors, axes, grids, and tooltips.

Key files:

- `scanner/js/modules/savedGraphsTab.js`
- `scanner/js/modules/chartEngine.js`
- `user/dashboard.php`

## Phase 6: Admin Portal Navigation Cleanup

Removed visible Admin Portal links from the public dashboard dropdowns and standalone Scanner navigation. The underlying admin pages and permissions remain in place; this change removes the navigation entry points, not the admin features themselves.

Key files:

- `user/dashboard.php`
- `scanner/index.php`

## Workflow Reference Document

A separate Notepad-friendly path/code map was added for locating the Office Upload, Review Editor, Saved Graphs, public view, and shared chart files:

- `IRIS_WORKFLOW_FILES_AND_CODE.txt`

## Verification Status

- Editor diagnostics reported no errors for the changed PHP/JavaScript source files.
- `git diff --check` reported no whitespace errors; Git printed a line-ending notice for `admin/review_editor.php`.
- Automated tests, application execution, server startup, and browser checks were not run for the latest chart work.
