<?php
// api/update_inspection.php - Update inspection status via AJAX
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$u = current_user();
$default_inspector = $u ? $u['fullname'] : 'เจ้าหน้าที่พัสดุ';

$id = (int)$data['id'];
$status = $data['status'] ?? 'usable'; // 'usable', 'damaged', 'degraded', 'lost', 'unused'
$remarks = trim($data['remarks'] ?? '');
$inspector = !empty($data['inspector']) ? trim($data['inspector']) : $default_inspector;

$usable = ($status === 'usable') ? 1 : 0;
$damaged = ($status === 'damaged') ? 1 : 0;
$degraded = ($status === 'degraded') ? 1 : 0;
$lost = ($status === 'lost') ? 1 : 0;
$unused = ($status === 'unused') ? 1 : 0;

try {
    $item_stmt = $pdo->prepare("SELECT * FROM inspection_items WHERE id = ?");
    $item_stmt->execute([$id]);
    $current = $item_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบรายการที่ต้องการบันทึก']);
        exit;
    }

    $fiscal_year = (int)$current['fiscal_year'];

    if (isset($data['type']) && $data['type'] === 'update_remarks') {
        $remarks = trim($data['remarks'] ?? '');
        $stmt = $pdo->prepare("UPDATE inspection_items SET remarks = ? WHERE id = ?");
        $stmt->execute([$remarks, $id]);
        $status = null;
    } else {
        $status = $data['status'] ?? 'usable';
        $remarks = isset($data['remarks']) ? trim($data['remarks']) : ($current['remarks'] ?? '');
        
        $usable = ($status === 'usable') ? 1 : 0;
        $damaged = ($status === 'damaged') ? 1 : 0;
        $degraded = ($status === 'degraded') ? 1 : 0;
        $lost = ($status === 'lost') ? 1 : 0;
        $unused = ($status === 'unused') ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE inspection_items 
            SET status_usable = ?, status_damaged = ?, status_degraded = ?, 
                status_lost = ?, status_unused = ?, remarks = ?, inspector = ?,
                is_overlap = 0
            WHERE id = ?
        ");
        $stmt->execute([$usable, $damaged, $degraded, $lost, $unused, $remarks, $inspector, $id]);
    }

    // Return recalculated stats for real-time mobile UI update
    $stat_total = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items WHERE fiscal_year = $fiscal_year")->fetchColumn();
    $stat_usable = (int)$pdo->query("SELECT COALESCE(SUM(status_usable),0) FROM inspection_items WHERE fiscal_year = $fiscal_year")->fetchColumn();
    $stat_damaged = (int)$pdo->query("SELECT COALESCE(SUM(status_damaged),0) FROM inspection_items WHERE fiscal_year = $fiscal_year")->fetchColumn();
    $stat_degraded = (int)$pdo->query("SELECT COALESCE(SUM(status_degraded),0) FROM inspection_items WHERE fiscal_year = $fiscal_year")->fetchColumn();
    $stat_lost = (int)$pdo->query("SELECT COALESCE(SUM(status_lost),0) FROM inspection_items WHERE fiscal_year = $fiscal_year")->fetchColumn();
    $stat_unused = (int)$pdo->query("SELECT COALESCE(SUM(status_unused),0) FROM inspection_items WHERE fiscal_year = $fiscal_year")->fetchColumn();
    $stat_overlap = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items WHERE fiscal_year = $fiscal_year AND is_overlap = 1")->fetchColumn();

    $stat_checked = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items WHERE fiscal_year = $fiscal_year AND (status_usable = 1 OR status_damaged = 1 OR status_degraded = 1 OR status_lost = 1 OR status_unused = 1)")->fetchColumn();
    $stat_pending = max(0, $stat_total - $stat_checked);

    echo json_encode([
        'success' => true,
        'id' => $id,
        'status' => $status,
        'remarks' => $remarks,
        'stats' => [
            'total' => $stat_total,
            'checked' => $stat_checked,
            'pending' => $stat_pending,
            'usable' => $stat_usable,
            'damaged' => $stat_damaged,
            'degraded' => $stat_degraded,
            'lost' => $stat_lost,
            'unused' => $stat_unused,
            'overlap' => $stat_overlap,
            'pct_checked' => $stat_total > 0 ? round(($stat_checked / $stat_total) * 100, 1) : 0
        ]
    ]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
