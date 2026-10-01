UPDATE `saved_graphs`
SET `chart_data` = JSON_SET(
        COALESCE(`chart_data`, JSON_OBJECT()),
        '$.irisConfig',
        JSON_REMOVE(
            JSON_SET(
                COALESCE(JSON_EXTRACT(`chart_data`, '$.irisConfig'), JSON_OBJECT()),
                '$.type', 'bar'
            ),
            '$.roseMode'
        )
    ),
    `chart_type` = 'bar'
WHERE LOWER(`chart_type`) IN ('rose', 'nightingale', 'polararea', 'polar-area');
