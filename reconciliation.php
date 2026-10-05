<?php
$active_page='reconciliation';$page_title='กระทบยอดทะเบียนกับบัญชีตรวจ';
$page_subtitle='ตรวจรายการที่พบเพียงฝั่งเดียว รหัสซ้ำ และรายการไม่มีรหัส';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/pagination.php';
$year=max(2500,min(2700,(int)($_GET['year']??2568)));
$registry=$pdo->query('SELECT id,equipment_name,equipment_code,category,source_sheet,source_row FROM equipment_registry ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$stmt=$pdo->prepare('SELECT id,item_number,item_name,asset_code FROM inspection_items WHERE fiscal_year=? ORDER BY item_number');$stmt->execute([$year]);$inspection=$stmt->fetchAll(PDO::FETCH_ASSOC);
$normalize=static fn($s)=>preg_replace('/\s+/u','',trim((string)$s));
$registryCodes=[];$inspectionCodes=[];
foreach($registry as $r){$code=$normalize($r['equipment_code']);if($code!=='')$registryCodes[$code]=($registryCodes[$code]??0)+1;}
foreach($inspection as $r){$code=$normalize($r['asset_code']);if($code!=='')$inspectionCodes[$code]=true;}
$groups=['registry_only'=>[],'inspection_only'=>[],'duplicate_code'=>[],'without_code'=>[]];
foreach($registry as $r){$code=$normalize($r['equipment_code']);if($code==='')$groups['without_code'][]=$r;elseif(!isset($inspectionCodes[$code]))$groups['registry_only'][]=$r;if($code!==''&&$registryCodes[$code]>1)$groups['duplicate_code'][]=$r;}
foreach($inspection as $r){$code=$normalize($r['asset_code']);if($code!==''&&!isset($registryCodes[$code]))$groups['inspection_only'][]=$r;}
$labels=['registry_only'=>'พบในทะเบียน ไม่พบในบัญชีตรวจ','inspection_only'=>'พบในบัญชีตรวจ ไม่พบในทะเบียน','duplicate_code'=>'รหัสซ้ำในทะเบียน','without_code'=>'ทะเบียนไม่มีรหัส'];
$type=$_GET['type']??'registry_only';if(!isset($groups[$type]))$type='registry_only';
$perPage=sena_page_limit($_GET['limit']??25);
$paging=sena_page_state(count($groups[$type]),$perPage,$_GET['page']??1);$page=$paging['page'];
$shown=array_slice($groups[$type],$paging['offset'],$perPage);
include __DIR__.'/includes/header.php';
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
<?php foreach($labels as $key=>$label):?><a href="?year=<?=$year?>&type=<?=$key?>" class="p-4 rounded-xl border <?=$type===$key?'border-indigo-500 bg-indigo-50':'border-slate-200 bg-white'?>"><div class="text-xs text-slate-600"><?=htmlspecialchars($label)?></div><strong class="text-2xl text-slate-900"><?=number_format(count($groups[$key]))?></strong></a><?php endforeach;?>
</div>
<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
 <div class="p-4 flex flex-wrap gap-3 items-center justify-between"><div><h2 class="font-semibold"><?=htmlspecialchars($labels[$type])?></h2><p class="text-xs text-slate-500">เทียบรหัสโดยละช่องว่าง รายการที่ไม่มีรหัสแสดงแยกเพื่อให้ตรวจด้วยคน</p></div><form method="get"><input type="hidden" name="type" value="<?=htmlspecialchars($type)?>"><label class="text-sm">ปีงบประมาณ <input name="year" type="number" min="2500" max="2700" value="<?=$year?>" class="w-28 border rounded-lg p-2"></label><button class="p-2 text-indigo-700">แสดง</button></form></div>
 <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left p-3">รายการ</th><th class="text-left p-3">รหัส</th><th class="text-left p-3">หมวด/แหล่งข้อมูล</th><th class="text-left p-3">รายละเอียด</th></tr></thead><tbody>
 <?php foreach($shown as $r):?><tr class="border-t border-slate-100"><td class="p-3"><?=htmlspecialchars($r['equipment_name']??$r['item_name'])?></td><td class="p-3 font-mono"><?=htmlspecialchars($r['equipment_code']??$r['asset_code']??'—')?></td><td class="p-3"><?=htmlspecialchars($r['category']??('บัญชีปี '.$year))?><div class="text-xs text-slate-500"><?=htmlspecialchars(($r['source_sheet']??'').(isset($r['source_row'])?' #'.$r['source_row']:''))?></div></td><td class="p-3"><a class="text-indigo-700 hover:underline" href="<?=isset($r['equipment_name'])?'equipment.php?search='.urlencode($r['equipment_code']?:$r['equipment_name']):'inspection.php?year='.$year.'&search='.urlencode($r['asset_code']?:$r['item_name'])?>">เปิดรายการ</a></td></tr><?php endforeach;?>
 </tbody></table></div>
 <?php sena_render_pagination(count($groups[$type]),$perPage,$page,['year'=>$year,'type'=>$type]); ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
