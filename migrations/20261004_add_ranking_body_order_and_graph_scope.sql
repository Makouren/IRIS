-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
ALTER TABLE ranking_bodies
    ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER short_name;

UPDATE ranking_bodies
SET sort_order = CASE
    WHEN UPPER(short_name) = 'WURI' THEN 10
    WHEN UPPER(short_name) = 'QS' THEN 20
    WHEN LOWER(name) LIKE '%webometrics%' OR UPPER(short_name) LIKE 'WEBOMETRICS%' THEN 30
    ELSE 100
END;

ALTER TABLE saved_graphs
    ADD COLUMN scope VARCHAR(150) NULL AFTER title;