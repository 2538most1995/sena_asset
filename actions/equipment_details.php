<?php
require_once __DIR__.'/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
if(!is_logged_in()){http_response_code(401);echo json_encode(['error'=>'กรุณาเข้าสู่ระบบ']);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/asset_link_service.php';
$year=max(2500,min(2700,(int)($_GET['year']??2568)));
sena_link_inspection_year($pdo,$year);
$source=($_GET['view']??'')==='all'?'equipment_registry':sena_registry_source_sql($year);
$stmt=$pdo->prepare("SELECT * FROM $source WHERE id=?");
$stmt->execute([(int)($_GET['id']??0)]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row){http_response_code(404);echo json_encode(['error'=>'ไม่พบรายการ']);exit;}
echo json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
