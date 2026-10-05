<?php
// api/import_handler.php - Save imported Excel/CSV records into MySQL
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['rows']) || !is_array($data['rows'])) {
    echo json_encode(['success' => false, 'error' => 'No rows provided']);
    exit;
}

$rows = $data['rows'];
$type = $data['type'] ?? 'inspection'; // 'inspection' or 'registry'

$inserted = 0;
$updated = 0;

try {
    $pdo->beginTransaction();

    if ($type === 'inspection') {
        $stmt = $pdo->prepare("
            INSERT INTO inspection_items 
            (item_number, item_name, asset_code, asset_id_code, status_usable, status_damaged, status_degraded, status_lost, status_unused, remarks, location, category, price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            item_name = VALUES(item_name),
            status_usable = VALUES(status_usable),
            status_damaged = VALUES(status_damaged),
            status_degraded = VALUES(status_degraded),
            remarks = VALUES(remarks)
        ");

        foreach ($rows as $r) {
            $num = (int)($r['item_number'] ?? $r['ลำดับที่'] ?? ($inserted + 1));
            $name = trim($r['item_name'] ?? $r['รายการ'] ?? '');
            $code = trim($r['asset_code'] ?? $r['รหัสครุภัณฑ์'] ?? '');
            $aid = trim($r['asset_id_code'] ?? $r['รหัสสินทรัพย์'] ?? '');
            $u = !empty($r['status_usable']) || !empty($r['ใช้ได้']) ? 1 : 0;
            $d = !empty($r['status_damaged']) || !empty($r['ชำรุด']) ? 1 : 0;
            $deg = !empty($r['status_degraded']) || !empty($r['เสื่อมคุณภาพ']) ? 1 : 0;
            $lost = !empty($r['status_lost']) || !empty($r['สูญไป']) ? 1 : 0;
            $unused = !empty($r['status_unused']) || !empty($r['ไม่ใช้']) ? 1 : 0;
            $remarks = trim($r['remarks'] ?? $r['หมายเหตุ'] ?? '');
            $loc = trim($r['location'] ?? $r['สถานที่'] ?? 'สกร.อำเภอเสนา');
            $cat = trim($r['category'] ?? $r['ประเภท'] ?? 'ครุภัณฑ์สำนักงาน');
            $price = (float)($r['price'] ?? $r['ราคา'] ?? 0);

            if ($name !== '') {
                $stmt->execute([$num, $name, $code, $aid, $u, $d, $deg, $lost, $unused, $remarks, $loc, $cat, $price]);
                $inserted++;
            }
        }
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO equipment_registry 
            (category, equipment_name, equipment_code, brand_description, unit_price, acquisition_method, location, remarks, acquisition_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($rows as $r) {
            $cat = trim($r['category'] ?? $r['ประเภท'] ?? 'ครุภัณฑ์สำนักงาน');
            $name = trim($r['equipment_name'] ?? $r['ชื่อครุภัณฑ์'] ?? $r['รายการ'] ?? '');
            $code = trim($r['equipment_code'] ?? $r['รหัสครุภัณฑ์'] ?? '');
            $brand = trim($r['brand_description'] ?? $r['ยี่ห้อ'] ?? '');
            $price = (float)($r['unit_price'] ?? $r['ราคา'] ?? 0);
            $method = trim($r['acquisition_method'] ?? $r['วิธีการได้มา'] ?? 'งปม.');
            $loc = trim($r['location'] ?? $r['สถานที่'] ?? 'สกร.อำเภอเสนา');
            $remarks = trim($r['remarks'] ?? $r['หมายเหตุ'] ?? '');
            $date = trim($r['acquisition_date'] ?? $r['วันที่ได้มา'] ?? date('Y-m-d'));
            $status = trim($r['status'] ?? 'active');

            if ($name !== '') {
                $stmt->execute([$cat, $name, $code, $brand, $price, $method, $loc, $remarks, $date, $status]);
                $inserted++;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'count' => $inserted]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
