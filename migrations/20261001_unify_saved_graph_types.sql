SET @saved_graph_chart_data_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'saved_graphs'
      AND COLUMN_NAME = 'chart_data'
);
SET @saved_graph_chart_data_migration := IF(
    @saved_graph_chart_data_column_exists = 0,
    'ALTER TABLE `saved_graphs` ADD COLUMN `chart_data` JSON NULL',
    'SELECT 1'
);
PREPARE add_saved_graph_chart_data FROM @saved_graph_chart_data_migration;
EXECUTE add_saved_graph_chart_data;
DEALLOCATE PREPARE add_saved_graph_chart_data;

UPDATE `saved_graphs`
SET `chart_data` = JSON_SET(
        COALESCE(`chart_data`, JSON_OBJECT()),
        '$.irisConfig',
        JSON_REMOVE(
            JSON_SET(
                COALESCE(JSON_EXTRACT(`chart_data`, '$.irisConfig'), JSON_OBJECT()),
                '$.type', 'bar'
            ),
            '$.roseMode'
        )
    ),
    `chart_type` = 'bar'
WHERE LOWER(`chart_type`) IN ('polararea', 'polar-area');
