<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/auth.php';
if (!is_logged_in()) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'กรุณาเข้าสู่ระบบ']); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/import_service.php';
try {
    $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($data['csrf_token'] ?? '')) || empty($_SESSION['csrf_token']))
        throw new RuntimeException('เซสชันหมดอายุ กรุณาโหลดหน้าใหม่');
    if (!isset($data['rows']) || !is_array($data['rows'])) throw new InvalidArgumentException('ไม่พบข้อมูลนำเข้า');
    $result=sena_import($pdo,(string)($data['type']??''),$data['rows'],!empty($data['replace']));
    echo json_encode($result,JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
