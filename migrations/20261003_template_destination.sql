-- Run this migration against the configured normalized database (iris_db_3nf).

SET @add_template_destination = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE templates ADD COLUMN destination ENUM(''analytics'', ''summary_cards'', ''ranking_history'') NULL AFTER original_filename',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND COLUMN_NAME = 'destination'
);
PREPARE template_destination_stmt FROM @add_template_destination;
EXECUTE template_destination_stmt;
DEALLOCATE PREPARE template_destination_stmt;

UPDATE templates
LEFT JOIN template_import_profiles ON template_import_profiles.template_id = templates.template_id
SET templates.destination = COALESCE(template_import_profiles.destination, 'analytics')
WHERE templates.destination IS NULL;

ALTER TABLE templates
    MODIFY COLUMN destination ENUM('analytics', 'summary_cards', 'ranking_history') NOT NULL DEFAULT 'analytics';
