CREATE TABLE IF NOT EXISTS ranking_scopes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ranking_scopes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE rankings
    ADD COLUMN scope_id INT UNSIGNED NULL,
    ADD INDEX idx_rankings_scope_id (scope_id),
    ADD CONSTRAINT fk_rankings_scope
        FOREIGN KEY (scope_id) REFERENCES ranking_scopes(id) ON DELETE SET NULL;

INSERT IGNORE INTO ranking_scopes (name, sort_order) VALUES
    ('International', 0),
    ('National', 1),
    ('Regional', 2),
    ('ASEAN', 3);