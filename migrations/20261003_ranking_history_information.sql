-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db;

SET @add_ranking_info_text = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE rankings ADD COLUMN info_text TEXT NULL AFTER global_rank',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND COLUMN_NAME = 'info_text'
);
PREPARE iris_ranking_info_stmt FROM @add_ranking_info_text;
EXECUTE iris_ranking_info_stmt;
DEALLOCATE PREPARE iris_ranking_info_stmt;
