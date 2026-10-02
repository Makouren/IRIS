<?php
require_once __DIR__.'/../config/db.php';
session_start();
const REQUIRE_LOGIN_FOR_PUBLIC = true;
const ALLOW_SUPER_ADMIN_UPLOAD = false;
function base_url(string $path=''): string { $base=rtrim(dirname($_SERVER['SCRIPT_NAME']??''),'/\\'); while(str_ends_with($base,'/auth')||str_ends_with($base,'/admin')||str_ends_with($base,'/user')||str_ends_with($base,'/api')||str_ends_with($base,'/scanner'))$base=rtrim(dirname($base),'/\\'); return ($base==='/'?'':$base).'/'.ltrim($path,'/'); }
function e($v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function redirect_to(string $path): never { header('Location: '.base_url($path)); exit; }
function flash(string $key, ?string $value=null){ if($value!==null){$_SESSION['_flash'][$key]=$value;return;} $v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return $v; }
function csrf_token(): string { if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32));return $_SESSION['_csrf']; }
function csrf_field(): string{return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">';}
function verify_csrf(): void {if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['_csrf']??'',$_POST['_csrf']??'')){http_response_code(419);exit('Invalid CSRF token.');}}
function requireRole(array $roles, bool $api = false): void {
	$respond = static function (int $status, string $message) use ($api): never {
		if ($api) {
			http_response_code($status);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['error' => $message]);
		} else {
			if ($status === 403) {
				$role = (string)($_SESSION['role'] ?? 'user');
				redirect_to($role === 'super_admin' ? 'admin/review_editor.php' : ($role === 'admin' ? 'admin/office_upload.php' : 'user/dashboard.php'));
			}
			redirect_to('auth/login.php');
		}
		exit;
	};

	$userId = (int)($_SESSION['user_id'] ?? 0);
	$role = (string)($_SESSION['role'] ?? '');
	if ($userId < 1 || $role === '') {
		if (!REQUIRE_LOGIN_FOR_PUBLIC && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && in_array('user', $roles, true)) return;
		$respond($api ? 401 : 302, 'Authentication required.');
	}

	try {
		$query = db()->prepare('SELECT roles.role_name AS role, users.is_active
			FROM users
			INNER JOIN roles ON roles.role_id = users.role_id
			WHERE users.user_id = ? LIMIT 1');
		$query->execute([$userId]);
		$account = $query->fetch(PDO::FETCH_ASSOC);
	} catch (Throwable $exception) {
		$account = null;
	}
	if (!$account || (int)$account['is_active'] !== 1) {
		$_SESSION = [];
		if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
		$respond($api ? 401 : 302, 'Authentication required.');
	}
	if (!hash_equals((string)$account['role'], $role)) {
		$_SESSION = [];
		if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
		$respond($api ? 401 : 302, 'Authentication required.');
	}
	if (!in_array($role, $roles, true)) $respond($api ? 403 : 403, 'Forbidden.');
}
function require_auth():void{requireRole(['super_admin', 'admin', 'user']);}
function require_admin():void{requireRole(['super_admin']);}
function old(string $key):string{return e($_SESSION['_old'][$key]??'');}
function set_old(array $v):void{$_SESSION['_old']=$v;}
function clear_old():void{unset($_SESSION['_old']);}
function parse_rank_to_value($raw):?int{$raw=trim((string)$raw);if($raw==='')return null;return preg_match('/\d+/',str_replace(['–','—'],'-',$raw),$m)?(int)$m[0]:null;}
function flash_redirect(string $path,string $key,string $msg):never{flash($key,$msg);redirect_to($path);}
