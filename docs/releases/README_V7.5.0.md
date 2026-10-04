# IRIS v7.5.0

This release combines the completed project-structure phases with the pending
workflow improvements on the `V7.5.0` branch. It supplements the root
[README](../../README.md) and earlier release notes in this folder.

## Project structure

- Extracted the Review Editor's inline feature scripts and modal markup into
  page-scoped files and includes.
- Extracted the user dashboard CSS and JavaScript into page-scoped assets.
- Split the shared scanner stylesheet into ordered files and centralized its
  versioned asset list.
- Split API resource handling into handler files while keeping
  `api/iris.php` as the public router.
- Grouped loose documentation, PHP includes, and scanner scripts into
  purpose-specific folders; updated references and added folder guides.
- Added project-map and maintenance documentation. Corrected the relocated
  portal navigation asset's filesystem path used for cache versioning.

## Workflow and data changes

### General records and sheet merging

- Allow merging when both records are General (without a linked template).
- Continue to allow records with the same linked template, and reject a
  General/templated pair or records with different templates.
- Apply the compatibility rule in the API and the Studio picker so incompatible
  records are not offered for selection.
- Extend sheet merge handling for partial header overlap and identity fields.
- Add guarded cleanup for data created or updated by source imports. Cleanup
  checks the current row against its upload audit snapshot and protects newer
  changes instead of silently deleting or overwriting them.

### Workbook imports and template profiles

- Allow office workbook uploads to proceed as General when no template is
  selected, with template assignment remaining an available later step.
- Expand saved import-profile workflows for Summary Cards and Ranking History,
  including workbook and field mapping controls.
- Improve custom-field mapping controls and validation in the profile editor.
- Improve handling of profile and template downloads in the admin interface.

### File Archives and Ranking History

- Add bulk publication controls for archived records.
- Improve Ranking History grouping, filtering, and publication actions.
- Integrate source-upload cleanup with record/file history handling, including
  safeguards against removing data that has changed since the upload.

### Graph rendering and exports

- Keep chart configuration and appearance consistent between Review Studio,
  public graph views, saved graphs, and print/export output.
- Update graph data, rendering, and export paths to use the shared chart
  configuration and preserve the relevant display options.

### Account and navigation workflows

- Replace the standalone password-change page with an authenticated,
  CSRF-protected password-change API endpoint used by the shared navigation
  dialog.
- Propagate refresh events for changes that affect Ranking History.

## Database and deployment

- No schema changes or migration scripts are included in this release.
- The local-only edit to `config/db.php` is not part of the release commit;
  database credentials and connection settings must be configured for each
  environment and must not be copied from a developer workstation.
- Existing migrations remain unchanged. Review the project's migration
  guidance before applying any migration to a deployment database.

## Validation

- PHP syntax check: 82 files passed.
- Non-vendor JavaScript syntax check: 60 files passed.
- Existing PHP tests: all 3 test files passed.
- Existing Node tests: 146 total, 136 passed and 10 failed. The same 10 test
  cases failed in the Phase 1 baseline, which had 144 total and 134 passed.
- `git diff --check` passed.
- The release changes were not manually browser-smoke-tested as a whole. In
  particular, recheck the authenticated upload, archives, merge, graph, and
  password-change workflows before deploying.
