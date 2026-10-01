<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/helpers/ImportSheetReader.php';
require_once __DIR__ . '/../../includes/helpers/TemplateImportSupport.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
    }
}

function assertContains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . ' Needle: ' . $needle . ' Haystack: ' . $haystack);
    }
}

$profileLiteral = [
    'mapping_rules' => [
        'import_key' => 'import_key',
        'period_key' => 'period_key',
        'main_value' => 'main_value',
    ],
    'header_aliases' => [
        'import_key' => ['import_key'],
        'period_key' => ['period_key'],
        'main_value' => ['main_value'],
    ],
    'required_columns' => ['import_key', 'period_key', 'main_value'],
    'defaults_json' => [],
];

$resolvedLiteral = TemplateImportSupport::resolveFieldIndexes(
    ['import_key', 'period_key', 'main_value'],
    $profileLiteral,
    ['import_key', 'period_key', 'main_value']
);
assertSame(0, $resolvedLiteral['import_key'], 'literal canonical field should resolve to the literal column.');
assertSame(1, $resolvedLiteral['period_key'], 'literal period_key should resolve to the literal column.');
assertSame(2, $resolvedLiteral['main_value'], 'literal main_value should resolve to the literal column.');

$profileMapped = [
    'mapping_rules' => [
        'import_key' => 'Global Label',
        'period_key' => 'Year',
        'main_value' => 'Main Value',
    ],
    'header_aliases' => [
        'import_key' => ['Global Label', 'Short Key'],
        'period_key' => ['Year'],
        'main_value' => ['Main Value'],
    ],
    'required_columns' => ['import_key', 'period_key', 'main_value'],
    'defaults_json' => [],
];

$resolvedMapped = TemplateImportSupport::resolveFieldIndexes(
    ['Title', 'Global Label', 'Year', 'Main Value'],
    $profileMapped,
    ['import_key', 'period_key', 'main_value']
);
assertSame(1, $resolvedMapped['import_key'], 'mapped Global Label -> import_key should resolve.');
assertSame(2, $resolvedMapped['period_key'], 'mapped Year -> period_key should resolve.');
assertSame(3, $resolvedMapped['main_value'], 'mapped Main Value -> main_value should resolve.');

$reflection = new ReflectionMethod(TemplateImportSupport::class, 'builtInSummaryProfile');
$reflection->setAccessible(true);
$builtInProfile = $reflection->invoke(null);

$builtInWuriHeaders = ['Main Descriptive Text', 'Year', 'Rank / Rank Bracket', 'Second Description', 'Information Text'];
$builtInResolved = TemplateImportSupport::resolveFieldIndexes(
    $builtInWuriHeaders,
    ['mapping_rules' => $builtInProfile['mapping_rules'], 'header_aliases' => $builtInProfile['header_aliases'], 'required_columns' => $builtInProfile['required_columns'], 'defaults_json' => []],
    ['import_key', 'period_key', 'main_value', 'main_label', 'secondary_description', 'info_text']
);
assertSame(0, $builtInResolved['import_key'], 'built-in Summary Card profile should resolve Main Descriptive Text to import_key.');
assertSame(0, $builtInResolved['main_label'], 'built-in Summary Card profile should resolve Main Descriptive Text to main_label.');
assertSame(1, $builtInResolved['period_key'], 'built-in Summary Card profile should resolve Year to period_key.');
assertSame(2, $builtInResolved['main_value'], 'built-in Summary Card profile should resolve Rank / Rank Bracket to main_value.');
assertSame(3, $builtInResolved['secondary_description'], 'built-in Summary Card profile should resolve Second Description to secondary_description.');
assertSame(4, $builtInResolved['info_text'], 'built-in Summary Card profile should resolve Information Text to info_text.');

