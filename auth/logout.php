<?php
/**
 * Purpose: Authentication flow for logout; participates in the PHP session and access-control workflow.
 */

require_once __DIR__ . '/../includes/functions.php';
requireRole(['super_admin', 'admin', 'user']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit('Method not allowed.');
}
verify_csrf();
$_SESSION = [];
session_destroy();
redirect_to('auth/login.php');