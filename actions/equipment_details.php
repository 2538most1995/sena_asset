<?php
require_once __DIR__.'/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
if(!is_logged_in()){http_response_code(401);echo json_encode(['error'=>'กรุณาเข้าสู่ระบบ']);exit;}
require_once __DIR__.'/../config/database.php';
$stmt=$pdo->prepare('SELECT * FROM equipment_registry WHERE id=?');
$stmt->execute([(int)($_GET['id']??0)]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row){http_response_code(404);echo json_encode(['error'=>'ไม่พบรายการ']);exit;}
echo json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
