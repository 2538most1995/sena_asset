<?php
require_once __DIR__.'/../config/auth.php';require_login();
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/backup_service.php';
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(empty($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],(string)($_POST['csrf_token']??''))){http_response_code(403);exit;}
    $id=sena_save_backup($pdo);
}else{$id=(string)($_GET['id']??'');}
if(!preg_match('/^[a-f0-9]{32}$/',$id)){http_response_code(404);exit;}
$file=sena_backup_directory($pdo).'/'.$id.'.json';
if(!is_file($file)){http_response_code(404);exit;}
header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="sena_asset_backup_'.$id.'.json"');
readfile($file);
