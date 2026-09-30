-- Run after 20260930_add_summary_card_categories.sql.
CREATE TABLE IF NOT EXISTS summary_card_category_map (
    summary_card_id VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (summary_card_id, category_id),
    KEY idx_summary_card_category_map_category (category_id),
    CONSTRAINT fk_summary_card_category_map_card
        FOREIGN KEY (summary_card_id) REFERENCES summary_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_summary_card_category_map_category
        FOREIGN KEY (category_id) REFERENCES summary_card_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO summary_card_category_map (summary_card_id, category_id)
SELECT id, category_id
FROM summary_cards
WHERE category_id IS NOT NULL;