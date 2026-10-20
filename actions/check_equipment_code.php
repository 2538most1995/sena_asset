<?php
require_once __DIR__.'/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(!is_logged_in()){http_response_code(401);echo json_encode(['error'=>'กรุณาเข้าสู่ระบบ']);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/equipment_code_service.php';
try{
    $year=filter_var($_GET['year']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>2500,'max_range'=>2700]]);
    if(!$year || !is_string($_GET['code']??null))throw new InvalidArgumentException('ปีหรือรหัสไม่ถูกต้อง');
    $registryId=max(0,(int)($_GET['registry_id']??0));$inspectionId=max(0,(int)($_GET['inspection_id']??0));
    if($inspectionId){$stmt=$pdo->prepare('SELECT registry_id FROM inspection_items WHERE id=?');$stmt->execute([$inspectionId]);$registryId=(int)$stmt->fetchColumn();}
    $matches=[];
    foreach(sena_code_affected_years($pdo,$year,$registryId) as $affectedYear)$matches=array_merge($matches,sena_code_conflicts($pdo,$_GET['code'],$affectedYear,$registryId,$inspectionId));
    echo json_encode(['duplicate'=>(bool)$matches,'matches'=>array_slice($matches,0,5)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){http_response_code(400);echo json_encode(['error'=>'ตรวจสอบรหัสไม่สำเร็จ กรุณาลองอีกครั้ง'],JSON_UNESCAPED_UNICODE);}
