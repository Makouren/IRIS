-- Run once against iris_db. Existing summary cards remain uncategorized.
CREATE TABLE IF NOT EXISTS summary_card_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_summary_card_categories_name (name),
    UNIQUE KEY uq_summary_card_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE summary_cards
    ADD COLUMN category_id INT UNSIGNED NULL,
    ADD INDEX idx_summary_cards_category_id (category_id),
    ADD CONSTRAINT fk_summary_cards_category
        FOREIGN KEY (category_id) REFERENCES summary_card_categories(id)
        ON DELETE SET NULL;