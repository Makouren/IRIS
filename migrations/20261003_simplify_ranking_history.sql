USE iris_db;

ALTER TABLE rankings MODIFY COLUMN ranking_type VARCHAR(320) NULL;

CREATE TEMPORARY TABLE iris_ranking_rank_recalc AS
SELECT id,
    LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(global_rank, '')), ',', ''), ' ', ''), '=', ''), '–', '-'), '—', '-'), CHAR(9), '')) AS rank_text
FROM rankings;

UPDATE rankings ranking
INNER JOIN iris_ranking_rank_recalc parsed ON parsed.id = ranking.id
SET ranking.rank_low = CASE
        WHEN parsed.rank_text REGEXP '^[0-9]+-[0-9]+$' THEN CAST(SUBSTRING_INDEX(parsed.rank_text, '-', 1) AS UNSIGNED)
        WHEN parsed.rank_text REGEXP '^top[0-9]+$' THEN 1
        WHEN parsed.rank_text REGEXP '^[0-9]+[+]$' THEN CAST(SUBSTRING_INDEX(parsed.rank_text, '+', 1) AS UNSIGNED)
        WHEN parsed.rank_text REGEXP '^[0-9]+$' THEN CAST(parsed.rank_text AS UNSIGNED)
        ELSE NULL
    END,
    ranking.rank_high = CASE
        WHEN parsed.rank_text REGEXP '^[0-9]+-[0-9]+$' THEN CAST(SUBSTRING_INDEX(parsed.rank_text, '-', -1) AS UNSIGNED)
        WHEN parsed.rank_text REGEXP '^top[0-9]+$' THEN CAST(SUBSTRING(parsed.rank_text, 4) AS UNSIGNED)
        WHEN parsed.rank_text REGEXP '^[0-9]+$' THEN CAST(parsed.rank_text AS UNSIGNED)
        ELSE NULL
    END,
    ranking.rank_value = CASE
        WHEN parsed.rank_text REGEXP '^[0-9]+-[0-9]+$' THEN
            (CAST(SUBSTRING_INDEX(parsed.rank_text, '-', 1) AS UNSIGNED) + CAST(SUBSTRING_INDEX(parsed.rank_text, '-', -1) AS UNSIGNED)) / 2
        WHEN parsed.rank_text REGEXP '^top[0-9]+$' THEN (1 + CAST(SUBSTRING(parsed.rank_text, 4) AS UNSIGNED)) / 2
        WHEN parsed.rank_text REGEXP '^[0-9]+[+]$' THEN CAST(SUBSTRING_INDEX(parsed.rank_text, '+', 1) AS UNSIGNED)
        WHEN parsed.rank_text REGEXP '^[0-9]+$' THEN CAST(parsed.rank_text AS UNSIGNED)
        ELSE NULL
    END;

DROP TEMPORARY TABLE iris_ranking_rank_recalc;

UPDATE rankings ranking
INNER JOIN ranking_bodies body ON body.id = ranking.ranking_body_id
SET ranking.ranking_type = body.short_name
WHERE ranking.ranking_type IS NULL OR TRIM(ranking.ranking_type) = '';

CREATE TEMPORARY TABLE iris_ranking_type_fold AS
SELECT ranking.id,
       CONCAT(COALESCE(NULLIF(TRIM(ranking.ranking_type), ''), body.short_name), ' - ', TRIM(ranking.category)) AS new_ranking_type
FROM rankings ranking
INNER JOIN ranking_bodies body ON body.id = ranking.ranking_body_id
WHERE ranking.category IS NOT NULL
    AND TRIM(ranking.category) <> ''
    AND LOWER(TRIM(ranking.category)) <> 'overall'
    AND EXISTS (
        SELECT 1
        FROM rankings other
        WHERE other.ranking_body_id = ranking.ranking_body_id
            AND LOWER(COALESCE(NULLIF(TRIM(other.ranking_type), ''), body.short_name)) = LOWER(ranking.ranking_type)
            AND LOWER(COALESCE(NULLIF(TRIM(other.category), ''), 'Overall')) <> LOWER(TRIM(ranking.category))
    );

UPDATE rankings ranking
INNER JOIN iris_ranking_type_fold folded ON folded.id = ranking.id
SET ranking.ranking_type = folded.new_ranking_type;

DROP TEMPORARY TABLE iris_ranking_type_fold;

CREATE TEMPORARY TABLE iris_ranking_dimension_fold AS
SELECT ranking.id,
       CONCAT(ranking.ranking_type, ' - ', COALESCE(NULLIF(TRIM(scope.name), ''), 'Unassigned'),
           IF(NULLIF(TRIM(ranking.level), '') IS NULL, '', CONCAT(' / ', TRIM(ranking.level)))) AS new_ranking_type
