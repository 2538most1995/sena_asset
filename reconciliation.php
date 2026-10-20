<?php
$active_page='reconciliation';$page_title='เช็ครหัสครุภัณฑ์';
$page_subtitle='ตรวจรหัสซ้ำและรายการไม่มีรหัส เฉพาะทะเบียนที่เชื่อมกับบัญชีตรวจของปีที่เลือก';
require_once __DIR__.'/config/auth.php';require_login();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/pagination.php';
require_once __DIR__.'/includes/equipment_code_service.php';
$year=max(2500,min(2700,(int)($_GET['year']??sena_default_inspection_year($pdo))));
sena_link_inspection_year($pdo,$year);
$groups=sena_code_check_groups($pdo,$year);
$labels=['all'=>'ทะเบียนที่ตรงกับบัญชีตรวจ','duplicate_code'=>'รายการที่มีรหัสซ้ำ','without_code'=>'รายการยังไม่มีรหัส'];
$type=is_string($_GET['type']??null)?$_GET['type']:'duplicate_code';
if(!isset($groups[$type]))$type='duplicate_code';
$perPage=sena_page_limit($_GET['limit']??25);
$paging=sena_page_state(count($groups[$type]),$perPage,$_GET['page']??1);$page=$paging['page'];
$shown=array_slice($groups[$type],$paging['offset'],$perPage);
$years=$pdo->query('SELECT DISTINCT fiscal_year FROM inspection_items ORDER BY fiscal_year DESC')->fetchAll(PDO::FETCH_COLUMN);
$escape=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
include __DIR__.'/includes/header.php';
?>
<form method="get" class="sena-filter-panel mb-5">
 <div class="sena-filter-grid">
  <label class="sena-filter-field"><span class="sena-filter-label">ปีงบประมาณที่ตรวจ</span><input name="year" type="number" min="2500" max="2700" value="<?=$year?>" list="code-check-years" class="sena-filter-control" required></label>
  <label class="sena-filter-field"><span class="sena-filter-label">รายการที่ต้องการตรวจ</span><select name="type" class="sena-filter-control"><?php foreach($labels as $key=>$label):?><option value="<?=$key?>" <?=$type===$key?'selected':''?>><?=$escape($label)?></option><?php endforeach;?></select></label>
 </div>
 <datalist id="code-check-years"><?php foreach($years as $y):?><option value="<?=(int)$y?>"></option><?php endforeach;?></datalist>
 <div class="sena-filter-actions mt-4"><button type="submit" class="bg-indigo-600 text-white rounded-xl px-5 py-2">ตรวจรหัส</button></div>
</form>
<p class="text-sm text-slate-600 mb-4">ข้อมูลบัญชีตรวจปี <?=$year?> · แสดงเฉพาะครุภัณฑ์ที่เชื่อมทะเบียนด้วย ID เดียวกัน รหัสซ้ำนับเป็นจำนวนรายการ โดยละช่องว่างในการเทียบรหัส</p>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
<?php foreach($labels as $key=>$label):?><a href="?year=<?=$year?>&type=<?=$key?>" class="p-4 rounded-xl border <?=$type===$key?'border-indigo-500 bg-indigo-50':'border-slate-200 bg-white'?>"><div class="text-sm text-slate-600"><?=$escape($label)?></div><strong class="text-2xl text-slate-900"><?=number_format(count($groups[$key]))?></strong></a><?php endforeach;?>
</div>
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
 <div class="p-4"><h2 class="font-semibold"><?=$escape($labels[$type])?></h2><p class="text-xs text-slate-500">ตรวจสอบแต่ละรายการก่อนแก้รหัส รายการเดิมและรูปภาพยังคงแยกตาม ID ครุภัณฑ์</p></div>
 <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left p-3">ลำดับตรวจ</th><th class="text-left p-3">รายการ</th><th class="text-left p-3">รหัสครุภัณฑ์</th><th class="text-left p-3">หมวดทะเบียน</th><th class="text-left p-3">สถานที่/ผู้รับผิดชอบ</th><th class="text-left p-3">ตรวจรายละเอียด</th></tr></thead><tbody>
 <?php if(!$shown):?><tr><td colspan="6" class="p-8 text-center text-slate-500">ไม่พบรายการในเงื่อนไขนี้สำหรับปี <?=$year?></td></tr><?php endif;?>
 <?php foreach($shown as $r):?><tr class="border-t border-slate-100"><td class="p-3"><?=(int)$r['item_number']?></td><td class="p-3"><?=$escape($r['equipment_name'])?></td><td class="p-3 font-mono"><?=$escape($r['equipment_code']?:'—')?></td><td class="p-3"><?=$escape($r['category']?:'ยังไม่ระบุหมวด')?></td><td class="p-3"><?=$escape($r['location']?:'ยังไม่ระบุสถานที่/ผู้รับผิดชอบ')?></td><td class="p-3"><a class="text-indigo-700 underline whitespace-nowrap" href="equipment.php?year=<?=$year?>&registry_id=<?=(int)$r['id']?>">เปิดทะเบียน</a><br><a class="text-indigo-700 underline whitespace-nowrap" href="inspection.php?year=<?=$year?>&registry_id=<?=(int)$r['id']?>">เปิดบัญชีตรวจ</a></td></tr><?php endforeach;?>
 </tbody></table></div>
 <?php sena_render_pagination(count($groups[$type]),$perPage,$page,['year'=>$year,'type'=>$type]); ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
