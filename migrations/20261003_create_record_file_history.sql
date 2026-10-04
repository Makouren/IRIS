-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
CREATE TABLE record_file_history (
    version_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    merge_group_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    record_id BIGINT UNSIGNED NOT NULL,
    source_record_id BIGINT UNSIGNED NOT NULL,
    target_record_id BIGINT UNSIGNED NOT NULL,
    entry_type VARCHAR(32) NOT NULL,
    merge_method VARCHAR(16) NOT NULL,
    acting_super_admin_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    snapshot_format SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    snapshot_json LONGTEXT NOT NULL,
    file_snapshot_key VARCHAR(64) NULL,
    original_file_name VARCHAR(255) NULL,
    file_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,
    file_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (version_id),
    KEY idx_record_file_history_record (record_id, version_id),
    KEY idx_record_file_history_group (merge_group_id, version_id),
    KEY idx_record_file_history_pair (source_record_id, target_record_id, version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
