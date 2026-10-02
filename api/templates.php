<?php
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function templates_fail(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function templates_csv_has_null_byte(string $path): bool {
    $handle = fopen($path, 'rb');
    if ($handle === false) return true;
    while (!feof($handle)) {
        if (str_contains((string)fread($handle, 8192), "\0")) {
            fclose($handle);
            return true;
        }
    }
    fclose($handle);
    return false;
}

try {
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/helpers/SummaryCardImportProfiles.php';
    require_once __DIR__ . '/../includes/helpers/ProfileWorkbookService.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        if (($_GET['resource'] ?? '') === 'profile_mapper_status') {
            requireRole(['super_admin'], true);
            $destination = (string)($_GET['destination'] ?? '');
            $profileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import destination.');
            echo json_encode(ProfileWorkbookService::status(db(), $destination, $profileId === false || $profileId === null ? null : (int)$profileId), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'summary_card_profiles') {
            requireRole(['super_admin'], true);
            echo json_encode(['active_profile_id' => SummaryCardImportProfiles::activeId(db()), 'profiles' => SummaryCardImportProfiles::available(db())], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'import_profiles') {
            requireRole(['super_admin'], true);
            $destination = (string)($_GET['destination'] ?? '');
            if (!in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import destination.');
            echo json_encode(['active_profile_id' => SummaryCardImportProfiles::activeId(db(), $destination), 'profiles' => SummaryCardImportProfiles::available(db(), $destination)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'import_profile') {
            requireRole(['super_admin'], true);
            $profileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
            $destination = (string)($_GET['destination'] ?? '');
            if (!$profileId || !in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import profile.');
            $profile = SummaryCardImportProfiles::get(db(), (int)$profileId, false, $destination);
            echo json_encode([
                'id' => (int)$profile['id'],
                'profile_name' => $profile['profile_name'],
                'sheet_selector' => $profile['sheet_selector'],
                'identity_fields' => $profile['identity_fields'],
                'header_aliases' => $profile['header_aliases'],
                'required_columns' => $profile['required_columns'],
                'mapping_rules' => $profile['mapping_rules'],
                'defaults' => $profile['defaults_json'],
                'workbook_header_row' => $profile['workbook_header_row'] ?? null,
                'workbook_headers' => $profile['workbook_headers'] ?? [],
                'workbook_original_filename' => $profile['workbook_original_filename'] ?? null
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'active_import_profile') {
            requireRole(['super_admin', 'admin'], true);
            $destination = (string)($_GET['destination'] ?? '');
            if (!in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import destination.');
            $profile = SummaryCardImportProfiles::active(db(), $destination);
            echo json_encode([
                'id' => (int)$profile['id'],
                'profile_name' => $profile['profile_name'],
                'template_id' => $profile['template_id'] === null ? null : (int)$profile['template_id'],
                'template_name' => $profile['template_name'] ?: $profile['profile_name'],
                'original_filename' => $profile['original_filename'] ?? null
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'summary_card_profile') {
            requireRole(['super_admin'], true);
            $profileId = filter_var($_GET['profile_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$profileId || $profileId < 1) templates_fail('Choose a valid Summary Card profile.');
            $profile = SummaryCardImportProfiles::get(db(), (int)$profileId);
            echo json_encode([
                'id' => (int)$profile['id'],
                'profile_name' => $profile['profile_name'],
                'sheet_selector' => $profile['sheet_selector'],
                'identity_fields' => $profile['identity_fields'],
                'header_aliases' => $profile['header_aliases'],
                'required_columns' => $profile['required_columns'],
                'mapping_rules' => $profile['mapping_rules'],
                'defaults' => $profile['defaults_json'],
                'workbook_header_row' => $profile['workbook_header_row'] ?? null,
                'workbook_headers' => $profile['workbook_headers'] ?? [],
                'workbook_original_filename' => $profile['workbook_original_filename'] ?? null
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'active_summary_card_profile') {
            requireRole(['super_admin', 'admin'], true);
            $profile = SummaryCardImportProfiles::active(db());
            echo json_encode([
                'id' => (int)$profile['id'],
                'profile_name' => $profile['profile_name'],
                'template_id' => $profile['template_id'] === null ? null : (int)$profile['template_id'],
                'template_name' => $profile['template_name'] ?: $profile['profile_name'],
                'original_filename' => $profile['original_filename'] ?? null
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'import_records') {
            requireRole(['super_admin'], true);
            $destination = (string)($_GET['destination'] ?? '');
            if (!in_array($destination, ['ranking_history', 'summary_cards'], true)) templates_fail('Choose a valid import destination.');
            $query = db()->prepare('SELECT records.id, records.fileName, records.fileType, records.status, records.office_name, records.uploaded_at, records.metadata,
                    COALESCE(upload_profiles.profile_name, profiles.profile_name, templates.name) AS template_name,
                    COALESCE(upload_profiles.destination, profiles.destination) AS profile_destination
                FROM records
                LEFT JOIN templates ON templates.id = records.template_id
                LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.id
                LEFT JOIN template_import_profiles upload_profiles ON upload_profiles.id = records.import_profile_id
                WHERE (upload_profiles.destination = ? OR profiles.destination = ? OR JSON_UNQUOTE(JSON_EXTRACT(records.metadata, "$.upload_purpose")) = ?)
                    AND LOWER(records.fileType) IN ("xlsx", "csv", "tsv")
                ORDER BY records.uploaded_at DESC, records.scannedAt DESC LIMIT 100');
            $query->execute([$destination, $destination, $destination]);
            $records = [];
            $storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
            $storageRoot = realpath($storagePath);
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $record) {
                $metadata = json_decode((string)($record['metadata'] ?? ''), true);
                $storedFile = is_array($metadata) ? (string)($metadata['stored_file'] ?? '') : '';
                if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $storedFile)) continue;
                $storedPath = $storageRoot ? realpath($storageRoot . DIRECTORY_SEPARATOR . $storedFile) : false;
                if (!$storageRoot || !$storedPath || dirname($storedPath) !== $storageRoot || !is_file($storedPath)) continue;
                $recordedPurpose = is_array($metadata) ? (string)($metadata['upload_purpose'] ?? '') : '';
                if ($recordedPurpose !== '' ? $recordedPurpose !== $destination : $record['profile_destination'] !== $destination) continue;
                if ($record['profile_destination'] !== null && $record['profile_destination'] !== $destination) continue;
                $records[] = [
                    'id' => $record['id'],
                    'file_name' => $record['fileName'],
                    'file_type' => strtolower((string)$record['fileType']),
                    'status' => $record['status'],
                    'office_name' => $record['office_name'],
                    'uploaded_at' => $record['uploaded_at'],
                    'template_name' => $record['template_name'] ?: ($destination === 'ranking_history' ? 'Unified Ranking History' : 'Unified Summary Cards')
                ];
            }
            echo json_encode($records, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'import_profile_for_record') {
            requireRole(['super_admin'], true);
            $recordId = trim((string)($_GET['record_id'] ?? ''));
            if ($recordId === '') templates_fail('Record id is required.');
            $profile = db()->prepare('SELECT records.template_id, records.import_profile_id, records.metadata,
                    COALESCE(upload_profiles.profile_name, profiles.profile_name, templates.name) AS template_name,
                    COALESCE(upload_profiles.destination, profiles.destination) AS destination
                FROM records
                LEFT JOIN templates ON templates.id = records.template_id
                LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.id
                LEFT JOIN template_import_profiles upload_profiles ON upload_profiles.id = records.import_profile_id
                WHERE records.id = ? LIMIT 1');
            $profile->execute([$recordId]);
            $row = $profile->fetch(PDO::FETCH_ASSOC);
            if (!$row) templates_fail('This upload has no linked template.', 409);
            $metadata = json_decode((string)($row['metadata'] ?? ''), true);
            $recordedPurpose = is_array($metadata) ? (string)($metadata['upload_purpose'] ?? '') : '';
            $row['destination'] = $row['destination'] ?: $recordedPurpose;
            if (!$row['destination']) templates_fail('Configure an import profile for this template first.', 409);
            if ($recordedPurpose !== '' && $recordedPurpose !== $row['destination']) templates_fail('This upload was submitted for a different destination than the template is currently configured for.', 409);
            if (empty($row['template_id']) && empty($row['import_profile_id']) && !in_array($row['destination'], ['summary_cards', 'ranking_history'], true)) {
                templates_fail('This upload has no assigned import profile.', 409);
            }
            $row['template_name'] = $row['template_name'] ?: ($row['destination'] === 'ranking_history' ? 'Unified Ranking History' : 'Unified Summary Cards');
            unset($row['metadata']);
            echo json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if (($_GET['resource'] ?? '') === 'ranking_bodies') {
            requireRole(['super_admin'], true);
            $pdo = db();
            $query = $pdo->prepare('SELECT bodies.id, bodies.name, bodies.short_name,
                    (SELECT COUNT(*) FROM templates WHERE ranking_body_id = bodies.id) AS template_count,
                    (SELECT COUNT(*) FROM rankings WHERE ranking_body_id = bodies.id) AS ranking_count
                FROM ranking_bodies bodies ORDER BY bodies.name ASC');
            $query->execute();
            echo json_encode($query->fetchAll(PDO::FETCH_ASSOC), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        requireRole(['super_admin', 'admin'], true);
        $pdo = db();
        $query = $pdo->prepare(($_SESSION['role'] ?? '') === 'super_admin'
            ? 'SELECT templates.id, templates.name, templates.original_filename, templates.ranking_body_id, bodies.name AS ranking_body_name, templates.is_active, templates.created_at, profiles.destination AS import_destination, profiles.sheet_selector, profiles.header_aliases, profiles.required_columns, profiles.identity_fields, profiles.mapping_rules, profiles.defaults_json FROM templates LEFT JOIN ranking_bodies bodies ON bodies.id = templates.ranking_body_id LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.id ORDER BY templates.created_at DESC, templates.id DESC'
            : 'SELECT templates.id, templates.name, templates.original_filename, templates.ranking_body_id, bodies.name AS ranking_body_name, templates.is_active, templates.created_at, profiles.destination AS import_destination, profiles.sheet_selector, profiles.header_aliases, profiles.required_columns, profiles.identity_fields, profiles.mapping_rules, profiles.defaults_json FROM templates LEFT JOIN ranking_bodies bodies ON bodies.id = templates.ranking_body_id LEFT JOIN template_import_profiles profiles ON profiles.template_id = templates.id WHERE templates.is_active = 1 ORDER BY templates.name ASC, templates.id DESC');
        $query->execute();
        $templates = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($templates as &$template) {
            foreach (['header_aliases', 'required_columns', 'identity_fields', 'mapping_rules', 'defaults_json'] as $field) {
                $template[$field] = json_decode((string)($template[$field] ?? ''), true) ?: [];
            }
            if (!$template['identity_fields'] && $template['import_destination'] === 'summary_cards') $template['identity_fields'] = ['import_key'];
        }
        unset($template);
        echo json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($method !== 'POST') templates_fail('Method not allowed.', 405);

    requireRole(['super_admin'], true);
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) templates_fail('Invalid CSRF token.', 419);

    $action = (string)($_POST['action'] ?? 'upload');
    $pdo = db();
    if ($action === 'upload-profile-workbook') {
        $destination = (string)($_POST['destination'] ?? '');
        $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$profileId || $profileId < 1 || !in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import profile.');
        try {
            $result = ProfileWorkbookService::upload($pdo, $_FILES['profile_workbook'] ?? [], $destination, (int)$profileId);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            templates_fail($exception->getMessage(), 400);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($action === 'preview-profile-workbook') {
        $destination = (string)($_POST['destination'] ?? '');
        $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
        $headerRow = filter_var($_POST['header_row'] ?? null, FILTER_VALIDATE_INT);
        if (!$profileId || $profileId < 1 || $headerRow === false || !in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid workbook profile, worksheet, and header row.');
        try {
            $result = ProfileWorkbookService::preview($pdo, (string)($_POST['workbook_token'] ?? ''), $destination, (int)$profileId, (string)($_POST['sheet_name'] ?? ''), (int)$headerRow);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            templates_fail($exception->getMessage(), 400);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($action === 'activate-import-profile') {
        $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
        $destination = (string)($_POST['destination'] ?? '');
        if (!$profileId || !in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import profile and destination.');
        $profile = SummaryCardImportProfiles::activate($pdo, (int)$profileId, (int)$_SESSION['user_id'], $destination);
        echo json_encode(['success' => true, 'active_profile_id' => (int)$profile['id'], 'profile_name' => $profile['profile_name'], 'destination' => $destination]);
        exit;
    }
    if ($action === 'save-import-profile-settings') {
        $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
        $destination = (string)($_POST['destination'] ?? '');
        $profileName = trim((string)($_POST['profile_name'] ?? ''));
        $rawProfile = (string)($_POST['profile'] ?? '');
        if (strlen($rawProfile) > 65535) templates_fail('Import profile must not exceed 64 KB.');
        $profileData = json_decode($rawProfile, true);
        if (!$profileId || !in_array($destination, ['summary_cards', 'ranking_history'], true)) templates_fail('Choose a valid import profile and destination.');
        if ($profileName === '' || strlen($profileName) > 150) templates_fail('Profile name is required and must not exceed 150 characters.');
        if (!is_array($profileData)) templates_fail('Import profile must be a valid JSON object.');
        $removedFields = ['scope', 'scope_id', 'level', 'level_id', 'edition', 'category', 'rank_low', 'rank_high', 'note', 'verification_status'];
        $aliases = $profileData['header_aliases'] ?? [];
        $mapping = $profileData['mapping_rules'] ?? [];
        $required = $profileData['required_columns'] ?? [];
        $identityFields = $profileData['identity_fields'] ?? ($destination === 'summary_cards' ? ['import_key'] : ['organization', 'ranking_type', 'year']);
        $defaults = $profileData['defaults'] ?? [];
        $sheetSelector = $profileData['sheet_selector'] ?? '';
        if (!is_array($aliases) || !is_array($mapping) || !is_array($required) || !array_is_list($required) || !is_array($identityFields) || !array_is_list($identityFields) || !is_array($defaults)) {
            templates_fail('Aliases, mappings, required columns, identities, and defaults have invalid shapes.');
        }
        if ($destination === 'ranking_history') {
            foreach ($removedFields as $field) {
                unset($aliases[$field], $mapping[$field], $defaults[$field]);
                $required = array_values(array_filter($required, static fn($requiredField): bool => $requiredField !== $field));
            }
            $allowedFields = ['organization', 'ranking_type', 'year', 'global_rank', 'ph_rank', 'source'];
            $identityFields = ['organization', 'ranking_type', 'year'];
            $required = ['organization', 'ranking_type', 'year', 'global_rank'];
            foreach (['organization', 'ranking_type', 'year', 'global_rank'] as $field) {
                if (!isset($mapping[$field])) templates_fail('Required Ranking History mapping is missing: ' . $field);
            }
        } else {
            $allowedFields = ['import_key', 'card_title', 'main_value', 'secondary_value', 'year_date', 'main_label', 'secondary_label', 'description', 'secondary_description', 'info_text', 'source_info', 'period_key'];
            if (!$identityFields || !in_array('import_key', $identityFields, true) || in_array('period_key', $identityFields, true)) templates_fail('Summary Card identity fields must include import_key and exclude period_key.');
            $allowedFields = array_values(array_unique(array_merge($allowedFields, $identityFields)));
            foreach (['import_key', 'period_key', 'main_value', 'main_label'] as $field) if (!isset($mapping[$field])) templates_fail('Required Summary Card mapping is missing: ' . $field);
            $required = array_values(array_unique(array_merge(['import_key', 'period_key', 'main_value', 'main_label'], $identityFields)));
        }
        foreach (array_unique(array_merge(array_keys($aliases), array_keys($mapping), $required, array_keys($defaults), $identityFields)) as $field) {
            if (!is_string($field) || !in_array($field, $allowedFields, true)) templates_fail('Unsupported ' . $destination . ' import field: ' . (string)$field);
        }
        $assignedHeaders = [];
        foreach ($mapping as $header) {
            if (!is_string($header) || trim($header) === '') templates_fail('Each mapping must name a worksheet header.');
            $normalized = ImportSheetReader::normalizeHeader($header);
            if (isset($assignedHeaders[$normalized])) templates_fail('Each worksheet column can only be mapped to one field.');
            $assignedHeaders[$normalized] = true;
        }
        foreach ($aliases as $list) if (!is_array($list) || !array_is_list($list) || array_filter($list, static fn($item): bool => !is_string($item))) templates_fail('Each header alias must be a list of strings.');
        foreach ($required as $field) if (!is_string($field)) templates_fail('Required columns must be strings.');
        foreach ($defaults as $value) if (!is_scalar($value) && $value !== null) templates_fail('Default values must be strings or numbers.');
        if (!is_string($sheetSelector) || strlen($sheetSelector) > 255) templates_fail('Worksheet selector must be under 256 characters.');
        SummaryCardImportProfiles::get($pdo, (int)$profileId, true, $destination);
        $workbook = null;
        $obsoleteWorkbook = null;
        $clearWorkbook = false;
        $workbookToken = trim((string)($_POST['workbook_token'] ?? ''));
        if ($workbookToken !== '') {
            if (!ProfileWorkbookService::metadataReady($pdo)) templates_fail('Run the pending migrations.');
            $headerRow = filter_var($_POST['workbook_header_row'] ?? null, FILTER_VALIDATE_INT);
            if ($headerRow === false || $headerRow < 1 || $headerRow > 10000) templates_fail('Preview and confirm a workbook header row before saving.');
            try {
                $workbook = ProfileWorkbookService::commit($pdo, $workbookToken, $destination, (int)$profileId, trim($sheetSelector), (int)$headerRow);
                $expectedHeaders = array_map([ImportSheetReader::class, 'normalizeHeader'], array_values($mapping));
                $actualHeaders = array_map([ImportSheetReader::class, 'normalizeHeader'], $workbook['headers']);
                $missingMappings = array_values(array_diff($expectedHeaders, $actualHeaders));
                if ($missingMappings) throw new InvalidArgumentException('The selected workbook header no longer matches the confirmed profile mappings. Preview the workbook again.');
            } catch (Throwable $exception) {
                if ($workbook) ProfileWorkbookService::abortCommit($workbook);
                templates_fail($exception->getMessage(), 400);
            }
        } elseif (ProfileWorkbookService::metadataReady($pdo)) {
            $storedQuery = $pdo->prepare('SELECT workbook_file_path, workbook_headers, sheet_selector FROM template_import_profiles WHERE id = ? AND destination = ?');
            $storedQuery->execute([(int)$profileId, $destination]);
            $stored = $storedQuery->fetch(PDO::FETCH_ASSOC) ?: [];
            $storedHeaders = json_decode((string)($stored['workbook_headers'] ?? ''), true);
            if (!empty($stored['workbook_file_path']) && is_array($storedHeaders)) {
                $mappedHeaders = array_map([ImportSheetReader::class, 'normalizeHeader'], array_values($mapping));
                $storedNormalized = array_map([ImportSheetReader::class, 'normalizeHeader'], $storedHeaders);
                $clearWorkbook = trim((string)($stored['sheet_selector'] ?? '')) !== trim((string)$sheetSelector)
                    || (bool)array_diff($mappedHeaders, $storedNormalized);
                if ($clearWorkbook) $obsoleteWorkbook = (string)$stored['workbook_file_path'];
            }
        }
        try {
            if ($workbook) {
                $save = $pdo->prepare('UPDATE template_import_profiles SET profile_name = ?, sheet_selector = ?, header_aliases = ?, required_columns = ?, identity_fields = ?, mapping_rules = ?, defaults_json = ?, workbook_file_path = ?, workbook_original_filename = ?, workbook_headers = ?, workbook_header_row = ? WHERE id = ? AND destination = ?');
                $save->execute([$profileName, trim($sheetSelector) !== '' ? trim($sheetSelector) : null, json_encode($aliases), json_encode($required), json_encode($identityFields), json_encode($mapping), json_encode($defaults), $workbook['workbook_file_path'], $workbook['workbook_original_filename'], $workbook['workbook_headers'], $workbook['workbook_header_row'], (int)$profileId, $destination]);
            } elseif ($clearWorkbook) {
                $save = $pdo->prepare('UPDATE template_import_profiles SET profile_name = ?, sheet_selector = ?, header_aliases = ?, required_columns = ?, identity_fields = ?, mapping_rules = ?, defaults_json = ?, workbook_file_path = NULL, workbook_original_filename = NULL, workbook_headers = NULL, workbook_header_row = NULL WHERE id = ? AND destination = ?');
                $save->execute([$profileName, trim($sheetSelector) !== '' ? trim($sheetSelector) : null, json_encode($aliases), json_encode($required), json_encode($identityFields), json_encode($mapping), json_encode($defaults), (int)$profileId, $destination]);
            } else {
                $save = $pdo->prepare('UPDATE template_import_profiles SET profile_name = ?, sheet_selector = ?, header_aliases = ?, required_columns = ?, identity_fields = ?, mapping_rules = ?, defaults_json = ? WHERE id = ? AND destination = ?');
                $save->execute([$profileName, trim($sheetSelector) !== '' ? trim($sheetSelector) : null, json_encode($aliases), json_encode($required), json_encode($identityFields), json_encode($mapping), json_encode($defaults), (int)$profileId, $destination]);
            }
        } catch (Throwable $exception) {
            if ($workbook) ProfileWorkbookService::abortCommit($workbook);
            throw $exception;
        }
        if ($workbook) ProfileWorkbookService::finishCommit($workbook);
        elseif ($obsoleteWorkbook !== null) ProfileWorkbookService::deleteStored($obsoleteWorkbook);
        echo json_encode(['success' => true, 'profile_name' => $profileName]);
        exit;
    }
    if ($action === 'delete-summary-card-upload') {
        $recordId = trim((string)($_POST['record_id'] ?? ''));
        if ($recordId === '') templates_fail('Choose a Summary Card upload to delete.');
        $record = null;
        $storedFile = '';
        $preservedRecord = false;
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT id, fileName, template_id, import_profile_id, metadata FROM records WHERE id = ? FOR UPDATE');
            $query->execute([$recordId]);
            $record = $query->fetch(PDO::FETCH_ASSOC);
            if (!$record) throw new RuntimeException('The selected upload no longer exists.', 404);
            $metadata = json_decode((string)($record['metadata'] ?? ''), true);
            $purpose = is_array($metadata) ? (string)($metadata['upload_purpose'] ?? '') : '';
            $profileId = (int)($record['import_profile_id'] ?? 0);
            if (!$profileId && !empty($record['template_id'])) {
                $profileQuery = $pdo->prepare('SELECT id FROM template_import_profiles WHERE template_id = ? AND destination = \'summary_cards\' LIMIT 1');
                $profileQuery->execute([(int)$record['template_id']]);
                $profileId = (int)$profileQuery->fetchColumn();
            }
            $isSummaryUpload = $purpose === 'summary_cards';
            if (!$isSummaryUpload && $profileId) {
                $purposeQuery = $pdo->prepare('SELECT destination FROM template_import_profiles WHERE id = ?');
                $purposeQuery->execute([$profileId]);
                $isSummaryUpload = $purposeQuery->fetchColumn() === 'summary_cards';
            }
            if (!$isSummaryUpload) throw new RuntimeException('Only Summary Card uploads can be deleted here.', 409);
            $applied = $pdo->prepare("SELECT COUNT(*) FROM import_batches WHERE BINARY source_record_id = BINARY ? AND destination = 'summary_cards' AND status = 'applied'");
            $applied->execute([$recordId]);
            $hasAppliedBatch = (int)$applied->fetchColumn() > 0;
            $historyReferences = $pdo->prepare('SELECT COUNT(*) FROM summary_card_snapshots WHERE BINARY source_record_id = BINARY ? OR BINARY last_source_record_id = BINARY ?');
            $historyReferences->execute([$recordId, $recordId]);
            $hasHistoryReferences = (int)$historyReferences->fetchColumn() > 0;
            $preservedRecord = $hasAppliedBatch || $hasHistoryReferences;
            $storedFile = is_array($metadata) ? (string)($metadata['stored_file'] ?? '') : '';
            if (!preg_match('/^[a-f0-9]{48}\.(xlsx|csv|tsv)$/', $storedFile)) {
                throw new RuntimeException('The selected upload has no supported private file to delete.', 409);
            }
            if (!$preservedRecord) {
                $pdo->prepare('DELETE FROM saved_graphs WHERE record_id = ?')->execute([$recordId]);
                $delete = $pdo->prepare('DELETE FROM records WHERE id = ?');
                $delete->execute([$recordId]);
                if ($delete->rowCount() !== 1) throw new RuntimeException('The selected upload could not be deleted.', 409);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $status = in_array($exception->getCode(), [404, 409], true) ? (int)$exception->getCode() : 500;
            templates_fail($status === 500 ? 'Unable to delete this Summary Card upload.' : $exception->getMessage(), $status);
        }

        $referenced = false;
        $references = $pdo->prepare('SELECT metadata FROM records WHERE metadata LIKE ?');
        $references->execute(['%' . $storedFile . '%']);
        foreach ($references->fetchAll(PDO::FETCH_COLUMN) as $otherMetadata) {
            $other = json_decode((string)$otherMetadata, true);
            if (is_array($other) && ($other['stored_file'] ?? null) === $storedFile) { $referenced = true; break; }
        }
        $storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
        $root = realpath($storagePath);
        $path = $root ? realpath($root . DIRECTORY_SEPARATOR . $storedFile) : false;
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $outsideWebRoot = !$documentRoot || (strcasecmp($root ?: '', $documentRoot) !== 0 && strncasecmp($root ?: '', $documentRoot . DIRECTORY_SEPARATOR, strlen($documentRoot) + 1) !== 0);
        if (!$referenced && $root && $path && dirname($path) === $root && $outsideWebRoot && is_file($path) && !@unlink($path)) {
            error_log('IRIS could not remove deleted Summary Card upload file: ' . $storedFile);
        }
        echo json_encode(['success' => true, 'deleted_record_id' => $recordId, 'deleted_file_name' => (string)($record['fileName'] ?? ''), 'preserved_record' => $preservedRecord]);
        exit;
    }
    if ($action === 'activate-summary-card-profile') {
        $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$profileId || $profileId < 1) templates_fail('Choose a valid Summary Card profile.');
        $profile = SummaryCardImportProfiles::activate($pdo, (int)$profileId, (int)$_SESSION['user_id']);
        echo json_encode(['success' => true, 'active_profile_id' => (int)$profile['id'], 'profile_name' => $profile['profile_name']]);
        exit;
    }
    if ($action === 'save-summary-card-profile') {
        $profileId = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
        $profileName = trim((string)($_POST['profile_name'] ?? ''));
        $rawProfile = (string)($_POST['profile'] ?? '');
        if (!$profileId || $profileId < 1) templates_fail('Choose a valid Summary Card profile.');
        if ($profileName === '' || strlen($profileName) > 150) templates_fail('Profile name is required and must not exceed 150 characters.');
        if (strlen($rawProfile) > 65535) templates_fail('Import profile must not exceed 64 KB.');
        $profileData = json_decode($rawProfile, true);
        if (!is_array($profileData)) templates_fail('Summary Card profile must be valid JSON.');
        $aliases = $profileData['header_aliases'] ?? [];
        $mapping = $profileData['mapping_rules'] ?? [];
        $required = $profileData['required_columns'] ?? [];
        $identityFields = $profileData['identity_fields'] ?? ['import_key'];
        $defaults = $profileData['defaults'] ?? [];
        $sheetSelector = $profileData['sheet_selector'] ?? '';
        if (!is_array($aliases) || !is_array($mapping) || !is_array($required) || !array_is_list($required) || !is_array($identityFields) || !array_is_list($identityFields) || !is_array($defaults)) {
            templates_fail('Summary Card aliases, mappings, required columns, identities, and defaults have invalid shapes.');
        }
        if (!$identityFields || !in_array('import_key', $identityFields, true) || in_array('period_key', $identityFields, true)) {
            templates_fail('Summary Card identity fields must include import_key and exclude period_key.');
        }
        foreach (array_unique(array_merge(array_keys($aliases), array_keys($mapping), $required, array_keys($defaults), $identityFields)) as $field) {
            if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field)) templates_fail('Summary Card canonical field names are invalid.');
        }
        foreach ($identityFields as $field) if (!is_string($field)) templates_fail('Summary Card identity fields must be canonical names.');
        foreach ($mapping as $header) if (!is_string($header) || trim($header) === '') templates_fail('Each Summary Card mapping must name a worksheet header.');
        foreach ($aliases as $list) if (!is_array($list) || !array_is_list($list) || array_filter($list, static fn($item): bool => !is_string($item))) templates_fail('Each Summary Card header alias must be a list of strings.');
        foreach ($required as $field) if (!is_string($field)) templates_fail('Summary Card required fields must be strings.');
        foreach ($defaults as $value) if (!is_scalar($value) && $value !== null) templates_fail('Summary Card defaults must be strings or numbers.');
        if (!is_string($sheetSelector) || strlen($sheetSelector) > 255) templates_fail('Worksheet selector must be under 256 characters.');
        $required = array_values(array_unique(array_merge($required, $identityFields)));
        foreach (['import_key', 'period_key', 'main_value', 'main_label'] as $field) if (!isset($mapping[$field])) templates_fail('Required Summary Card mapping is missing: ' . $field);
        SummaryCardImportProfiles::get($pdo, (int)$profileId, true);
        $save = $pdo->prepare('UPDATE template_import_profiles SET profile_name = ?, sheet_selector = ?, header_aliases = ?, required_columns = ?, identity_fields = ?, mapping_rules = ?, defaults_json = ? WHERE id = ? AND destination = \'summary_cards\'');
        $save->execute([$profileName, trim($sheetSelector) !== '' ? trim($sheetSelector) : null, json_encode($aliases), json_encode($required), json_encode($identityFields), json_encode($mapping), json_encode($defaults), (int)$profileId]);
        if (!$save->rowCount()) {
            $exists = $pdo->prepare('SELECT id FROM template_import_profiles WHERE id = ? AND destination = \'summary_cards\'');
            $exists->execute([(int)$profileId]);
            if (!$exists->fetchColumn()) templates_fail('The Summary Card profile is unavailable or its template is inactive.', 409);
        }
        echo json_encode(['success' => true, 'profile_name' => $profileName]);
        exit;
    }
    if ($action === 'save-import-profile') {
        $templateId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $destination = (string)($_POST['destination'] ?? '');
        if (!$templateId || !in_array($destination, ['ranking_history', 'summary_cards'], true)) templates_fail('Choose a valid template and import destination.');
        $rawProfile = (string)($_POST['profile'] ?? '');
        if (strlen($rawProfile) > 65535) templates_fail('Import profile must not exceed 64 KB.');
        $profileData = json_decode($rawProfile, true);
        if (!is_array($profileData)) templates_fail('Import profile must be valid JSON.');
        $allowedFields = $destination === 'ranking_history'
            ? ['organization', 'ranking_type', 'year', 'global_rank', 'ph_rank', 'source']
            : ['import_key', 'card_title', 'main_value', 'secondary_value', 'year_date', 'main_label', 'secondary_label', 'description', 'secondary_description', 'info_text', 'source_info', 'period_key'];
        $aliases = $profileData['header_aliases'] ?? [];
        $mapping = $profileData['mapping_rules'] ?? [];
        $required = $profileData['required_columns'] ?? [];
        $identityFields = $profileData['identity_fields'] ?? ($destination === 'summary_cards' ? ['import_key'] : ['organization', 'ranking_type', 'year']);
        $defaults = $profileData['defaults'] ?? [];
        if (!is_array($aliases) || !is_array($mapping) || !is_array($required) || !array_is_list($required) || !is_array($defaults)) {
            templates_fail('Aliases, mappings, required columns, and defaults must have the expected object/list shapes.');
        }
        if (!is_array($identityFields) || !array_is_list($identityFields) || array_filter($identityFields, static fn($field): bool => !is_string($field))) {
            templates_fail('Identity fields must be a list of canonical field names.');
        }
        if ($destination === 'ranking_history') {
            foreach (['scope', 'scope_id', 'level', 'level_id', 'edition', 'category', 'rank_low', 'rank_high', 'note', 'verification_status'] as $removedField) {
                unset($aliases[$removedField], $mapping[$removedField], $defaults[$removedField]);
                $required = array_values(array_filter($required, static fn($field): bool => $field !== $removedField));
            }
            $identityFields = ['organization', 'ranking_type', 'year'];
            $required = array_values(array_unique(array_merge($required, $identityFields)));
        } else {
            if (!$identityFields || !in_array('import_key', $identityFields, true) || in_array('period_key', $identityFields, true)) {
                templates_fail('Summary Card identity fields must include import_key and exclude period_key.');
            }
            foreach ($identityFields as $field) {
                if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field)) templates_fail('Identity fields must use canonical field names.');
            }
            $required = array_values(array_unique(array_merge($required, $identityFields)));
            $allowedFields = array_values(array_unique(array_merge($allowedFields, $identityFields)));
        }
        if (!$mapping) templates_fail('Add at least one worksheet field mapping.');
        $minimumMappings = $destination === 'ranking_history' ? ['organization', 'ranking_type', 'year', 'global_rank'] : ['import_key', 'period_key', 'main_value'];
        foreach ($minimumMappings as $field) {
            if (!isset($mapping[$field])) templates_fail('Required field mapping is missing: ' . $field);
        }
        foreach (array_unique(array_merge(array_keys($aliases), array_keys($mapping), $required, array_keys($defaults))) as $field) {
            if (!in_array($field, $allowedFields, true)) templates_fail('Unsupported ' . $destination . ' import field: ' . (string)$field);
        }
        foreach ($mapping as $field => $header) {
            if (!is_string($header) || trim($header) === '') templates_fail('Each mapping must name a worksheet header.');
        }
        foreach ($aliases as $field => $list) {
            if (!is_array($list) || !array_is_list($list) || array_filter($list, static fn($item): bool => !is_string($item))) {
                templates_fail('Each header alias must be a list of header names.');
            }
        }
        foreach ($required as $field) if (!is_string($field)) templates_fail('Required column names must be strings.');
        foreach ($defaults as $value) if (!is_scalar($value) && $value !== null) templates_fail('Default values must be strings or numbers.');
        $templateQuery = $pdo->prepare('SELECT id FROM templates WHERE id = ?');
        $templateQuery->execute([$templateId]);
        if ($templateQuery->fetchColumn() === false) templates_fail('Template not found.', 404);
        $sheetSelector = $profileData['sheet_selector'] ?? '';
        if (!is_string($sheetSelector) || strlen($sheetSelector) > 255) templates_fail('Worksheet selector must be a name under 256 characters.');
        $sheetSelector = trim($sheetSelector);
        $save = $pdo->prepare('INSERT INTO template_import_profiles (template_id, destination, sheet_selector, header_aliases, required_columns, identity_fields, mapping_rules, defaults_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE destination = VALUES(destination), sheet_selector = VALUES(sheet_selector), header_aliases = VALUES(header_aliases), required_columns = VALUES(required_columns), identity_fields = VALUES(identity_fields), mapping_rules = VALUES(mapping_rules), defaults_json = VALUES(defaults_json), created_by = VALUES(created_by)');
        $save->execute([$templateId, $destination, $sheetSelector !== '' ? $sheetSelector : null, json_encode($aliases), json_encode($required), json_encode($identityFields), json_encode($mapping), json_encode($defaults), (int)$_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'save-ranking-body') {
        $bodyId = filter_var($_POST['ranking_body_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $bodyName = trim((string)($_POST['body_name'] ?? ''));
        $shortName = trim((string)($_POST['short_name'] ?? ''));
        $nameLength = function_exists('mb_strlen') ? mb_strlen($bodyName, 'UTF-8') : strlen($bodyName);
        if ($bodyName === '' || $nameLength > 100) templates_fail('Ranking body name is required and must not exceed 100 characters.');
        if ($shortName === '' || strlen($shortName) > 20) templates_fail('Short name is required and must not exceed 20 characters.');

        $duplicate = $pdo->prepare('SELECT id FROM ranking_bodies WHERE (LOWER(name) = LOWER(?) OR LOWER(short_name) = LOWER(?)) AND id <> ? LIMIT 1');
        $duplicate->execute([$bodyName, $shortName, $bodyId ?? 0]);
        if ($duplicate->fetchColumn()) templates_fail('A ranking body with that name or short name already exists.', 409);

        if ($bodyId) {
            $update = $pdo->prepare('UPDATE ranking_bodies SET name = ?, short_name = ? WHERE id = ?');
            $update->execute([$bodyName, $shortName, $bodyId]);
            if (!$update->rowCount()) {
                $exists = $pdo->prepare('SELECT id FROM ranking_bodies WHERE id = ?');
                $exists->execute([$bodyId]);
                if (!$exists->fetchColumn()) templates_fail('Ranking body not found.', 404);
            }
            echo json_encode(['success' => true, 'id' => $bodyId]);
            exit;
        }

        $insert = $pdo->prepare('INSERT INTO ranking_bodies (name, short_name) VALUES (?, ?)');
        $insert->execute([$bodyName, $shortName]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }
    if ($action === 'delete-ranking-body') {
        $bodyId = filter_var($_POST['ranking_body_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$bodyId || $bodyId < 1) templates_fail('Ranking body id is required.');
        $pdo->beginTransaction();
        try {
            $bodyQuery = $pdo->prepare('SELECT id FROM ranking_bodies WHERE id = ? FOR UPDATE');
            $bodyQuery->execute([$bodyId]);
            if (!$bodyQuery->fetchColumn()) templates_fail('Ranking body not found.', 404);
            $templateQuery = $pdo->prepare('SELECT COUNT(*) FROM templates WHERE ranking_body_id = ?');
            $templateQuery->execute([$bodyId]);
            $templateCount = (int)$templateQuery->fetchColumn();
            $rankingQuery = $pdo->prepare('SELECT COUNT(*) FROM rankings WHERE ranking_body_id = ?');
            $rankingQuery->execute([$bodyId]);
            $rankingCount = (int)$rankingQuery->fetchColumn();
            if ($templateCount > 0 || $rankingCount > 0) {
                $pdo->rollBack();
                templates_fail("Cannot delete this ranking body: {$rankingCount} ranking row(s) and {$templateCount} template(s) still use it. Reassign or unlink them first.", 409);
            }
            $pdo->prepare('DELETE FROM ranking_bodies WHERE id = ?')->execute([$bodyId]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
    if ($action === 'upload') {
        $name = trim((string)($_POST['name'] ?? ''));
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($name === '' || $nameLength > 150) templates_fail('Template name is required and must not exceed 150 characters.');
        $rankingBodyId = null;
        if (isset($_POST['ranking_body_id']) && $_POST['ranking_body_id'] !== '') {
            $rankingBodyId = filter_var($_POST['ranking_body_id'], FILTER_VALIDATE_INT);
            if (!$rankingBodyId || $rankingBodyId < 1) templates_fail('Choose a valid ranking body.');
            $bodyQuery = $pdo->prepare('SELECT id FROM ranking_bodies WHERE id = ?');
            $bodyQuery->execute([$rankingBodyId]);
            if (!$bodyQuery->fetchColumn()) templates_fail('Ranking body not found.', 404);
        }

        $file = $_FILES['template_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) templates_fail('Choose a template file.');
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'xls', 'csv', 'docx'];
        if (!in_array($extension, $allowedExtensions, true) || (int)$file['size'] < 1 || (int)$file['size'] > 100 * 1024 * 1024) {
            templates_fail('Choose an XLSX, XLS, CSV, or DOCX file under 100 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $signature = file_get_contents($file['tmp_name'], false, null, 0, 16) ?: '';
        $validMime = match ($extension) {
            'csv' => in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)
                && !templates_csv_has_null_byte($file['tmp_name']),
            'xls' => in_array($mime, ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/x-cfb', 'application/octet-stream'], true)
                && str_starts_with($signature, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'xlsx', 'docx' => in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'], true)
                && str_starts_with($signature, "PK\x03\x04")
                && class_exists(ZipArchive::class)
                && (static function () use ($file, $extension): bool {
                    $zip = new ZipArchive();
                    if ($zip->open($file['tmp_name']) !== true) return false;
                    $requiredEntry = $extension === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';
                    $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($requiredEntry) !== false;
                    $zip->close();
                    return $valid;
                })(),
            default => false,
        };
        if (!$validMime) templates_fail('The file contents do not match the selected file type.');

        $storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
        if (!is_dir($storagePath) && !mkdir($storagePath, 0750, true) && !is_dir($storagePath)) templates_fail('Private upload storage is unavailable.', 500);
        $realStorage = realpath($storagePath);
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if (!$realStorage || ($documentRoot && strncasecmp($realStorage, $documentRoot, strlen($documentRoot)) === 0)) templates_fail('Upload storage must be outside the web root.', 500);

        $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
        $storedPath = $realStorage . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $storedPath)) templates_fail('Unable to store the template file.', 500);
        chmod($storedPath, 0640);
        $originalName = basename((string)$file['name']);
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'template.' . $extension;
        $originalName = function_exists('mb_substr') ? mb_substr($originalName, 0, 255, 'UTF-8') : substr($originalName, 0, 255);

        try {
            $insert = $pdo->prepare('INSERT INTO templates (name, file_path, original_filename, ranking_body_id, uploaded_by) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$name, $storedName, $originalName, $rankingBodyId, (int)$_SESSION['user_id']]);
        } catch (Throwable $exception) {
            if (is_file($storedPath)) unlink($storedPath);
            throw $exception;
        }
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) templates_fail('Template id is required.');
    if ($action === 'set-ranking-body') {
        $rankingBodyId = null;
        if (isset($_POST['ranking_body_id']) && $_POST['ranking_body_id'] !== '') {
            $rankingBodyId = filter_var($_POST['ranking_body_id'], FILTER_VALIDATE_INT);
            if (!$rankingBodyId || $rankingBodyId < 1) templates_fail('Choose a valid ranking body.');
            $bodyQuery = $pdo->prepare('SELECT id FROM ranking_bodies WHERE id = ?');
            $bodyQuery->execute([$rankingBodyId]);
            if (!$bodyQuery->fetchColumn()) templates_fail('Ranking body not found.', 404);
        }
        $update = $pdo->prepare('UPDATE templates SET ranking_body_id = ? WHERE id = ?');
        $update->execute([$rankingBodyId, $id]);
        if (!$update->rowCount()) {
            $exists = $pdo->prepare('SELECT id FROM templates WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) templates_fail('Template not found.', 404);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if (in_array($action, ['deactivate', 'activate'], true)) {
        $update = $pdo->prepare('UPDATE templates SET is_active = ? WHERE id = ?');
        $update->execute([$action === 'activate' ? 1 : 0, $id]);
        if (!$update->rowCount()) {
            $exists = $pdo->prepare('SELECT id FROM templates WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) templates_fail('Template not found.', 404);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'delete') {
        $pdo->beginTransaction();
        try {
            $templateQuery = $pdo->prepare('SELECT id, file_path FROM templates WHERE id = ? FOR UPDATE');
            $templateQuery->execute([$id]);
            $template = $templateQuery->fetch(PDO::FETCH_ASSOC);
            if (!$template) {
                $pdo->rollBack();
                templates_fail('Template not found.', 404);
            }
            $countQuery = $pdo->prepare('SELECT COUNT(*) FROM records WHERE template_id = ?');
            $countQuery->execute([$id]);
            $usageCount = (int)$countQuery->fetchColumn();
            if ($usageCount > 0) {
                $pdo->rollBack();
                templates_fail($usageCount . ' uploads use this template — deactivate instead.', 409);
            }
            $pdo->prepare('DELETE FROM templates WHERE id = ?')->execute([$id]);
            $pdo->commit();
            $storagePath = getenv('IRIS_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'iris-private-uploads';
            $realStorage = realpath($storagePath);
            $filePath = $realStorage ? realpath($realStorage . DIRECTORY_SEPARATOR . (string)$template['file_path']) : false;
            if ($realStorage && $filePath && dirname($filePath) === $realStorage && is_file($filePath)) unlink($filePath);
            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
    templates_fail('Unknown template action.');
} catch (Throwable $exception) {
    error_log('IRIS templates API failure: ' . $exception);
    if (ob_get_level() > 0) ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unable to process template request.']);
    exit;
}
