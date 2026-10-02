# IRIS V3.4.7 Release Notes

V3.4.7 highlights the **Unified Summary Cards** template, a reusable import profile that standardizes how office spreadsheets populate Observatory snapshot cards.

## Unified Summary Cards

- Adds a built-in `Unified Summary Cards` profile that is not tied to one uploaded office template.
- Lets Super Admins select the active Summary Card import profile, including eligible template-specific profiles.
- Maps common spreadsheet columns such as `Global Label`, `Title`, `Main Value`, `Main Label`, and `Year / Date` to the Summary Card data model.
- Supports optional `Categories` and `Display Precision` columns, as well as secondary values, descriptions, and information text.
- Uses Global Label as the stable card identity by default, so periods update one card's history instead of creating a new card for each year.
- Associates eligible existing template-less Summary Card uploads with the active profile during migration.

Imports continue to use the review-before-apply workflow, optimistic row versions, audit batches, and the established history/publication rules. Historical backfills do not replace newer periods, new periods remain unpublished until explicitly published, and blank values preserve existing data unless `__CLEAR__` is supplied.

## Database Updates

After the existing template-import and Summary Card history schema migrations, apply:

- `migrations/20261002_unified_summary_card_profiles.sql`
- `migrations/20261002_summary_card_category_precision_mapping.sql`

The unified profile migration creates the built-in profile and active-profile setting, and adds the profile reference for uploads. The follow-up migration ensures the category and precision mappings are present. Apply these before using the unified profile on an existing installation.

## Verification

Run the focused mapping test and the JavaScript suite from the repository root:

```powershell
php scanner/test/templateImportMapping.test.php
node --test scanner/test/*.test.js
```

Against local XAMPP/MySQL, verify that a Super Admin can select the Unified Summary Cards profile, preview and apply a spreadsheet with Global Label and Year / Date, and see the imported card periods in history. Also check optional categories and precision, blank-field preservation, explicit clearing, historical backfill, and publication behavior.

See [README.md](README.md) for setup and architecture, [README_PHP.md](README_PHP.md) for deployment details, and [README_V3.4.5.md](README_V3.4.5.md) for the preceding release notes.