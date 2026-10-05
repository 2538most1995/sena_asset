<?php
// reports.php - รายงาน
$active_page = 'reports';
$page_title = 'รายงาน';
$page_subtitle = 'สรุปรายงานครุภัณฑ์และผลการตรวจประจำปี';

require_once __DIR__ . '/config/database.php';

$config_file = __DIR__ . '/config/settings.json';
$default_settings = [
    'org_name' => 'สำนักงานส่งเสริมการเรียนรู้ระดับอำเภอเสนา',
    'department' => 'กรมส่งเสริมการเรียนรู้ กระทรวงศึกษาธิการ',
    'province' => 'พระนครศรีอยุธยา',
    'fiscal_year' => '2568',
    'director_name' => 'นายวุฒิพล กำมา',
    'director_position' => 'ผู้อำนวยการ สกร.ระดับอำเภอเสนา',
    'officer_name' => 'นางสาวกมลวรรณ ใจดี',
    'officer_position' => 'เจ้าหน้าที่พัสดุ',
    'logo_url' => 'assets/img/dole_logo.png'
];
$settings = file_exists($config_file) ? (json_decode(file_get_contents($config_file), true) ?: $default_settings) : $default_settings;
$logo_url = !empty($settings['logo_url']) ? $settings['logo_url'] : 'assets/img/dole_logo.png';

