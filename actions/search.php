<?php
// api/search.php - Fast JSON search endpoint for global search
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, item_number, asset_code, item_name, location, category, price,
               status_usable, status_damaged, status_degraded
        FROM inspection_items 
        WHERE item_name LIKE ? OR asset_code LIKE ? OR location LIKE ? OR category LIKE ?
        ORDER BY item_number ASC
        LIMIT 15
    ");
    $param = "%$q%";
    $stmt->execute([$param, $param, $param, $param]);
    $items = $stmt->fetchAll();

    echo json_encode(['results' => $items]);
} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'results' => []]);
}
