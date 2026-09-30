CREATE TABLE IF NOT EXISTS star_rating_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_star_rating_categories_name (name),
    UNIQUE KEY uq_star_rating_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS star_rating_card_category_map (
    card_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (card_id, category_id),
    KEY idx_star_rating_card_category_category (category_id),
    CONSTRAINT fk_star_rating_card_category_card
        FOREIGN KEY (card_id) REFERENCES star_rating_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_star_rating_card_category_category
        FOREIGN KEY (category_id) REFERENCES star_rating_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;