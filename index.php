<?php
/**
 * Purpose: Application entry point that routes visitors to the appropriate sign-in or landing page.
 */
 require_once __DIR__.'/includes/functions.php';if(!empty($_SESSION['user_id'])){$role=$_SESSION['role']??'user';redirect_to($role==='super_admin'?'admin/review_editor.php':($role==='admin'?'admin/office_upload.php':'user/dashboard.php'));}redirect_to('auth/login.php');