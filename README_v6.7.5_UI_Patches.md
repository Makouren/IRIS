# v6.7.5 UI Patches

This document records the IRIS v6.7.5 UI and workflow changes made across the patch session. It supplements, and does not replace, the main [README](README.md) or the [v6.7.0 release notes](README_V6.7.0.md).

## Summary

The patch set focuses on consistent navigation across the Observatory, Super Admin, and Office Upload areas; clearer dark-mode styling; reduced document-action clutter; safer uploads; and restoration of normal-user registration.

## Changes

### Shared navigation and responsive layouts

- Consolidated the role-aware navigation into a shared PHP component, used by the Observatory, Super Admin pages, and Office Upload.
- Added shared navigation styles and JavaScript for responsive menu behavior, profile dropdown actions, and the Office Upload theme toggle.
- Matched the Office Upload header controls to the Super Admin visual treatment while retaining role-specific labels and links.
- Removed redundant links to the page currently being viewed, including the Observatory link on the Observatory and the Office Upload link on the Office Upload page.
- Adjusted the compact-menu breakpoint and logo sizing to reduce collisions at tablet and narrow viewport widths.
- Removed legacy Admin header rules that conflicted with shared responsive styles.
- Fixed a malformed URL expression in the shared navigation include that caused PHP to stop rendering pages using the component.

### File Archives and document actions

- Replaced the row of separate File Archives actions with a grouped action menu.
- Kept the existing role permissions, file-state conditions, routes, request handlers, and delete confirmation behavior.
- Added keyboard and outside-click handling for the action menu.

### Office uploads and templates

- Added a shared 10 MiB upload limit and server-side checks for upload errors, file extensions, MIME types, and file signatures.
- Added client-side size checks and upload guidance at relevant upload controls.
- Added `.user.ini` defaults for `upload_max_filesize = 10M` and `post_max_size = 12M`, plus an access rule preventing direct requests for `.user.ini`.
- Improved import mapping and workbook handling in the Summary Card and Ranking History template flows.
- Added guidance for configuring equivalent PHP limits in the active Laragon/Apache `php.ini` when `.user.ini` is not honored by the PHP handler.

### Registration

- Re-enabled self-registration for normal users only.
- Added server-side validation, CSRF checking, password hashing, username/email uniqueness checks, and session-based rate limiting.
- New registrations use the existing inactive-account flag and must be activated by a Super Admin. Registration cannot assign an elevated role.
- Added a Register link and registration feedback on the login page.
- No registration tables or columns were added.

### Visual consistency and dark mode

- Applied the public Observatory dot-grid background to the Review Editor.
- Unified navigation layout and styling across role pages while preserving role-specific navigation choices.
- Improved dark-mode styling for editor modals and star-rating cards, and adjusted the modal backdrop to retain transparency and blur.
- Kept the IRIS logo on a white surface in dark mode and removed the unwanted logo outline.
- Corrected Office Upload theme configuration and removed duplicate theme-toggle handling.

### Local runtime and troubleshooting

- The project was run locally through Laragon/PHP at `http://127.0.0.1:8000/`.
- A blank dashboard was traced to invalid PHP in the shared navigation include; correcting that syntax restored rendering.
- A temporary Cloudflare Quick Tunnel was used for remote preview. Quick Tunnel addresses are ephemeral and are not a deployment configuration.
- The local database connection configuration was adjusted for the development environment. Do not reuse local credentials in a deployed environment.

## Known issue

The Super Admin Manage Templates page can show “Unable to process template request” when an import profile references a saved workbook that is missing or inaccessible in private storage. The PHP log identified `ProfileWorkbookService::savedWorkbookPreview()` as the failing path. Restore the referenced file or upload it again through the manager. This is a workbook-storage issue, not a UI styling issue.

## Database and deployment notes

- No database schema or data changes were executed as part of these patches.
- The code uses the existing `users.is_active` field for registration approval.
- The repository contains migration scripts for features that require schema support. Review the version-specific release notes and compare migrations with the target schema before applying any. In particular, `migrations/20261003_template_destination.sql` was not run as part of this patch session.
- The local database dump and local database credentials are environment-specific and are not documented here.
- For Laragon with Apache `mod_php`, set PHP upload limits in the active `php.ini` and restart Apache if `.user.ini` settings are not applied.

## Verification performed

- Ran PHP syntax checks on the modified navigation, upload, and registration pages during implementation.
- Ran JavaScript syntax checks on the shared navigation and Office Upload scripts.
- Ran `git diff --check` on the edited code.
- Inspected the shared navigation visually at the available local viewport sizes. Some live role-specific actions, including the Office Upload theme and account menus, were not available for an authenticated end-to-end manual check in every role.
- No automated browser/E2E suite was run.

## Files included in the patch set

### Shared navigation, layout, and appearance

- `.htaccess`
- `admin/dashboard.php`
- `admin/includes/header.php`
- `admin/office_upload.php`
- `includes/portal_nav.php`
- `scanner/css/portalNavigation.css`
- `scanner/css/styles.css`
- `scanner/js/portalNavigation.js`
- `user/dashboard.php`

### Uploads, templates, and import mapping

- `.user.ini`
- `admin/includes/template_manager_modal.php`
- `admin/js/officeTemplates.js`
- `admin/js/profileWorkbookMapper.js`
- `admin/js/templateManager.js`
- `admin/smart_upload_process.php`
- `admin/upload_process.php`
- `api/templates.php`
- `api/star_rating_cards.php`
- `includes/helpers/ProfileWorkbookService.php`
- `includes/helpers/SheetValidationHelper.php`
- `includes/helpers/TemplateImportSupport.php`
- `includes/upload_limits.php`
- `scanner/index.php`
- `scanner/js/modules/fileIngestion.js`

### File Archives

- `scanner/js/modules/adminPortal.js`

### Registration

- `admin/includes/account_manager_modal.php`
- `auth/login.php`
- `auth/register.php`

### Documentation and local configuration

- `README.md`
- `config/db.php` (local development configuration only; credentials intentionally omitted)
