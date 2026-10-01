<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function accounts_fail(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function accounts_input(): array {
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($data) ? $data : [];
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT id, username, email, role, office_name, is_active, created_at
            FROM users WHERE role IN ('admin', 'user') ORDER BY role, office_name, username")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
    if ($method !== 'POST') accounts_fail('Method not allowed.', 405);

    $expected = (string)($_SESSION['_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) accounts_fail('Invalid CSRF token.', 419);
    $data = accounts_input();
    $action = (string)($data['action'] ?? 'save');

    if ($action === 'save') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $role = (string)($data['role'] ?? '');
        $officeName = trim((string)($data['office_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $username)) accounts_fail('Username may contain letters, numbers, underscores, and hyphens only.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) accounts_fail('Enter a valid email address.');
        if (!in_array($role, ['admin', 'user'], true)) accounts_fail('Choose Admin or User.');
        $officeLength = function_exists('mb_strlen') ? mb_strlen($officeName, 'UTF-8') : strlen($officeName);
        if ($role === 'admin' && ($officeName === '' || $officeLength > 100)) accounts_fail('Office name is required and must not exceed 100 characters.');
        if ($role === 'user') $officeName = '';
        if (!$id && strlen($password) < 8) accounts_fail('Passwords must contain at least 8 characters.');
        if ($password !== '' && strlen($password) < 8) accounts_fail('Passwords must contain at least 8 characters.');

        $duplicate = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        $duplicate->execute([$username, $email, $id ?? 0]);
        if ($duplicate->fetchColumn()) accounts_fail('Username or email already exists.', 409);

        if ($id) {
            $sets = ['username = ?', 'email = ?', 'role = ?', 'office_name = ?'];
            $values = [$username, $email, $role, $officeName !== '' ? $officeName : null];
            if ($password !== '') {
                $sets[] = 'password = ?';
                $values[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $values[] = $id;
            $update = $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ? AND role IN ('admin', 'user')");
            $update->execute($values);
            if (!$update->rowCount()) {
                $exists = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role IN ('admin', 'user')");
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) accounts_fail('Account not found.', 404);
            }
        } else {
            $insert = $pdo->prepare('INSERT INTO users (username, email, password, role, office_name) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $officeName !== '' ? $officeName : null]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) accounts_fail('Account id is required.');
    if ($action === 'deactivate' || $action === 'activate') {
        $update = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role IN ('admin', 'user')");
        $update->execute([$action === 'activate' ? 1 : 0, $id]);
        if (!$update->rowCount()) accounts_fail('Account not found.', 404);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'reset-password') {
        $password = (string)($data['password'] ?? '');
        if (strlen($password) < 8) accounts_fail('Passwords must contain at least 8 characters.');
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role IN ('admin', 'user')");
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        if (!$update->rowCount()) accounts_fail('Account not found.', 404);
        echo json_encode(['success' => true]);
        exit;
    }
    accounts_fail('Unknown account action.');
} catch (Throwable $exception) {
    accounts_fail('Unable to process account request.', 500);
}