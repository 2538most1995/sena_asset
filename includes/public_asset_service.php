<?php
/** Public projection: no import metadata, internal notes, accounts, or mutation helpers. */
function sena_public_asset(PDO $pdo, int $id, ?int $year): ?array {
    $stmt = $pdo->prepare('SELECT id, equipment_name, equipment_code, category, asset_type,
        brand_description, serial_number, unit_price, acquisition_method, document_number,
        location, acquisition_date, acquisition_date_text, status, image_url
        FROM equipment_registry WHERE id = ?');
    $stmt->execute([$id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) return null;
    $statusLabels = ['active'=>'ใช้ได้','damaged'=>'ชำรุด','degraded'=>'เสื่อมคุณภาพ',
        'disposed'=>'จำหน่ายแล้ว','unused'=>'ไม่ใช้','unverified'=>'ยังไม่ยืนยันสถานะ'];
    $asset['status_label'] = $statusLabels[$asset['status']] ?? 'ยังไม่ยืนยันสถานะ';
    // The year printed in a QR is the observation context, not a reason to change the master.
    $sql = 'SELECT fiscal_year, item_name, asset_code, location,
        status_usable, status_damaged, status_degraded, status_lost, status_unused
        FROM inspection_items WHERE registry_id = ?';
    $args = [$id];
    if ($year !== null) { $sql .= ' AND fiscal_year = ?'; $args[] = $year; }
    $stmt = $pdo->prepare($sql . ' ORDER BY fiscal_year DESC, id DESC LIMIT 1');
    $stmt->execute($args);
    $annual = $stmt->fetch(PDO::FETCH_ASSOC);
    $asset['fiscal_year'] = $annual ? (int)$annual['fiscal_year'] : null;
    if ($annual) {
        $asset['equipment_name'] = $annual['item_name'];
        $asset['equipment_code'] = $annual['asset_code'];
        if (trim((string)$annual['location']) !== '') $asset['location'] = $annual['location'];
        $labels = [];
        foreach (['usable'=>'ใช้ได้','damaged'=>'ชำรุด','degraded'=>'เสื่อมคุณภาพ','lost'=>'สูญไป','unused'=>'ไม่ใช้'] as $flag=>$label) {
            if (!empty($annual['status_'.$flag])) $labels[] = $label;
        }
        $asset['status_label'] = $labels ? implode(' / ', $labels) : 'รอตรวจนับ';
    }
    // Only local raster uploads may be embedded in the public page.
    $asset['image_url'] = sena_public_asset_image($asset['image_url']);
    return $asset;
}
function sena_public_asset_image($path): ?string {
    return is_string($path) && preg_match('~\Auploads/equipment/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp|gif)\z~i', $path) ? $path : null;
}
function sena_public_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
