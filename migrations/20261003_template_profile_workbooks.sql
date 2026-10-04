-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

SET @add_profile_workbook_path = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE template_import_profiles ADD COLUMN workbook_file_path VARCHAR(80) NULL AFTER defaults_json',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'workbook_file_path'
);
PREPARE iris_profile_workbook_stmt FROM @add_profile_workbook_path;
EXECUTE iris_profile_workbook_stmt;
DEALLOCATE PREPARE iris_profile_workbook_stmt;

SET @add_profile_workbook_name = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE template_import_profiles ADD COLUMN workbook_original_filename VARCHAR(255) NULL AFTER workbook_file_path',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'workbook_original_filename'
);
PREPARE iris_profile_workbook_stmt FROM @add_profile_workbook_name;
EXECUTE iris_profile_workbook_stmt;
DEALLOCATE PREPARE iris_profile_workbook_stmt;

SET @add_profile_workbook_headers = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE template_import_profiles ADD COLUMN workbook_headers JSON NULL AFTER workbook_original_filename',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'workbook_headers'
);
PREPARE iris_profile_workbook_stmt FROM @add_profile_workbook_headers;
EXECUTE iris_profile_workbook_stmt;
DEALLOCATE PREPARE iris_profile_workbook_stmt;

SET @add_profile_workbook_header_row = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE template_import_profiles ADD COLUMN workbook_header_row INT UNSIGNED NULL AFTER workbook_headers',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'workbook_header_row'
);
PREPARE iris_profile_workbook_stmt FROM @add_profile_workbook_header_row;
EXECUTE iris_profile_workbook_stmt;
DEALLOCATE PREPARE iris_profile_workbook_stmt;