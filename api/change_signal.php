<?php
/**
 * Purpose: API endpoint for change signal operations; serves the corresponding application data or action.
 */

require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$query = db()->query('SELECT state_data, updated_at FROM app_change_state WHERE id = 1');
$state = $query->fetch(PDO::FETCH_ASSOC) ?: [];
$stateData = json_decode((string)($state['state_data'] ?? ''), true);
echo json_encode([
    'version' => is_array($stateData) ? (int)($stateData['version'] ?? 1) : 1,
    'updated_at' => $state['updated_at'] ?? null
]);