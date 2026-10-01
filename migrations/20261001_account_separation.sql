USE iris_db;

CREATE TABLE IF NOT EXISTS iris_schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    MODIFY COLUMN role ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user';

UPDATE users
SET role = 'super_admin'
WHERE NOT EXISTS (
    SELECT 1 FROM iris_schema_migrations WHERE version = '20261001_account_separation'
);

INSERT IGNORE INTO iris_schema_migrations (version)
VALUES ('20261001_account_separation');

SET @add_office_name = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE users ADD COLUMN office_name VARCHAR(100) NULL AFTER role',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'office_name'
);
PREPARE iris_stmt FROM @add_office_name;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

SET @add_is_active = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE users ADD COLUMN is_active TINYINT NOT NULL DEFAULT 1 AFTER office_name',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'
);
PREPARE iris_stmt FROM @add_is_active;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

SET @add_uploaded_by = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD COLUMN uploaded_by INT NULL AFTER status',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'uploaded_by'
);
PREPARE iris_stmt FROM @add_uploaded_by;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

SET @add_record_office_name = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD COLUMN office_name VARCHAR(100) NULL AFTER uploaded_by',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'office_name'
);
PREPARE iris_stmt FROM @add_record_office_name;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

SET @add_record_uploaded_at = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD COLUMN uploaded_at DATETIME NULL AFTER office_name',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'uploaded_at'
);
PREPARE iris_stmt FROM @add_record_uploaded_at;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

SET @add_uploaded_by_index = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD INDEX idx_records_uploaded_by (uploaded_by)',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND INDEX_NAME = 'idx_records_uploaded_by'
);
PREPARE iris_stmt FROM @add_uploaded_by_index;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

SET @add_record_opened_at = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE records ADD COLUMN opened_at DATETIME NULL AFTER uploaded_at',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'records' AND COLUMN_NAME = 'opened_at'
);
PREPARE iris_stmt FROM @add_record_opened_at;
EXECUTE iris_stmt;
DEALLOCATE PREPARE iris_stmt;

UPDATE records
SET opened_at = COALESCE(updatedAt, scannedAt, NOW())
WHERE opened_at IS NULL AND uploaded_at IS NULL;