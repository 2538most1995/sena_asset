<?php
$active_page='categories';$page_title='จัดการหมวดครุภัณฑ์';
$page_subtitle='เพิ่มหรือแก้ชื่อหมวดสำหรับทะเบียนและบัญชีตรวจ';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/auth.php';require_login();
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$message='';$error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  try{
    if(!hash_equals($_SESSION['csrf_token'],(string)($_POST['csrf_token']??'')))throw new RuntimeException('เซสชันหมดอายุ');
    $name=trim((string)($_POST['name']??''));
    if($name===''||mb_strlen($name)>255)throw new InvalidArgumentException('กรุณากรอกชื่อหมวดไม่เกิน 255 ตัวอักษร');
    $id=(int)($_POST['id']??0);
    $pdo->beginTransaction();
    if($id){
      $stmt=$pdo->prepare('SELECT category_name FROM asset_categories WHERE id=? FOR UPDATE');$stmt->execute([$id]);$old=$stmt->fetchColumn();
      if($old===false)throw new RuntimeException('ไม่พบหมวด');
      $stmt=$pdo->prepare('SELECT id FROM asset_categories WHERE category_name=? AND id<>? LIMIT 1');$stmt->execute([$name,$id]);
      if($stmt->fetchColumn())throw new RuntimeException('ชื่อหมวดนี้มีแล้ว');
      $stmt=$pdo->prepare('UPDATE asset_categories SET category_name=?,registry_enabled=? WHERE id=?');$stmt->execute([$name,!empty($_POST['registry_enabled'])?1:0,$id]);
      $stmt=$pdo->prepare('UPDATE registry_source_sheets SET category_name=? WHERE category_name=?');$stmt->execute([$name,$old]);
      foreach(['equipment_registry','inspection_items'] as $table){$stmt=$pdo->prepare("UPDATE `$table` SET category=? WHERE category=?");$stmt->execute([$name,$old]);}
      $message='เปลี่ยนชื่อหมวดและปรับรายการที่เกี่ยวข้องแล้ว';
    }else{
      $stmt=$pdo->prepare('SELECT id FROM asset_categories WHERE category_name=? LIMIT 1');$stmt->execute([$name]);
      if($stmt->fetchColumn())throw new RuntimeException('ชื่อหมวดนี้มีแล้ว');
      $stmt=$pdo->prepare('INSERT INTO asset_categories(category_name,registry_enabled) VALUES(?,?)');$stmt->execute([$name,!empty($_POST['registry_enabled'])?1:0]);
      $message='เพิ่มหมวดแล้ว';
    }
    $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$categories=$pdo->query('SELECT c.id,c.category_name,c.registry_enabled,
  (SELECT COUNT(*) FROM equipment_registry e WHERE e.category=c.category_name) registry_count,
  (SELECT COUNT(*) FROM inspection_items i WHERE i.category=c.category_name) inspection_count
  FROM asset_categories c ORDER BY c.category_name')->fetchAll(PDO::FETCH_ASSOC);
include __DIR__.'/includes/header.php';
?>
<?php if($message):?><div class="p-3 bg-emerald-50 text-emerald-800 rounded-xl mb-4"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="p-3 bg-rose-50 text-rose-800 rounded-xl mb-4"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="grid lg:grid-cols-[minmax(240px,330px)_1fr] gap-5">
 <form method="post" class="bg-white border border-slate-200 rounded-2xl p-5 h-fit">
  <h2 class="font-semibold mb-3">เพิ่มหมวด</h2><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
  <label class="block text-sm">ชื่อหมวด<input name="name" required maxlength="255" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2"></label>
  <label class="block mt-3 text-sm"><input type="checkbox" name="registry_enabled" value="1" checked> แสดงในตัวเลือกหมวดทะเบียน</label>
  <button class="mt-4 px-4 py-2 bg-indigo-700 text-white rounded-lg">บันทึกหมวด</button>
 </form>
 <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
  <div class="p-4 border-b border-slate-100 font-semibold">หมวดทั้งหมด <?=count($categories)?> หมวด</div>
  <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">ชื่อหมวด</th><th class="p-3 text-right">ทะเบียน</th><th class="p-3 text-right">บัญชีตรวจ</th><th class="p-3"></th></tr></thead><tbody>
  <?php foreach($categories as $cat):?><tr class="border-t border-slate-100"><td class="p-3"><form id="cat-<?=$cat['id']?>" method="post" class="flex gap-2"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="id" value="<?=$cat['id']?>"><input name="name" required maxlength="255" value="<?=htmlspecialchars($cat['category_name'])?>" class="border border-slate-200 rounded-lg px-2 py-1 min-w-48 w-full"><label class="text-xs whitespace-nowrap"><input type="checkbox" name="registry_enabled" value="1" <?=$cat['registry_enabled']?'checked':''?>> &#3607;&#3632;&#3648;&#3610;&#3637;&#3618;&#3609;</label></form></td><td class="p-3 text-right"><?=number_format($cat['registry_count'])?></td><td class="p-3 text-right"><?=number_format($cat['inspection_count'])?></td><td class="p-3 text-right"><button form="cat-<?=$cat['id']?>" class="text-indigo-700 hover:underline">บันทึก</button></td></tr><?php endforeach;?>
  </tbody></table></div>
 </div>
</div>
<?php include __DIR__.'/includes/footer.php';?>
