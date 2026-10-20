<?php
// This route has no session, write handler, schema bootstrap, or automatic annual linking.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); echo 'ดูข้อมูลได้อย่างเดียว'; exit;
}
require_once __DIR__.'/includes/public_asset_service.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>2500,'max_range'=>2700]]) : null;
$asset = null;
$message = 'ไม่พบรายการครุภัณฑ์';
if (!$id || $year === false) {
    http_response_code(404);
} else {
    define('SENA_PUBLIC_READ_ONLY', true);
    require __DIR__.'/config/database.php';
    try {
        if (!$pdo) throw new RuntimeException('Database unavailable');
        $asset = sena_public_asset($pdo, (int)$id, $year);
        if (!$asset) http_response_code(404);
    } catch (Throwable $e) {
        error_log('Public asset lookup failed: '.$e->getMessage());
        http_response_code(503); $message = 'ไม่สามารถแสดงข้อมูลได้ชั่วคราว กรุณาลองอีกครั้ง';
    }
}
$fields = $asset ? [
    'รหัสครุภัณฑ์'=>$asset['equipment_code'] ?: 'ยังไม่ระบุรหัส',
    'หมวดทะเบียน'=>$asset['category'], 'ประเภทจากหัวกระดาษ'=>$asset['asset_type'],
    'ยี่ห้อ / รายละเอียด'=>$asset['brand_description'],
    'สถานที่/ผู้รับผิดชอบ'=>$asset['location'] ?: 'ยังไม่ระบุสถานที่/ผู้รับผิดชอบ',
    'ราคาต่อหน่วย'=>$asset['unit_price'] !== null ? number_format((float)$asset['unit_price'], 2).' บาท' : null,
    'วันที่จัดซื้อ / ได้มา'=>$asset['acquisition_date'] ?: $asset['acquisition_date_text'],
    'วิธีการได้มา'=>$asset['acquisition_method'], 'หมายเลขเครื่อง / Serial'=>$asset['serial_number'],
    'เลขที่เอกสาร'=>$asset['document_number'],
] : [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=sena_public_escape($asset ? $asset['equipment_name'].' | รายละเอียดครุภัณฑ์' : $message)?> — SENA Asset</title>
<?php include __DIR__.'/includes/favicon.php'; ?>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f5fa;color:#1e293b;font-family:system-ui,"Thonburi","Leelawadee UI",sans-serif;line-height:1.7}
header,main,footer{max-width:1060px;margin:auto;padding:24px}header{display:flex;align-items:center;gap:14px}header img{width:44px;height:44px;border-radius:12px}header strong{display:block}header small{color:#64748b}.notice{color:#4338ca;background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:12px 18px;margin-bottom:24px}
article{background:white;border:1px solid #e2e8f0;border-radius:24px;overflow:hidden;box-shadow:0 8px 32px #1e293b06}.heading{padding:28px 32px;border-bottom:1px solid #e2e8f0}h1{font-size:clamp(22px,4vw,30px);line-height:1.5;margin:0 0 14px;overflow-wrap:anywhere}.badge{display:inline-block;padding:5px 14px;background:#eef2ff;color:#4338ca;border-radius:24px;font-weight:600}.context{color:#64748b;margin:12px 0 0}.content{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.6fr);gap:28px;padding:32px}.photo{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;display:flex;align-items:center;justify-content:center;min-height:200px;overflow:hidden;align-self:start}.photo img{display:block;width:100%;max-height:500px;object-fit:contain}.placeholder{color:#64748b;padding:24px}dl{margin:0;min-width:0}.field{padding:15px 0;border-bottom:1px solid #edf1f7}.field:first-child{padding-top:0}dt{color:#64748b;font-size:14px;margin-bottom:5px}dd{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:16px}footer{text-align:center;color:#64748b;font-size:13px}.error{padding:40px;text-align:center}
@media(max-width:640px){header,main,footer{padding:16px}.heading,.content{padding:22px}.content{grid-template-columns:1fr;gap:24px}.photo img{max-height:340px}.notice{font-size:14px;margin-bottom:16px}}
</style>
</head>
<body>
<header><img src="assets/icons/favicon-32x32.png" alt=""><div><strong>SENA Asset</strong><small>ระบบทะเบียนครุภัณฑ์</small></div></header>
<main>
<div class="notice">ข้อมูลสาธารณะ · ดูรายละเอียดได้อย่างเดียว</div>
<?php if ($asset): ?>
<article>
<div class="heading"><h1><?=sena_public_escape($asset['equipment_name'])?></h1><span class="badge"><?=sena_public_escape($asset['status_label'])?></span>
<p class="context"><?=$asset['fiscal_year'] ? 'ข้อมูลบัญชีตรวจประจำปีงบประมาณ พ.ศ. '.(int)$asset['fiscal_year'] : 'ข้อมูลทะเบียนครุภัณฑ์ปัจจุบัน'?></p></div>
<div class="content"><div class="photo">
<?php if ($asset['image_url']): ?><img src="<?=sena_public_escape($asset['image_url'])?>" alt="รูปครุภัณฑ์">
<?php else: ?><span class="placeholder">ยังไม่มีรูปครุภัณฑ์</span><?php endif; ?>
</div><dl>
<?php foreach ($fields as $label=>$value): ?>
<div class="field"><dt><?=sena_public_escape($label)?></dt><dd><?=sena_public_escape(trim((string)$value) !== '' ? $value : '—')?></dd></div>
<?php endforeach; ?>
</dl></div></article>
<?php else: ?><article class="error"><h1><?=sena_public_escape($message)?></h1><p>กรุณาตรวจสอบลิงก์หรือ QR Code ของรายการ</p></article><?php endif; ?>
</main><footer>สกร.อำเภอเสนา · ทะเบียนครุภัณฑ์</footer>
</body></html>
