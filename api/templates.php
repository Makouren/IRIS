<?php
require_once __DIR__ . '/../includes/functions.php';
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
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
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
            ? 'SELECT templates.id, templates.name, templates.original_filename, templates.ranking_body_id, bodies.name AS ranking_body_name, templates.is_active, templates.created_at FROM templates LEFT JOIN ranking_bodies bodies ON bodies.id = templates.ranking_body_id ORDER BY templates.created_at DESC, templates.id DESC'
            : 'SELECT templates.id, templates.name, templates.original_filename, templates.ranking_body_id, bodies.name AS ranking_body_name, templates.is_active, templates.created_at FROM templates LEFT JOIN ranking_bodies bodies ON bodies.id = templates.ranking_body_id WHERE templates.is_active = 1 ORDER BY templates.name ASC, templates.id DESC');
        $query->execute();
        echo json_encode($query->fetchAll(PDO::FETCH_ASSOC), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($method !== 'POST') templates_fail('Method not allowed.', 405);

    requireRole(['super_admin'], true);
    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) templates_fail('Invalid CSRF token.', 419);

    $action = (string)($_POST['action'] ?? 'upload');
    $pdo = db();
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
    templates_fail('Unable to process template request.', 500);
}