FROM rankings ranking
LEFT JOIN ranking_scopes scope ON scope.id = ranking.scope_id
WHERE EXISTS (
    SELECT 1
    FROM rankings other
    WHERE other.ranking_body_id = ranking.ranking_body_id
        AND LOWER(other.ranking_type) = LOWER(ranking.ranking_type)
        AND (NOT (other.scope_id <=> ranking.scope_id)
            OR NOT (LOWER(COALESCE(other.level, '')) <=> LOWER(COALESCE(ranking.level, ''))))
);

UPDATE rankings ranking
INNER JOIN iris_ranking_dimension_fold folded ON folded.id = ranking.id
SET ranking.ranking_type = folded.new_ranking_type;

DROP TEMPORARY TABLE iris_ranking_dimension_fold;

ALTER TABLE import_batches MODIFY COLUMN template_id INT NULL;

INSERT INTO template_import_profiles (
    template_id, profile_name, destination, sheet_selector, header_aliases,
    required_columns, identity_fields, mapping_rules, defaults_json
)
SELECT NULL, 'Unified Ranking History', 'ranking_history', NULL,
    JSON_OBJECT(
        'organization', JSON_ARRAY('Organization', 'Institution', 'University'),
        'ranking_type', JSON_ARRAY('Ranking Type', 'Ranking', 'List', 'Ranking List', 'Type'),
        'year', JSON_ARRAY('Year', 'Edition Year'),
        'global_rank', JSON_ARRAY('Rank', 'Global Rank', 'Overall Rank', 'World Rank'),
        'ph_rank', JSON_ARRAY('Philippine Rank', 'PH Rank', 'National Rank'),
        'source', JSON_ARRAY('Source', 'Source Information', 'Reference', 'URL')
    ),
    JSON_ARRAY('organization', 'ranking_type', 'year', 'global_rank'),
    JSON_ARRAY('organization', 'ranking_type', 'year'),
    JSON_OBJECT(
        'organization', 'Organization',
        'ranking_type', 'Ranking Type',
        'year', 'Year',
        'global_rank', 'Rank',
        'ph_rank', 'Philippine Rank',
        'source', 'Source'
    ),
    JSON_OBJECT()
WHERE NOT EXISTS (
    SELECT 1 FROM template_import_profiles
    WHERE template_id IS NULL AND profile_name = 'Unified Ranking History' AND destination = 'ranking_history'
);

INSERT IGNORE INTO summary_card_import_settings (id, active_profile_id)
SELECT 2, id FROM template_import_profiles
WHERE template_id IS NULL AND profile_name = 'Unified Ranking History' AND destination = 'ranking_history'
ORDER BY id LIMIT 1;

UPDATE summary_card_import_settings
SET active_profile_id = (
    SELECT id FROM template_import_profiles
    WHERE template_id IS NULL AND profile_name = 'Unified Ranking History' AND destination = 'ranking_history'
    ORDER BY id LIMIT 1
)
WHERE id = 2 AND active_profile_id IS NULL;

UPDATE records
SET import_profile_id = (
    SELECT active_profile_id FROM summary_card_import_settings WHERE id = 2
)
WHERE import_profile_id IS NULL AND template_id IS NULL
    AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.upload_purpose')) = 'ranking_history';

SET @drop_ranking_seed_key = (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE rankings DROP INDEX uq_rankings_seed_key', 'SELECT 1')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND INDEX_NAME = 'uq_rankings_seed_key'
);
PREPARE iris_ranking_identity_stmt FROM @drop_ranking_seed_key;
EXECUTE iris_ranking_identity_stmt;
DEALLOCATE PREPARE iris_ranking_identity_stmt;

SELECT body.name AS organization, rankings.ranking_body_id, rankings.ranking_type, rankings.year, COUNT(*) AS duplicate_count,
    GROUP_CONCAT(rankings.id ORDER BY rankings.id SEPARATOR ', ') AS ranking_ids
FROM rankings
INNER JOIN ranking_bodies body ON body.id = rankings.ranking_body_id
GROUP BY rankings.ranking_body_id, rankings.ranking_type, rankings.year, body.name
HAVING COUNT(*) > 1
ORDER BY body.name, rankings.ranking_type, rankings.year;

SET @ranking_identity_duplicates = (
    SELECT COUNT(*) FROM (
        SELECT ranking_body_id, ranking_type, year
        FROM rankings
        GROUP BY ranking_body_id, ranking_type, year
        HAVING COUNT(*) > 1
    ) duplicates_found
);
SET @ranking_identity_index = (
    SELECT IF(COUNT(*) > 0, 'SELECT ''uq_rankings_body_type_year already exists''',
        IF(@ranking_identity_duplicates = 0,
            'ALTER TABLE rankings ADD UNIQUE KEY uq_rankings_body_type_year (ranking_body_id, ranking_type, year)',
            'SELECT ''WARNING: duplicate Ranking History identities listed above; unique key was skipped'''))
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND INDEX_NAME = 'uq_rankings_body_type_year'
);
PREPARE iris_ranking_identity_stmt FROM @ranking_identity_index;
EXECUTE iris_ranking_identity_stmt;
DEALLOCATE PREPARE iris_ranking_identity_stmt;