$identityFields = ['import_key', 'source', 'metric', 'category', 'record_type'];
$identityHeaders = ['Global Label', 'Source System', 'Metric', 'Category', 'Record Type', 'Period', 'Value'];
$identityProfile = [
    'mapping_rules' => [
        'import_key' => 'Global Label',
        'source' => 'Source System',
        'metric' => 'Metric',
        'category' => 'Category',
        'record_type' => 'Record Type',
        'period_key' => 'Period',
        'main_value' => 'Value',
    ],
    'header_aliases' => [],
];
$resolvedIdentityFields = TemplateImportSupport::resolveFieldIndexes(
    $identityHeaders,
    $identityProfile,
    array_merge($identityFields, ['period_key', 'main_value'])
);
assertSame(1, $resolvedIdentityFields['source'], 'configured source identity field should resolve from its profile mapping.');
assertSame(2, $resolvedIdentityFields['metric'], 'configured metric identity field should resolve from its profile mapping.');
assertSame(3, $resolvedIdentityFields['category'], 'configured category identity field should resolve from its profile mapping.');
assertSame(4, $resolvedIdentityFields['record_type'], 'configured record-type identity field should resolve from its profile mapping.');

$unifiedIdentity = [
    'import_key' => 'clsu-performance',
    'source' => 'WURI',
    'metric' => 'overall rank',
    'category' => 'overall',
    'record_type' => 'ranking',
];
$overall2025 = TemplateImportSupport::canonicalImportKey($unifiedIdentity, $identityFields, 'Unified', 2);
assertSame(
    $overall2025,
    TemplateImportSupport::canonicalImportKey($unifiedIdentity, $identityFields, 'Unified', 3),
    'same configured identity should reuse the same card key across periods.'
);
assertSame(
    TemplateImportSupport::identityPeriodKey($unifiedIdentity, $identityFields, 'Y:2025', 'Unified', 2),
    TemplateImportSupport::identityPeriodKey($unifiedIdentity, $identityFields, 'Y:2025', 'Unified', 3),
    'same configured identity and period should produce the same duplicate key.'
);
assertTrue(
    TemplateImportSupport::identityPeriodKey($unifiedIdentity, $identityFields, 'Y:2025', 'Unified', 2)
        !== TemplateImportSupport::identityPeriodKey($unifiedIdentity, $identityFields, 'Y:2026', 'Unified', 4),
    'same configured identity in different periods should remain valid history.'
);
foreach ([
    array_replace($unifiedIdentity, ['source' => 'QS']),
    array_replace($unifiedIdentity, ['metric' => 'rank bracket']),
    array_replace($unifiedIdentity, ['category' => 'SDG 4']),
    array_replace($unifiedIdentity, ['record_type' => 'rating']),
] as $distinctIdentity) {
    assertTrue(
        TemplateImportSupport::identityPeriodKey($distinctIdentity, $identityFields, 'Y:2025', 'Unified', 5)
            !== TemplateImportSupport::identityPeriodKey($unifiedIdentity, $identityFields, 'Y:2025', 'Unified', 2),
        'different configured source, metric, category, or record type should coexist for the same label and period.'
    );
}
$seenIdentityPeriods = [];
TemplateImportSupport::validateUniqueIdentityPeriod($seenIdentityPeriods, $unifiedIdentity, $identityFields, 'Y:2025', 'Unified', 2);
TemplateImportSupport::validateUniqueIdentityPeriod($seenIdentityPeriods, array_replace($unifiedIdentity, ['category' => 'SDG 4']), $identityFields, 'Y:2025', 'Unified', 3);
TemplateImportSupport::validateUniqueIdentityPeriod($seenIdentityPeriods, $unifiedIdentity, $identityFields, 'Y:2026', 'Unified', 4);
try {
    TemplateImportSupport::validateUniqueIdentityPeriod($seenIdentityPeriods, $unifiedIdentity, $identityFields, 'Y:2025', 'Unified', 5);
    throw new RuntimeException('An identical configured identity and period should be rejected.');
} catch (RuntimeException $exception) {
    assertContains('duplicate configured Summary Card identity and period', $exception->getMessage(), 'Only a duplicate configured identity and period should be rejected.');
}

