SET @saved_graph_colors_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'saved_graphs'
      AND COLUMN_NAME = 'colors'
);
SET @saved_graph_colors_migration := IF(
    @saved_graph_colors_column_exists = 0,
    'ALTER TABLE `saved_graphs` ADD COLUMN `colors` JSON NULL',
    'SELECT 1'
);
PREPARE add_saved_graph_colors FROM @saved_graph_colors_migration;
EXECUTE add_saved_graph_colors;
DEALLOCATE PREPARE add_saved_graph_colors;