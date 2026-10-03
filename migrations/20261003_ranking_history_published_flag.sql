-- Add is_published flag to rankings so rows can be shown/hidden on the public chart
-- All existing rows default to published (1) so nothing disappears after migration.
SET @add_ranking_published_flag = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE rankings ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1 AFTER info_text',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND COLUMN_NAME = 'is_published'
);
PREPARE iris_ranking_published_stmt FROM @add_ranking_published_flag;
EXECUTE iris_ranking_published_stmt;
DEALLOCATE PREPARE iris_ranking_published_stmt;
