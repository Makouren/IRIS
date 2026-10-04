-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

ALTER TABLE summary_cards
    MODIFY import_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL;

SET @add_snapshot_title = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT "" AFTER card_id', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'title'
);
PREPARE iris_summary_stmt FROM @add_snapshot_title;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_main_label = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN main_label VARCHAR(255) NOT NULL DEFAULT "" AFTER main_value', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'main_label'
);
PREPARE iris_summary_stmt FROM @add_snapshot_main_label;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_secondary_label = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN secondary_label VARCHAR(255) NOT NULL DEFAULT "" AFTER main_label', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'secondary_label'
);
PREPARE iris_summary_stmt FROM @add_snapshot_secondary_label;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_period_label = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN period_label VARCHAR(255) NOT NULL DEFAULT "" AFTER period_key', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'period_label'
);
PREPARE iris_summary_stmt FROM @add_snapshot_period_label;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_period_sort = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN period_sort INT UNSIGNED NOT NULL DEFAULT 0 AFTER period_label', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'period_sort'
);
PREPARE iris_summary_stmt FROM @add_snapshot_period_sort;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_period_precision = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN period_precision TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER period_sort', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'period_precision'
);
PREPARE iris_summary_stmt FROM @add_snapshot_period_precision;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_publication = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER period_precision', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'is_published'
);
PREPARE iris_summary_stmt FROM @add_snapshot_publication;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_source_info = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN source_info TEXT NULL AFTER info_text', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'source_info'
);
PREPARE iris_summary_stmt FROM @add_snapshot_source_info;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_last_source = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN last_source_record_id VARCHAR(255) NULL AFTER source_record_id', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'last_source_record_id'
);
PREPARE iris_summary_stmt FROM @add_snapshot_last_source;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

SET @add_snapshot_last_batch = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE summary_card_snapshots ADD COLUMN last_batch_id BIGINT UNSIGNED NULL AFTER batch_id', 'SELECT 1')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND COLUMN_NAME = 'last_batch_id'
);
PREPARE iris_summary_stmt FROM @add_snapshot_last_batch;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

ALTER TABLE summary_card_snapshots
    MODIFY source_record_id VARCHAR(255) NULL,
    MODIFY batch_id BIGINT UNSIGNED NULL;