require_once __DIR__.'/config/auth.php';require_login();
require_once __DIR__.'/includes/report_service.php';
require_once __DIR__.'/includes/pagination.php';
$query=sena_report_query($_GET);$source=$query['source'];$year=$query['year'];
$report_type=trim($_GET['type']??'');$filter_status=trim($_GET['status']??'');$filter_loc=trim($_GET['loc']??'');$filter_cat=trim($_GET['cat']??'');
$count=$pdo->prepare($query['count_sql']);$count->execute($query['params']);$report_count=(int)$count->fetchColumn();
$registry_count=(int)$pdo->query('SELECT COUNT(*) FROM equipment_registry')->fetchColumn();
$stats_stmt=$pdo->prepare("SELECT COUNT(*) total,COALESCE(SUM(status_usable),0) usable,COALESCE(SUM(status_damaged),0) damaged,COALESCE(SUM(status_degraded),0) degraded,COALESCE(SUM(status_lost),0) lost,COALESCE(SUM(status_unused),0) unused FROM inspection_items WHERE fiscal_year=?");
$stats_stmt->execute([$year]);$stats=$stats_stmt->fetch(PDO::FETCH_ASSOC);
$per_page=sena_page_limit($_GET['limit']??25);$paging=sena_page_state($report_count,$per_page,$_GET['page']??1);$page=$paging['page'];$offset=$paging['offset'];
$is_print=isset($_GET['print']);$is_export=($_GET['export']??'')==='excel';
if($is_print||$is_export)$offset=0;
$sql=$query['sql'];if(!$is_print&&!$is_export)$sql.=" LIMIT $per_page OFFSET $offset";
$stmt=$pdo->prepare($sql);$stmt->execute($query['params']);$preview_items=$stmt->fetchAll(PDO::FETCH_ASSOC);
if($source==='inspection'){
 $location_stmt=$pdo->prepare('SELECT DISTINCT effective_location FROM '.sena_inspection_source_sql().' WHERE fiscal_year=? AND effective_location IS NOT NULL ORDER BY effective_location');
 $location_stmt->execute([$year]);$locations=$location_stmt->fetchAll(PDO::FETCH_COLUMN);
}else $locations=$pdo->query("SELECT DISTINCT location FROM equipment_registry WHERE location IS NOT NULL AND location<>'' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
$category_sql=$source==='registry'
    ? "SELECT category_name FROM asset_categories WHERE registry_enabled=1 UNION SELECT category FROM equipment_registry WHERE category IS NOT NULL AND category<>'' ORDER BY 1"
    : "SELECT DISTINCT category FROM inspection_items WHERE category IS NOT NULL AND category<>'' ORDER BY category";
$category_options=$pdo->query($category_sql)->fetchAll(PDO::FETCH_COLUMN);
$year_options=$pdo->query('SELECT DISTINCT fiscal_year FROM inspection_items ORDER BY fiscal_year DESC')->fetchAll(PDO::FETCH_COLUMN);if(!in_array($year,$year_options))$year_options[]=$year;
$export_url='?'.http_build_query(array_merge($_GET,['export'=>'excel']));
$print_url='?'.http_build_query(array_merge(array_diff_key($_GET,['export'=>true]),['print'=>1]));
if($is_export){
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="sena_'.$source.'_'.date('Ymd_His').'.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
 fputcsv($out,['ลำดับ','รหัสครุภัณฑ์','รายการ','หมวดทะเบียน','ประเภทหัวกระดาษ','สถานที่/ผู้รับผิดชอบ','สถานะ','ราคา','ปีงบประมาณ','ชีตต้นฉบับ','แถวต้นฉบับ','หมายเหตุ','แหล่งข้อมูลสถานที่/ผู้รับผิดชอบ'],',','"','');
 foreach($preview_items as $i=>$r)fputcsv($out,array_map('sena_csv_cell',[$source==='registry'?$i+1:$r['item_number'],$r['asset_code'],$r['item_name'],$r['category'],$r['asset_type'],$r['location'],sena_report_status($r,$source),$r['price'],$r['fiscal_year'],$r['source_sheet'],$r['source_row'],$r['remarks'],$source==='inspection'?sena_inspection_location_origin($r):'ทะเบียนหลัก']),',','"','');
 fclose($out);exit;
}
include __DIR__ . '/includes/header.php';
?>

<!-- 1. TOP FILTERS & ACTION BUTTONS -->
<div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs no-print">
    <form method="GET" action="reports.php" class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
        
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-xs">แหล่งข้อมูล <select name="source" onchange="this.form.submit()" class="border rounded-xl px-3 py-2">
             <option value="registry" <?=$source==='registry'?'selected':''?>>ทะเบียนหลักทั้งหมด</option><option value="inspection" <?=$source==='inspection'?'selected':''?>>บัญชีตรวจประจำปี</option>
            </select></label>
            <!-- Fiscal Year -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">ปีงบประมาณ</span>
                <select name="year" <?=$source==='registry'?'disabled':''?> onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 font-medium focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($year_options as $y): ?>
                        <option value="<?= $y ?>" <?= $year === (int)$y ? 'selected' : '' ?>>พ.ศ. <?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Report Type -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">ประเภทรายงาน</span>
                <select name="type" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">ทั้งหมด</option>
                    <option value="all" <?= $report_type === 'all' ? 'selected' : '' ?>>รายการทั้งหมดจากแหล่งที่เลือก</option>
                    <option value="damaged" <?= $report_type === 'damaged' ? 'selected' : '' ?>>รายงานครุภัณฑ์ชำรุด</option>
                    <option value="degraded" <?= $report_type === 'degraded' ? 'selected' : '' ?>>รายงานครุภัณฑ์เสื่อมคุณภาพ</option>
                    <option value="dispose" <?= $report_type === 'dispose' ? 'selected' : '' ?>>รายงานสูญไป/ไม่ใช้</option>
                </select>
            </div>

            <!-- Status -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">สถานะ</span>
                <select name="status" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">ทั้งหมด</option>
                    <option value="usable" <?= $filter_status === 'usable' ? 'selected' : '' ?>>ใช้ได้</option>
                    <option value="damaged" <?= $filter_status === 'damaged' ? 'selected' : '' ?>>ชำรุด</option>
                    <option value="degraded" <?= $filter_status === 'degraded' ? 'selected' : '' ?>>เสื่อมคุณภาพ</option>
                    <option value="unverified" <?=$filter_status==='unverified'?'selected':''?>><?=$source==='registry'?'ยังไม่ยืนยัน':'รอตรวจนับ'?></option>
                    <?php if($source==='inspection'):?><option value="lost" <?=$filter_status==='lost'?'selected':''?>>สูญไป</option><?php else:?><option value="disposed" <?=$filter_status==='disposed'?'selected':''?>>จำหน่ายแล้ว</option><?php endif;?>
                    <option value="unused" <?=$filter_status==='unused'?'selected':''?>>ไม่ใช้</option>
                </select>
            </div>

            <label class="text-xs">หมวด <select name="cat" class="border rounded-xl px-3 py-2 max-w-48"><option value="">ทั้งหมด</option><?php foreach($category_options as $cat):?><option value="<?=htmlspecialchars($cat)?>" <?=$filter_cat===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option><?php endforeach;?></select></label>
            <!-- Location -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">สถานที่/ผู้รับผิดชอบ</span>
                <select name="loc" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 max-w-[170px]">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= htmlspecialchars($l) ?>" <?= $filter_loc === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-xs transition-all">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                <span>แสดงรายงาน</span>
            </button>
            <button type="button" onclick="printReport()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-rose-600 text-xs font-medium transition-all">
                <i class="fa-regular fa-file-pdf"></i>
                <span>ส่งออก PDF</span>
            </button>
            <a href="<?=htmlspecialchars($export_url)?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-emerald-600 text-xs font-medium transition-all">
                <i class="fa-regular fa-file-excel"></i>
                <span>ส่งออก Excel</span>
            </a>
        </div>
    </form>
</div>

<div class="grid sm:grid-cols-3 gap-4 no-print">
 <div class="bg-white rounded-xl border p-4"><p>ทะเบียนหลักทั้งหมด</p><strong class="text-3xl"><?=number_format($registry_count)?></strong></div>
 <div class="bg-white rounded-xl border p-4"><p>บัญชีตรวจปี <?=$year?></p><strong class="text-3xl"><?=number_format((int)$stats['total'])?></strong></div>
 <div class="bg-white rounded-xl border p-4"><p>รายการตามเงื่อนไขรายงาน</p><strong class="text-3xl"><?=number_format($report_count)?></strong></div>
</div>
<!-- 4. BOTTOM SECTION: ตัวอย่างรายงาน (REPORT PREVIEW FOR PRINT/PDF) matching Screenshot 1 -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
    
    <!-- Preview Toolbar (Hidden when printing) -->
    <div class="p-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3 no-print">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="fa-regular fa-file-lines text-sm"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-sm">ตัวอย่างรายงาน</h3>
                <p class="text-[11px] text-slate-400">แสดงตัวอย่างรายงานก่อนพิมพ์หรือส่งออกไฟล์</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="printReport()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-medium transition-all">
                <i class="fa-solid fa-print text-xs"></i>
                <span>พิมพ์รายงาน</span>
            </button>
        </div>
    </div>

    <!-- Official Document Sheet Container -->
    <div class="p-6 bg-slate-100/50 flex justify-center">
        <div id="printable-report" class="w-full max-w-4xl bg-white rounded-xl border border-slate-200 shadow-md p-8 text-slate-800 space-y-6">
            
            <!-- Ministry / Garuda Emblem Header -->
            <div class="text-center space-y-1">
                <div class="w-20 h-20 mx-auto mb-2 flex items-center justify-center">
                    <img src="<?= htmlspecialchars($logo_url) ?>" 
                         alt="ตราสัญลักษณ์ / ตราครุฑ" 
                         class="h-20 max-w-full object-contain drop-shadow-xs"
                         onerror="this.src='assets/img/dole_logo.png'">
                </div>
                <h2 class="text-lg font-bold text-slate-900 leading-tight"><?= htmlspecialchars($settings['org_name'] ?? 'สำนักงานส่งเสริมการเรียนรู้ระดับอำเภอเสนา') ?></h2>
                <h3 class="text-base font-semibold text-slate-800">
                    <?= $source==='registry'?'รายงานทะเบียนครุภัณฑ์หลัก':'รายงานบัญชีตรวจประจำปี' ?> (<?=number_format($report_count)?> รายการ)
                </h3>
                <?php if($source==='inspection'):?><p class="text-xs text-slate-600">ปีงบประมาณ <?=$year?></p><p class="text-xs text-slate-500">สถานที่ว่างอ้างอิงทะเบียนปัจจุบันเมื่อรหัสตรงและมีสถานที่เดียว ไม่ใช่หลักฐานยืนยันสถานที่ย้อนหลัง</p><?php endif;?>
                <p class="text-[11px] text-slate-500 font-light">ข้อมูล ณ วันที่ <?= date('j') ?> <?= ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][date('n')-1] ?> <?= (date('Y') + 543) ?></p>
            </div>

            <!-- Official Report Table -->
            <div class="overflow-x-auto border border-slate-300 rounded-lg">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-100 border-b border-slate-300 text-slate-700 text-[11px] font-semibold">
                        <tr>
                            <th class="py-2.5 px-2.5 text-center w-12 border-r border-slate-300">ลำดับที่</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">รหัสครุภัณฑ์</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">รายการครุภัณฑ์</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">หมวด</th>
                            <th class="py-2.5 px-3 border-r border-slate-300">ประเภทหัวกระดาษ</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">สถานที่/ผู้รับผิดชอบ</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">สถานะ</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">ปีงบฯ</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">มูลค่า (บาท)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-[11px]">
                        <?php if (empty($preview_items)): ?>
                            <tr>
                                <td colspan="9" class="py-6 text-center text-slate-400">ไม่พบรายการครุภัณฑ์ตามเงื่อนไขที่เลือก</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($preview_items as $idx => $r): 
                                $st=sena_report_status($r,$source);$badge='bg-slate-100 text-slate-700';
                            ?>
                            <tr>
                                <td class="py-2 px-2.5 text-center border-r border-slate-200"><?= $source==='registry'?$offset+$idx+1:$r['item_number'] ?></td>
                                <td class="py-2 px-3 font-mono border-r border-slate-200"><?= htmlspecialchars($r['asset_code'] ?: '-') ?></td>
                                <td class="py-2 px-3 font-medium border-r border-slate-200"><?= htmlspecialchars($r['item_name']) ?></td>
                                <td class="py-2 px-3 border-r border-slate-200"><?= htmlspecialchars($r['category'] ?: '—') ?></td>
                                <td class="py-2 px-3 border-r border-slate-200"><?= htmlspecialchars($r['asset_type'] ?: '—') ?></td>
                                <td class="py-2 px-3 border-r border-slate-200"><?= htmlspecialchars($r['location'] ?: 'ยังไม่ระบุสถานที่') ?><?php if($source==='inspection'):?><small class="block text-[10px] text-slate-500"><?=htmlspecialchars(sena_inspection_location_origin($r))?></small><?php endif;?></td>
                                <td class="py-2 px-3 text-center border-r border-slate-200"><span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $badge ?>"><?= $st ?></span></td>
                                <td class="py-2 px-3 text-center border-r border-slate-200"><?= $r['fiscal_year']??'—' ?></td>
                                <td class="py-2 px-3 text-right font-medium"><?= $r['price']===null?'—':number_format((float)$r['price'],2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if(!$is_print):?><div class="no-print"><?php sena_render_pagination($report_count,$per_page,$page,$_GET); ?></div><?php endif;?>
            <!-- Signatures Section -->
            <div class="grid grid-cols-2 gap-8 pt-8 text-center text-xs">
                <div class="space-y-1">
                    <p>ลงชื่อ..........................................................ผู้จัดทำ</p>
                    <p class="font-medium text-slate-800">(<?= htmlspecialchars($settings['officer_name'] ?? 'นางสาวกมลวรรณ ใจดี') ?>)</p>
                    <p class="text-slate-500 text-[11px]"><?= htmlspecialchars($settings['officer_position'] ?? 'เจ้าหน้าที่พัสดุ') ?></p>
                </div>
                <div class="space-y-1">
                    <p>ลงชื่อ..........................................................ประธานกรรมการ</p>
                    <p class="font-medium text-slate-800">(<?= htmlspecialchars($settings['director_name'] ?? 'นายวุฒิพล กำมา') ?>)</p>
                    <p class="text-slate-500 text-[11px]"><?= htmlspecialchars($settings['director_position'] ?? 'ผู้อำนวยการ สกร.ระดับอำเภอเสนา') ?></p>
                </div>
            </div>

        </div>
    </div>

</div>

<style>@media print{thead{display:table-header-group}tr{break-inside:avoid}#printable-report{max-width:none;padding:0;border:0;box-shadow:none}.overflow-x-auto{overflow:visible!important}}@page{size:A4 landscape;margin:12mm}</style>
<script>function printReport(){window.open(<?=json_encode($print_url)?>,'_blank');}</script>
<?php if($is_print):?><script>window.addEventListener('load',()=>window.print());</script><?php endif;?>
<?php include __DIR__.'/includes/footer.php'; ?>
