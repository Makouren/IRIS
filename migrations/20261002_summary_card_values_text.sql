-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db_3nf;

ALTER TABLE summary_card_periods
    MODIFY COLUMN main_value VARCHAR(255) NULL,
    MODIFY COLUMN secondary_value VARCHAR(255) NULL;

ALTER TABLE summary_card_snapshots
    MODIFY COLUMN main_value VARCHAR(255) NULL,
    MODIFY COLUMN secondary_value VARCHAR(255) NULL;