$unifiedHeaders = ['Global Label', 'Title', 'Main Value', 'Main Label', 'Year / Date', 'Secondary Label', 'Secondary Value', 'Description', 'Italic Supporting Text', 'Information', 'Display Order', 'Categories', 'Display Precision'];
$unifiedProfile = [
    'mapping_rules' => [
        'import_key' => 'Global Label',
        'card_title' => 'Title',
        'main_value' => 'Main Value',
        'main_label' => 'Main Label',
        'period_key' => 'Year / Date',
    ],
    'header_aliases' => [],
];
$unifiedIndexes = TemplateImportSupport::resolveFieldIndexes(
    $unifiedHeaders,
    $unifiedProfile,
    ['import_key', 'card_title', 'main_value', 'main_label', 'period_key']
);
assertSame(0, $unifiedIndexes['import_key'], 'Global Label should map to the stable Summary Card import_key without an import_key column.');
assertSame(1, $unifiedIndexes['card_title'], 'Title should resolve from the unified template.');
assertSame(3, $unifiedIndexes['main_label'], 'Main Label should resolve from the unified template.');
assertSame(4, $unifiedIndexes['period_key'], 'Year / Date should map to canonical period_key.');
$overallRank = ['import_key' => 'WURI', 'card_title' => "World's Universities with Real Impact", 'main_label' => 'Global rank'];
$fundingCategory = ['import_key' => 'WURI', 'card_title' => 'Funding for Sustainability', 'main_label' => 'Global category rank'];
$costBenefitCategory = ['import_key' => 'WURI', 'card_title' => 'Cost-Benefit Management', 'main_label' => 'Global category rank'];
$unifiedFields = ['import_key', 'main_label', 'card_title'];
assertTrue(
    TemplateImportSupport::identityPeriodKey($overallRank, $unifiedFields, 'Y:2025', 'Summary Cards', 3)
        !== TemplateImportSupport::identityPeriodKey($fundingCategory, $unifiedFields, 'Y:2025', 'Summary Cards', 4),
    'The actual WURI overall and category rows must coexist for the same Global Label and period.'
);
assertTrue(
    TemplateImportSupport::identityPeriodKey($fundingCategory, $unifiedFields, 'Y:2025', 'Summary Cards', 4)
        !== TemplateImportSupport::identityPeriodKey($costBenefitCategory, $unifiedFields, 'Y:2025', 'Summary Cards', 5),
    'Different category titles must resolve to distinct configured Summary Card identities.'
);
assertTrue(
    TemplateImportSupport::identityPeriodKey($overallRank, $unifiedFields, 'Y:2025', 'Summary Cards', 3)
        !== TemplateImportSupport::identityPeriodKey($overallRank, $unifiedFields, 'Y:2026', 'Summary Cards', 2),
    'The same unified Summary Card identity in another period must remain historical data.'
);

$profileMissing = [
    'mapping_rules' => [
        'period_key' => 'Year',
        'main_value' => 'Main Value',
    ],
    'header_aliases' => [
        'period_key' => ['Year'],
        'main_value' => ['Main Value'],
    ],
    'required_columns' => ['import_key', 'period_key', 'main_value'],
    'defaults_json' => [],
];

try {
    TemplateImportSupport::resolveFieldIndexes(
        ['Title', 'Year', 'Main Value'],
        $profileMissing,
        ['import_key', 'period_key', 'main_value']
    );
    throw new RuntimeException('Missing mapping should raise InvalidArgumentException.');
} catch (InvalidArgumentException $exception) {
    assertContains('import_key', $exception->getMessage(), 'Missing mapping message must name the canonical field.');
    assertContains('mapping', strtolower($exception->getMessage()), 'Missing mapping message must explain the required profile mapping.');
}

try {
    ImportSheetReader::selectSheet(
        [
            ['name' => 'Sheet A', 'headers' => ['Global Label', 'Year', 'Main Value']],
            ['name' => 'Sheet B', 'headers' => ['Global Label', 'Year', 'Main Value']],
        ],
        null,
        [['Global Label'], ['Year'], ['Main Value']]
    );
    throw new RuntimeException('Multiple matching worksheets should require explicit sheet selection.');
} catch (ImportSheetSelectionRequired $exception) {
    assertSame(2, count($exception->candidates), 'Ambiguous worksheets should list every matching candidate.');
}

$sheetHeaders = ['Title', 'Global Label', 'Year', 'Main Value'];
$previewIndexes = TemplateImportSupport::resolveFieldIndexes($sheetHeaders, $profileMapped, ['import_key', 'period_key', 'main_value']);
$applyIndexes = TemplateImportSupport::resolveFieldIndexes($sheetHeaders, $profileMapped, ['import_key', 'period_key', 'main_value']);
assertSame($previewIndexes, $applyIndexes, 'Preview and Apply should use the same resolved mapping indexes.');

echo "template import mapping tests passed\n";
