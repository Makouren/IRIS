-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

SET @add_template_import_identity_fields = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE template_import_profiles ADD COLUMN identity_fields JSON NULL AFTER required_columns',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'identity_fields'
);
PREPARE iris_identity_stmt FROM @add_template_import_identity_fields;
EXECUTE iris_identity_stmt;
DEALLOCATE PREPARE iris_identity_stmt;

UPDATE template_import_profiles
SET identity_fields = JSON_ARRAY('import_key')
WHERE destination = 'summary_cards' AND identity_fields IS NULL;

UPDATE template_import_profiles
SET identity_fields = JSON_ARRAY()
WHERE destination = 'ranking_history' AND identity_fields IS NULL;
