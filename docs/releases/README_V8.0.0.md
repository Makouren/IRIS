# IRIS v8.0.0

This release builds on v7.5.0 with improvements to the admin card-management
experience, public Observatory dashboards, Ranking History, and responsive
navigation.

## Admin card management

- Organize Summary Cards and Star Rating Cards into collapsible category
  sections in the Review Editor.
- Preserve category-section open state as the editor rerenders.
- Keep selection synchronized when a card appears in multiple categories and
  deduplicate IDs before bulk actions.
- Correct published-card selection so bulk unpublish is available and operates
  reliably when card IDs arrive as either numbers or strings.
- Improve category-specific card presentation and dark-mode styling.

## Public Observatory and Ranking History

- Give Summary, Ranking History, Star Ratings, and published graph sections
  bounded internal scrolling, avoiding excessive page-level scrolling.
- Keep section headings and filtering controls visible while their content
  scrolls.
- Use opaque light- and dark-theme header surfaces, including matching
  scrollbar-track colors, to prevent chart content showing through or leaving
  a contrasting strip at the right edge.
- Avoid repeating an organization name in Ranking History chart titles when
  the ranking type already includes it.
- Retain per-card Ranking Trend Matrix actions, ranking explanations, and
  responsive graph layouts.
- Resize visible charts after a theme change so chart dimensions stay correct.

## Navigation and presentation

- Improve the mobile hamburger menu's visibility and contrast in light mode.
- Coordinate the mobile menu and profile actions in portrait layouts, and make
  the theme control's label visible.
- Improve contrast for the Office template download action.
- Add regression coverage for category grouping, bulk actions, scrolling,
  sticky controls, theme surfaces, and ranking chart titles.

## Maintenance and database

- Add concise purpose documentation to application pages, browser modules, API
  handlers, helpers, and migration files.
- No schema or data migration behavior changes are included; migration-file
  edits in this release add explanatory comments only.
- The workstation-specific edit to `config/db.php` is intentionally excluded
  from the release commit. Configure database credentials per environment.

## Validation

- PHP syntax checks: 82 files passed.
- PHP tests: all 3 test files passed.
- Node tests: 151 total; 141 passed and 10 failed. The same 10 cases failed in
  the recorded pre-release baseline.
- Authenticated browser verification was not completed.
