-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db_3nf;

ALTER TABLE rankings
    ADD COLUMN global_rank_display VARCHAR(100) NULL AFTER global_rank,
    ADD COLUMN ph_rank_display VARCHAR(100) NULL AFTER ph_rank;

UPDATE rankings
SET global_rank_display = COALESCE(
        global_rank_display,
        CASE
            WHEN rank_low IS NOT NULL AND rank_high IS NOT NULL AND rank_low <> rank_high
                THEN CONCAT(rank_low, '-', rank_high)
            ELSE CAST(global_rank AS CHAR)
        END
    ),
    ph_rank_display = COALESCE(ph_rank_display, CAST(ph_rank AS CHAR));
