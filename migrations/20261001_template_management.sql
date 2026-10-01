USE iris_db;

CREATE TABLE IF NOT EXISTS templates (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_templates_uploaded_by (uploaded_by),
    CONSTRAINT fk_templates_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @add_template_id = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD COLUMN template_id INT NULL AFTER uploaded_at',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'template_id'
);
PREPARE template_stmt FROM @add_template_id;
EXECUTE template_stmt;
DEALLOCATE PREPARE template_stmt;

SET @add_template_id_index = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD INDEX idx_records_template_id (template_id)',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND INDEX_NAME = 'idx_records_template_id'
);
PREPARE template_stmt FROM @add_template_id_index;
EXECUTE template_stmt;
DEALLOCATE PREPARE template_stmt;

SET @add_template_id_fk = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD CONSTRAINT fk_records_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE RESTRICT',
        'SELECT 1')
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'template_id'
      AND REFERENCED_TABLE_NAME = 'templates'
);
PREPARE template_stmt FROM @add_template_id_fk;
EXECUTE template_stmt;
DEALLOCATE PREPARE template_stmt;