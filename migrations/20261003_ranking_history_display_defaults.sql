USE iris_db;

CREATE TABLE IF NOT EXISTS ranking_history_display_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    default_organization VARCHAR(100) NULL,
    default_list VARCHAR(320) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ranking_history_display_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ranking_history_display_settings (id, default_organization, default_list)
VALUES (1, NULL, NULL);