UPDATE summary_card_snapshots
SET period_label = IF(period_label = '', IF(year_date = '', period_key, year_date), period_label),
    period_precision = CASE
        WHEN period_key REGEXP '^Y:[0-9]{4}$' THEN 1
        WHEN period_key REGEXP '^Q:[0-9]{4}-Q[1-4]$' THEN 2
        WHEN period_key REGEXP '^M:[0-9]{4}-(0[1-9]|1[0-2])$' THEN 3
        WHEN period_key REGEXP '^D:[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(SUBSTRING(period_key, 3), '%Y-%m-%d'), '%Y-%m-%d') = SUBSTRING(period_key, 3) THEN 4
        WHEN period_key REGEXP '^[0-9]{4}$' THEN 1
        WHEN period_key REGEXP '^[0-9]{4}-Q[1-4]$' THEN 2
        WHEN period_key REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN 3
        WHEN period_key REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(period_key, '%Y-%m-%d'), '%Y-%m-%d') = period_key THEN 4
        ELSE 0 END,
    period_sort = CASE
        WHEN period_key REGEXP '^Y:[0-9]{4}$' THEN CAST(CONCAT(SUBSTRING(period_key, 3), '1231') AS UNSIGNED)
        WHEN period_key REGEXP '^Q:[0-9]{4}-Q[1-4]$' THEN CAST(DATE_FORMAT(LAST_DAY(STR_TO_DATE(CONCAT(SUBSTRING(period_key, 3, 4), '-', CAST(SUBSTRING(period_key, 8, 1) AS UNSIGNED) * 3, '-01'), '%Y-%m-%d')), '%Y%m%d') AS UNSIGNED)
        WHEN period_key REGEXP '^M:[0-9]{4}-(0[1-9]|1[0-2])$' THEN CAST(DATE_FORMAT(LAST_DAY(STR_TO_DATE(CONCAT(SUBSTRING(period_key, 3), '-01'), '%Y-%m-%d')), '%Y%m%d') AS UNSIGNED)
        WHEN period_key REGEXP '^D:[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(SUBSTRING(period_key, 3), '%Y-%m-%d'), '%Y-%m-%d') = SUBSTRING(period_key, 3) THEN CAST(REPLACE(SUBSTRING(period_key, 3), '-', '') AS UNSIGNED)
        WHEN period_key REGEXP '^[0-9]{4}$' THEN CAST(CONCAT(period_key, '1231') AS UNSIGNED)
        WHEN period_key REGEXP '^[0-9]{4}-Q[1-4]$' THEN CAST(DATE_FORMAT(LAST_DAY(STR_TO_DATE(CONCAT(LEFT(period_key, 4), '-', CAST(SUBSTRING(period_key, 7, 1) AS UNSIGNED) * 3, '-01'), '%Y-%m-%d')), '%Y%m%d') AS UNSIGNED)
        WHEN period_key REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN CAST(DATE_FORMAT(LAST_DAY(STR_TO_DATE(CONCAT(period_key, '-01'), '%Y-%m-%d')), '%Y%m%d') AS UNSIGNED)
        WHEN period_key REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(period_key, '%Y-%m-%d'), '%Y-%m-%d') = period_key THEN CAST(REPLACE(period_key, '-', '') AS UNSIGNED)
        ELSE 0 END,
    period_key = CASE
        WHEN period_key REGEXP '^[YQMDL]:[A-Za-z0-9:-]+$' THEN CONVERT(period_key USING ascii) COLLATE ascii_bin
        WHEN period_key REGEXP '^[0-9]{4}$' THEN CONCAT(_ascii'Y:', CONVERT(period_key USING ascii)) COLLATE ascii_bin
        WHEN period_key REGEXP '^[0-9]{4}-Q[1-4]$' THEN CONCAT(_ascii'Q:', CONVERT(period_key USING ascii)) COLLATE ascii_bin
        WHEN period_key REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN CONCAT(_ascii'M:', CONVERT(period_key USING ascii)) COLLATE ascii_bin
        WHEN period_key REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(period_key, '%Y-%m-%d'), '%Y-%m-%d') = period_key THEN CONCAT(_ascii'D:', CONVERT(period_key USING ascii)) COLLATE ascii_bin
        ELSE CONCAT(_ascii'L:', LEFT(CONVERT(SHA2(CONCAT(card_id, CHAR(0), CONVERT(period_key USING utf8mb4) COLLATE utf8mb4_general_ci), 256) USING ascii), 16)) COLLATE ascii_bin END;

ALTER TABLE summary_card_snapshots
    MODIFY period_key VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

UPDATE summary_card_snapshots snapshots
INNER JOIN summary_cards cards ON cards.id = snapshots.card_id
SET snapshots.title = cards.title
WHERE snapshots.title = '';

UPDATE summary_card_snapshots snapshots
INNER JOIN summary_cards cards ON cards.id = snapshots.card_id
SET snapshots.main_label = cards.main_label,
    snapshots.secondary_label = cards.secondary_label
WHERE snapshots.main_label = '' AND snapshots.secondary_label = '';

UPDATE summary_card_snapshots snapshots
INNER JOIN records ON records.id = snapshots.source_record_id
SET snapshots.source_info = CONCAT('Imported from ', records.fileName)
WHERE snapshots.source_info IS NULL AND snapshots.source_record_id IS NOT NULL;

UPDATE summary_card_snapshots
SET last_source_record_id = source_record_id,
    last_batch_id = batch_id
WHERE last_source_record_id IS NULL AND (source_record_id IS NOT NULL OR batch_id IS NOT NULL);

