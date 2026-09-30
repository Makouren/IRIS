-- Run once. Existing ranking values remain unchanged; new metadata is nullable/defaulted.
ALTER TABLE rankings
    MODIFY COLUMN rank_value DECIMAL(10,2) NULL,
    ADD COLUMN ranking_type VARCHAR(100) NULL,
    ADD COLUMN level VARCHAR(20) NULL,
    ADD COLUMN edition VARCHAR(80) NOT NULL DEFAULT 'Annual',
    ADD COLUMN rank_low INT UNSIGNED NULL,
    ADD COLUMN rank_high INT UNSIGNED NULL,
    ADD COLUMN source VARCHAR(500) NULL,
    ADD COLUMN verification_status VARCHAR(20) NOT NULL DEFAULT 'unverified',
    ADD COLUMN seed_managed TINYINT(1) NOT NULL DEFAULT 0,
    ADD UNIQUE KEY uq_rankings_seed_key (ranking_body_id, scope_id, year, edition);