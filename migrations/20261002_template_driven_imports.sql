USE iris_db;

CREATE TABLE IF NOT EXISTS template_import_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    destination ENUM('ranking_history', 'summary_cards') NOT NULL,
    sheet_selector VARCHAR(255) NULL,
    header_aliases JSON NOT NULL,
    required_columns JSON NOT NULL,
    mapping_rules JSON NOT NULL,
    defaults_json JSON NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_template_import_profile_template (template_id),
    CONSTRAINT fk_template_import_profile_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_template_import_profile_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    created_by BIGINT UNSIGNED NULL,
    template_id INT NOT NULL,
    source_record_id VARCHAR(255) NOT NULL,
    destination ENUM('ranking_history', 'summary_cards') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    status ENUM('applied', 'reverted') NOT NULL DEFAULT 'applied',
    inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reverted_at DATETIME NULL,
    KEY idx_import_batches_source (source_record_id),
    KEY idx_import_batches_content (destination, template_id, content_sha256),
    CONSTRAINT fk_import_batches_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_import_batches_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batch_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('ranking', 'summary_card') NOT NULL,
    entity_id VARCHAR(255) NOT NULL,
    sheet_name VARCHAR(255) NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    before_state JSON NULL,
    after_state JSON NOT NULL,
    KEY idx_import_batch_rows_entity (entity_type, entity_id),
    CONSTRAINT fk_import_batch_rows_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @add_summary_card_import_key = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_cards ADD COLUMN import_key VARCHAR(100) NULL AFTER id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_cards' AND COLUMN_NAME = 'import_key'
);
PREPARE iris_import_stmt FROM @add_summary_card_import_key;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

SET @add_summary_secondary_description = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_cards ADD COLUMN secondary_description TEXT NULL AFTER description',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_cards' AND COLUMN_NAME = 'secondary_description'
);
PREPARE iris_import_stmt FROM @add_summary_secondary_description;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

SET @add_summary_info_text = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_cards ADD COLUMN info_text TEXT NULL AFTER secondary_description',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_cards' AND COLUMN_NAME = 'info_text'
);
PREPARE iris_import_stmt FROM @add_summary_info_text;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

UPDATE summary_cards SET import_key = id WHERE import_key IS NULL;

SET @add_summary_card_import_key_index = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_cards ADD UNIQUE KEY uq_summary_cards_import_key (import_key)',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_cards' AND INDEX_NAME = 'uq_summary_cards_import_key'
);
PREPARE iris_import_stmt FROM @add_summary_card_import_key_index;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

SET @add_rankings_updated_at = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE rankings ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND COLUMN_NAME = 'updated_at'
);
PREPARE iris_import_stmt FROM @add_rankings_updated_at;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

SET @add_ranking_import_identity_index = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE rankings ADD INDEX idx_rankings_import_identity (ranking_body_id, scope_id, ranking_type, level, year, edition, category)',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND INDEX_NAME = 'idx_rankings_import_identity'
);
PREPARE iris_import_stmt FROM @add_ranking_import_identity_index;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

SET @drop_coarse_ranking_key = (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE rankings DROP INDEX uq_rankings_seed_key',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND INDEX_NAME = 'uq_rankings_seed_key'
);
PREPARE iris_import_stmt FROM @drop_coarse_ranking_key;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

CREATE TABLE IF NOT EXISTS summary_card_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(255) NOT NULL,
    period_key VARCHAR(20) NOT NULL,
    main_value VARCHAR(255) NOT NULL,
    secondary_value VARCHAR(255) NOT NULL DEFAULT '',
    year_date VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    secondary_description TEXT NULL,
    info_text TEXT NULL,
    source_record_id VARCHAR(255) NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_summary_card_snapshot_period (card_id, period_key),
    KEY idx_summary_card_snapshot_period (period_key),
    CONSTRAINT fk_summary_card_snapshot_card FOREIGN KEY (card_id) REFERENCES summary_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_summary_card_snapshot_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @add_snapshot_secondary_description = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_card_snapshots ADD COLUMN secondary_description TEXT NULL AFTER description',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'secondary_description'
);
PREPARE iris_import_stmt FROM @add_snapshot_secondary_description;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

SET @add_snapshot_info_text = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_card_snapshots ADD COLUMN info_text TEXT NULL AFTER secondary_description',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'info_text'
);
PREPARE iris_import_stmt FROM @add_snapshot_info_text;
EXECUTE iris_import_stmt;
DEALLOCATE PREPARE iris_import_stmt;

DROP TRIGGER IF EXISTS trg_summary_cards_import_key_before_insert;
CREATE TRIGGER trg_summary_cards_import_key_before_insert BEFORE INSERT ON summary_cards FOR EACH ROW SET NEW.import_key = COALESCE(NEW.import_key, NEW.id);

INSERT IGNORE INTO summary_cards (
    id, import_key, title, main_value, main_label, year_date,
    secondary_label, secondary_value, description, secondary_description,
    info_text, display_order, display_precision, is_published
) VALUES (
    'sample_summary_ranking', 'sample_summary_ranking', 'Sample Global Ranking',
    '601-650', 'Global ranking band', '2026', 'Institution', 'CLSU',
    'Sample card for validating template-driven snapshot imports.',
    'Replace this italic supporting line with the second description from the upload.',
    'This information appears from the circled i control and can be updated from a mapped Info column.',
    0, 0, 1
);

UPDATE summary_cards
SET import_key = 'snapshot-central-luzon-state-university-clsu', title = 'CLSU WURI'
WHERE id = 'sample_summary_ranking';
