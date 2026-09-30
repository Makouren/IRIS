-- Fictional sample data for demonstrating the Ranking History time-series chart.
-- Updates only DEMO / DEMO ONLY rows and inserts only missing years.
INSERT INTO ranking_bodies (name, short_name)
VALUES ('IRIS Demo Dataset (Fictional)', 'DEMO')
ON DUPLICATE KEY UPDATE name = VALUES(name);

UPDATE rankings AS ranking
INNER JOIN ranking_bodies AS body ON body.id = ranking.ranking_body_id
INNER JOIN (
    SELECT 2020 AS year, 1000 AS rank_value UNION ALL
    SELECT 2021, 900 UNION ALL
    SELECT 2022, 800 UNION ALL
    SELECT 2023, 700 UNION ALL
    SELECT 2024, 600 UNION ALL
    SELECT 2025, 500 UNION ALL
    SELECT 2026, 400
) AS sample ON sample.year = ranking.year
SET ranking.global_rank = CAST(sample.rank_value AS CHAR),
    ranking.rank_value = sample.rank_value
WHERE body.short_name = 'DEMO'
  AND ranking.category = 'DEMO ONLY';

INSERT INTO rankings (ranking_body_id, year, category, global_rank, rank_value, note)
SELECT body.id, sample.year, 'DEMO ONLY', CAST(sample.rank_value AS CHAR), sample.rank_value,
       'Fictional sample ranking for chart demonstration'
FROM ranking_bodies AS body
CROSS JOIN (
    SELECT 2020 AS year, 1000 AS rank_value UNION ALL
    SELECT 2021, 900 UNION ALL
    SELECT 2022, 800 UNION ALL
    SELECT 2023, 700 UNION ALL
    SELECT 2024, 600 UNION ALL
    SELECT 2025, 500 UNION ALL
    SELECT 2026, 400
) AS sample
WHERE body.short_name = 'DEMO'
  AND NOT EXISTS (
      SELECT 1 FROM rankings AS existing
      WHERE existing.ranking_body_id = body.id
        AND existing.year = sample.year
        AND existing.category = 'DEMO ONLY'
  );