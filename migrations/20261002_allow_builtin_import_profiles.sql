-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
ALTER TABLE template_import_profiles
    MODIFY COLUMN template_id BIGINT UNSIGNED NULL;
