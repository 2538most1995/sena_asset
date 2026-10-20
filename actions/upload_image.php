<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/auth.php';
if(!is_logged_in()){http_response_code(401);echo json_encode(['success'=>false,'error'=>'กรุณาเข้าสู่ระบบ']);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/image_helper.php';
require_once __DIR__.'/../includes/asset_link_service.php';
$result=null;
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Method not allowed');
    $registryId=(int)($_POST['equipment_id']??0);$inspectionId=(int)($_POST['inspection_id']??0);
    if($inspectionId>0){
        $stmt=$pdo->prepare('SELECT fiscal_year FROM inspection_items WHERE id=?');$stmt->execute([$inspectionId]);$year=$stmt->fetchColumn();
        if(!$year)throw new RuntimeException('ไม่พบรายการตรวจ');
        sena_link_inspection_year($pdo,(int)$year);
        $stmt=$pdo->prepare('SELECT registry_id FROM inspection_items WHERE id=?');$stmt->execute([$inspectionId]);$registryId=(int)$stmt->fetchColumn();
    }
    $stmt=$pdo->prepare('SELECT id FROM equipment_registry WHERE id=?');$stmt->execute([$registryId]);
    if(!$stmt->fetchColumn())throw new RuntimeException('ไม่พบครุภัณฑ์ที่ต้องการอัปโหลดรูป');
    if(!isset($_FILES['image']))throw new RuntimeException('กรุณาเลือกไฟล์รูปภาพ');
    $result=processAndSaveEquipmentImage($_FILES['image'],800,75);
    if(!$result['success'])throw new RuntimeException($result['error']);
    sena_set_asset_photo($pdo,$registryId,$result['url']);
    $result['equipment_id']=$registryId;$result['inspection_id']=$inspectionId;
    echo json_encode($result,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    if(!empty($result['success']))@unlink(__DIR__.'/../'.$result['url']);
    http_response_code(422);echo json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
