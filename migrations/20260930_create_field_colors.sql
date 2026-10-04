-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
CREATE TABLE IF NOT EXISTS field_colors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    field_key VARCHAR(191) NOT NULL,
    label VARCHAR(255) NOT NULL,
    color CHAR(7) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_field_colors_field_key (field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;