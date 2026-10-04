<?php
/**
 * Purpose: API endpoint for change password operations; serves the corresponding application data or action.
 */

require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$providedToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$sessionToken = (string)($_SESSION['_csrf'] ?? '');
if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
    http_response_code(419);
    echo json_encode(['error' => 'Your session token is invalid. Refresh the page and try again.']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data.']);
    exit;
}

$current = (string)($payload['current_password'] ?? '');
$new = (string)($payload['new_password'] ?? '');
$confirm = (string)($payload['confirm_password'] ?? '');
$query = db()->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
$query->execute([(int)$_SESSION['user_id']]);
$hash = (string)$query->fetchColumn();

if (!password_verify($current, $hash)) {
    http_response_code(422);
    echo json_encode(['error' => 'Current password is incorrect.']);
    exit;
}
if (strlen($new) < 8 || !hash_equals($new, $confirm)) {
    http_response_code(422);
    echo json_encode(['error' => 'New passwords must match and contain at least 8 characters.']);
    exit;
}

$update = db()->prepare('UPDATE users SET password = ? WHERE user_id = ?');
$update->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
echo json_encode(['success' => true, 'message' => 'Password updated.']);
