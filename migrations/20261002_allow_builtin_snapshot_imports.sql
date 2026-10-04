-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

SET @drop_import_batch_template_fk = (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE import_batches DROP FOREIGN KEY fk_import_batches_template',
        'SELECT 1')
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_batches'
      AND CONSTRAINT_NAME = 'fk_import_batches_template'
      AND REFERENCED_TABLE_NAME = 'templates'
);
PREPARE iris_import_stmt FROM @drop_import_batch_template_fk;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

ALTER TABLE import_batches MODIFY COLUMN template_id INT NULL;

SET @add_import_batch_template_fk = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE import_batches ADD CONSTRAINT fk_import_batches_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL',
        'SELECT 1')
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'import_batches'
      AND CONSTRAINT_NAME = 'fk_import_batches_template'
      AND REFERENCED_TABLE_NAME = 'templates'
);
PREPARE iris_import_stmt FROM @add_import_batch_template_fk;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;
