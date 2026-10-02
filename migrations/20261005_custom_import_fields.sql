-- Apply to the configured normalized IRIS database after backing it up.
-- Adds profile definitions and per-period/per-ranking storage for custom workbook fields.

SET @add_profile_custom_fields = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE template_import_profiles ADD COLUMN custom_fields JSON NULL AFTER mapping_rules',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'custom_fields'
);
PREPARE iris_custom_fields_stmt FROM @add_profile_custom_fields;
EXECUTE iris_custom_fields_stmt;
DEALLOCATE PREPARE iris_custom_fields_stmt;

SET @add_ranking_custom_fields = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE rankings ADD COLUMN custom_fields JSON NULL AFTER source',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND COLUMN_NAME = 'custom_fields'
);
PREPARE iris_custom_fields_stmt FROM @add_ranking_custom_fields;
EXECUTE iris_custom_fields_stmt;
DEALLOCATE PREPARE iris_custom_fields_stmt;

SET @add_snapshot_custom_fields = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_card_snapshots ADD COLUMN custom_fields JSON NULL AFTER source_info',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'custom_fields'
);
PREPARE iris_custom_fields_stmt FROM @add_snapshot_custom_fields;
EXECUTE iris_custom_fields_stmt;
DEALLOCATE PREPARE iris_custom_fields_stmt;

SET @add_period_custom_fields = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_card_periods ADD COLUMN custom_fields JSON NULL AFTER source_info',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_periods' AND COLUMN_NAME = 'custom_fields'
);
PREPARE iris_custom_fields_stmt FROM @add_period_custom_fields;
EXECUTE iris_custom_fields_stmt;
DEALLOCATE PREPARE iris_custom_fields_stmt;
