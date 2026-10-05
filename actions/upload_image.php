<?php
// actions/upload_image.php - Upload and auto-compress equipment photo
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/image_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$equipment_id = (int)($_POST['equipment_id'] ?? 0);
$inspection_id = (int)($_POST['inspection_id'] ?? 0);
$equipment_code = trim($_POST['equipment_code'] ?? '');

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์รูปภาพ']);
    exit;
}

// Process and compress image (max 800px, 75% quality -> results in 30-70KB)
$result = processAndSaveEquipmentImage($_FILES['image'], 800, 75);

if (!$result['success']) {
    echo json_encode($result);
    exit;
}

// Update database if inspection_id, equipment_id or equipment_code is provided
$result['inspection_id'] = $inspection_id;
$result['equipment_id'] = $equipment_id;
if ($inspection_id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE inspection_items SET image_url = ? WHERE id = ?");
        $stmt->execute([$result['url'], $inspection_id]);
    } catch (\Exception $e) {}
}

if ($equipment_id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE equipment_registry SET image_url = ? WHERE id = ?");
        $stmt->execute([$result['url'], $equipment_id]);

        // Also sync with inspection_items if code is present
        if ($equipment_code !== '') {
            $stmt2 = $pdo->prepare("UPDATE inspection_items SET image_url = ? WHERE asset_code = ?");
            $stmt2->execute([$result['url'], $equipment_code]);
        }
    } catch (\Exception $e) {
        // Log error
    }
} elseif ($equipment_code !== '') {
    try {
        $stmt = $pdo->prepare("UPDATE equipment_registry SET image_url = ? WHERE equipment_code = ?");
        $stmt->execute([$result['url'], $equipment_code]);
        $stmt2 = $pdo->prepare("UPDATE inspection_items SET image_url = ? WHERE asset_code = ?");
        $stmt2->execute([$result['url'], $equipment_code]);
    } catch (\Exception $e) {
        // Log error
    }
}

echo json_encode($result);
