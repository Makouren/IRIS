-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

CREATE TABLE IF NOT EXISTS app_change_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO app_change_state (id, version) VALUES (1, 1);