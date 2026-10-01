<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user'], true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$query = db()->query('SELECT version, updated_at FROM app_change_state WHERE id = 1');
echo json_encode($query->fetch(PDO::FETCH_ASSOC) ?: ['version' => 1, 'updated_at' => null]);