INSERT IGNORE INTO summary_card_snapshots (
    card_id, title, period_key, period_label, period_sort, period_precision, is_published,
    main_value, main_label, secondary_label, secondary_value, year_date,
    description, secondary_description, info_text, source_info, source_record_id, batch_id
)
SELECT cards.id, cards.title,
    CASE
        WHEN cards.year_date REGEXP '^[0-9]{4}$' THEN CONCAT('Y:', cards.year_date)
        WHEN cards.year_date REGEXP '^[0-9]{4}-Q[1-4]$' THEN CONCAT('Q:', cards.year_date)
        WHEN cards.year_date REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN CONCAT('M:', cards.year_date)
        WHEN cards.year_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(cards.year_date, '%Y-%m-%d'), '%Y-%m-%d') = cards.year_date THEN CONCAT('D:', cards.year_date)
        ELSE CONCAT('L:', LEFT(SHA2(CONCAT(cards.id, CHAR(0), cards.year_date), 256), 16)) END,
    IF(cards.year_date = '', 'Legacy current period', cards.year_date),
    CASE
        WHEN cards.year_date REGEXP '^[0-9]{4}$' THEN CAST(CONCAT(cards.year_date, '1231') AS UNSIGNED)
        WHEN cards.year_date REGEXP '^[0-9]{4}-Q[1-4]$' THEN CAST(DATE_FORMAT(LAST_DAY(STR_TO_DATE(CONCAT(LEFT(cards.year_date, 4), '-', CAST(SUBSTRING(cards.year_date, 7, 1) AS UNSIGNED) * 3, '-01'), '%Y-%m-%d')), '%Y%m%d') AS UNSIGNED)
        WHEN cards.year_date REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN CAST(DATE_FORMAT(LAST_DAY(STR_TO_DATE(CONCAT(cards.year_date, '-01'), '%Y-%m-%d')), '%Y%m%d') AS UNSIGNED)
        WHEN cards.year_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(cards.year_date, '%Y-%m-%d'), '%Y-%m-%d') = cards.year_date THEN CAST(REPLACE(cards.year_date, '-', '') AS UNSIGNED)
        ELSE 0 END,
    CASE
        WHEN cards.year_date REGEXP '^[0-9]{4}$' THEN 1
        WHEN cards.year_date REGEXP '^[0-9]{4}-Q[1-4]$' THEN 2
        WHEN cards.year_date REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN 3
        WHEN cards.year_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(cards.year_date, '%Y-%m-%d'), '%Y-%m-%d') = cards.year_date THEN 4
        ELSE 0 END,
    cards.is_published, cards.main_value, cards.main_label, cards.secondary_label,
    cards.secondary_value, cards.year_date, cards.description,
    cards.secondary_description, cards.info_text, NULL, NULL, NULL
FROM summary_cards cards;

UPDATE summary_card_snapshots snapshots
INNER JOIN summary_cards cards ON cards.id = snapshots.card_id
SET snapshots.title = cards.title,
    snapshots.period_label = IF(cards.year_date = '', 'Legacy current period', cards.year_date),
    snapshots.is_published = cards.is_published,
    snapshots.main_value = cards.main_value,
    snapshots.main_label = cards.main_label,
    snapshots.secondary_label = cards.secondary_label,
    snapshots.secondary_value = cards.secondary_value,
    snapshots.year_date = cards.year_date,
    snapshots.description = cards.description,
    snapshots.secondary_description = cards.secondary_description,
    snapshots.info_text = cards.info_text
WHERE snapshots.period_key = CASE
        WHEN cards.year_date REGEXP '^[0-9]{4}$' THEN CONCAT('Y:', cards.year_date)
        WHEN cards.year_date REGEXP '^[0-9]{4}-Q[1-4]$' THEN CONCAT('Q:', cards.year_date)
        WHEN cards.year_date REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' THEN CONCAT('M:', cards.year_date)
        WHEN cards.year_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND DATE_FORMAT(STR_TO_DATE(cards.year_date, '%Y-%m-%d'), '%Y-%m-%d') = cards.year_date THEN CONCAT('D:', cards.year_date)
        ELSE CONCAT('L:', LEFT(SHA2(CONCAT(cards.id, CHAR(0), cards.year_date), 256), 16)) END;

SET @add_summary_snapshot_unique = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE summary_card_snapshots ADD UNIQUE KEY uq_summary_card_snapshot_period (card_id, period_key)',
        'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_card_snapshots' AND INDEX_NAME = 'uq_summary_card_snapshot_period'
);
PREPARE iris_summary_stmt FROM @add_summary_snapshot_unique;
EXECUTE iris_summary_stmt;
DEALLOCATE PREPARE iris_summary_stmt;

CREATE TABLE IF NOT EXISTS summary_card_period_changes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    period_key VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    action ENUM('create', 'identity', 'correct', 'publish', 'unpublish') NOT NULL,
    before_state JSON NULL,
    after_state JSON NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_summary_card_period_changes (card_id, period_key, created_at),
    CONSTRAINT fk_summary_card_period_changes_card FOREIGN KEY (card_id) REFERENCES summary_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_summary_card_period_changes_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;