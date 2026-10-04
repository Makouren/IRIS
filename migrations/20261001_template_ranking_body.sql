-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

SET @add_template_ranking_body_id = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE templates ADD COLUMN ranking_body_id BIGINT UNSIGNED NULL AFTER original_filename',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND COLUMN_NAME = 'ranking_body_id'
);
PREPARE template_body_stmt FROM @add_template_ranking_body_id;
EXECUTE template_body_stmt;
DEALLOCATE PREPARE template_body_stmt;

SET @add_template_ranking_body_index = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE templates ADD INDEX idx_templates_ranking_body_id (ranking_body_id)',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND INDEX_NAME = 'idx_templates_ranking_body_id'
);
PREPARE template_body_stmt FROM @add_template_ranking_body_index;
EXECUTE template_body_stmt;
DEALLOCATE PREPARE template_body_stmt;

SET @add_template_ranking_body_fk = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE templates ADD CONSTRAINT fk_templates_ranking_body FOREIGN KEY (ranking_body_id) REFERENCES ranking_bodies(id) ON DELETE SET NULL',
        'SELECT 1')
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND COLUMN_NAME = 'ranking_body_id'
      AND REFERENCED_TABLE_NAME = 'ranking_bodies'
);
PREPARE template_body_stmt FROM @add_template_ranking_body_fk;
EXECUTE template_body_stmt;
DEALLOCATE PREPARE template_body_stmt;