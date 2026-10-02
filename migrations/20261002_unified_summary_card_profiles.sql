USE iris_db;

SET @allow_standalone_import_profiles = (
    SELECT IF(IS_NULLABLE = 'NO', 'ALTER TABLE template_import_profiles MODIFY template_id INT NULL', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'template_id'
);
PREPARE iris_summary_profile_stmt FROM @allow_standalone_import_profiles;
EXECUTE iris_summary_profile_stmt;
DEALLOCATE PREPARE iris_summary_profile_stmt;

SET @add_summary_profile_name = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE template_import_profiles ADD COLUMN profile_name VARCHAR(150) NULL AFTER template_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'template_import_profiles' AND COLUMN_NAME = 'profile_name'
);
PREPARE iris_summary_profile_stmt FROM @add_summary_profile_name;
EXECUTE iris_summary_profile_stmt;
DEALLOCATE PREPARE iris_summary_profile_stmt;

SET @add_record_import_profile_id = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE records ADD COLUMN import_profile_id BIGINT UNSIGNED NULL AFTER template_id', 'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'import_profile_id'
);
PREPARE iris_summary_profile_stmt FROM @add_record_import_profile_id;
EXECUTE iris_summary_profile_stmt;
DEALLOCATE PREPARE iris_summary_profile_stmt;

SET @add_record_import_profile_fk = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD CONSTRAINT fk_records_import_profile FOREIGN KEY (import_profile_id) REFERENCES template_import_profiles(id) ON DELETE SET NULL',
        'SELECT 1')
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND CONSTRAINT_NAME = 'fk_records_import_profile'
);
PREPARE iris_summary_profile_stmt FROM @add_record_import_profile_fk;
EXECUTE iris_summary_profile_stmt;
DEALLOCATE PREPARE iris_summary_profile_stmt;

INSERT INTO template_import_profiles (
    template_id, profile_name, destination, sheet_selector, header_aliases,
    required_columns, identity_fields, mapping_rules, defaults_json
)
SELECT NULL, 'Unified Summary Cards', 'summary_cards', 'Summary Cards',
    JSON_OBJECT(
        'import_key', JSON_ARRAY('Global Label'),
        'card_title', JSON_ARRAY('Title'),
        'period_key', JSON_ARRAY('Year / Date'),
        'main_value', JSON_ARRAY('Main Value'),
        'main_label', JSON_ARRAY('Main Label'),
        'year_date', JSON_ARRAY('Year / Date'),
        'secondary_label', JSON_ARRAY('Secondary Label'),
        'secondary_value', JSON_ARRAY('Secondary Value'),
        'description', JSON_ARRAY('Description'),
        'secondary_description', JSON_ARRAY('Italic Supporting Text'),
        'info_text', JSON_ARRAY('Information'),
        'category_names', JSON_ARRAY('Categories'),
        'display_precision', JSON_ARRAY('Display Precision')
    ),
    JSON_ARRAY('import_key', 'period_key', 'main_value', 'main_label', 'card_title'),
    JSON_ARRAY('import_key', 'main_label', 'card_title'),
    JSON_OBJECT(
        'import_key', 'Global Label',
        'card_title', 'Title',
        'period_key', 'Year / Date',
        'main_value', 'Main Value',
        'main_label', 'Main Label',
        'year_date', 'Year / Date',
        'secondary_label', 'Secondary Label',
        'secondary_value', 'Secondary Value',
        'description', 'Description',
        'secondary_description', 'Italic Supporting Text',
        'info_text', 'Information',
        'category_names', 'Categories',
        'display_precision', 'Display Precision'
    ),
    JSON_OBJECT()
WHERE NOT EXISTS (
    SELECT 1 FROM template_import_profiles
    WHERE template_id IS NULL AND profile_name = 'Unified Summary Cards' AND destination = 'summary_cards'
);

CREATE TABLE IF NOT EXISTS summary_card_import_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    active_profile_id BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_summary_card_import_settings_profile FOREIGN KEY (active_profile_id) REFERENCES template_import_profiles(id) ON DELETE SET NULL,
    CONSTRAINT fk_summary_card_import_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO summary_card_import_settings (id, active_profile_id)
SELECT 1, id FROM template_import_profiles
WHERE template_id IS NULL AND profile_name = 'Unified Summary Cards' AND destination = 'summary_cards'
ORDER BY id LIMIT 1;

UPDATE records
SET import_profile_id = (
    SELECT active_profile_id FROM summary_card_import_settings WHERE id = 1
)
WHERE import_profile_id IS NULL AND template_id IS NULL
    AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.upload_purpose')) = 'summary_cards';