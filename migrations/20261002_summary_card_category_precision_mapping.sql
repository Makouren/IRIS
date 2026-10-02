USE iris_db;

UPDATE template_import_profiles
SET header_aliases = JSON_SET(
        header_aliases,
        '$.category_names', JSON_ARRAY('Categories'),
        '$.display_precision', JSON_ARRAY('Display Precision')
    ),
    mapping_rules = JSON_SET(
        mapping_rules,
        '$.category_names', 'Categories',
        '$.display_precision', 'Display Precision'
    )
WHERE destination = 'summary_cards'
    AND template_id IS NULL
    AND profile_name = 'Unified Summary Cards'
    AND (JSON_EXTRACT(mapping_rules, '$.category_names') IS NULL
        OR JSON_EXTRACT(mapping_rules, '$.display_precision') IS NULL);