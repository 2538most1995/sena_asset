<?php
$active_page='import'; $page_title='นำเข้าข้อมูล';
$page_subtitle='นำเข้าทะเบียนทุกชีตและบัญชีตรวจประจำปี พร้อมตรวจข้อมูลก่อนบันทึก';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/auth.php';
require_login();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
if (isset($_GET['download'])) {
    $type=$_GET['download'];
    if (!in_array($type,['registry','inspection'],true)) {http_response_code(404);exit;}
    $headers=$type==='registry'
      ? ['category','asset_type','equipment_name','equipment_code','brand_description','serial_number','unit_price','acquisition_method','document_number','location','receipt_evidence','change_details','change_document','remarks','acquisition_date','status']
      : ['item_number','item_name','asset_code','asset_id_code','status_usable','status_damaged','status_degraded','status_lost','status_unused','remarks','fiscal_year','category','location','price'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sena_'.$type.'_template.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers,',','"','');fclose($out);exit;
}
include __DIR__.'/includes/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  <?php foreach (['registry'=>['ทะเบียนครุภัณฑ์','ไฟล์ .xls หลายชีต หรือเทมเพลต .csv/.xlsx','fa-cubes','indigo'],
                  'inspection'=>['บัญชีรายการตรวจประจำปี','ไฟล์ .xlsx หรือเทมเพลต .csv/.xlsx','fa-clipboard-check','emerald']] as $type=>$meta): ?>
  <section class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
    <div class="flex items-center gap-3 mb-4">
      <span class="w-10 h-10 rounded-xl bg-<?= $meta[3] ?>-50 text-<?= $meta[3] ?>-700 grid place-items-center"><i class="fa-solid <?= $meta[2] ?>"></i></span>
      <div><h2 class="font-semibold text-slate-900"><?= $meta[0] ?></h2><p class="text-xs text-slate-500"><?= $meta[1] ?></p></div>
    </div>
    <label class="block border-2 border-dashed border-slate-300 rounded-xl p-5 cursor-pointer hover:border-indigo-500 focus-within:border-indigo-500">
      <span class="text-sm font-medium text-slate-700">เลือกไฟล์เพื่อตรวจสอบ</span>
      <input class="block w-full mt-2 text-sm" type="file" id="file-<?= $type ?>" accept=".xls,.xlsx,.csv">
    </label>
    <?php if ($type==='inspection'): ?>
    <label class="block text-sm text-slate-700 mt-4">ปีงบประมาณ
      <input id="fiscal-year" type="number" min="2500" max="2700" value="2568" class="mt-1 block w-36 border border-slate-300 rounded-lg px-3 py-2">
    </label>
    <?php endif; ?>
    <p class="mt-3 text-xs text-slate-500" id="summary-<?= $type ?>">ยังไม่ได้เลือกไฟล์</p>
    <a href="?download=<?= $type ?>" class="inline-flex items-center gap-2 text-sm text-indigo-700 mt-4 hover:underline"><i class="fa-solid fa-download"></i>ดาวน์โหลดเทมเพลต CSV</a>
  </section>
  <?php endforeach; ?>
</div>
<section class="bg-white border border-slate-200 rounded-2xl p-5 mt-5 shadow-sm">
  <div class="flex flex-wrap justify-between items-center gap-3">
    <div><h2 class="font-semibold text-slate-900">ตรวจสอบก่อนนำเข้า</h2><p class="text-sm text-slate-500" id="preview-summary">เลือกไฟล์ทะเบียนหรือบัญชีตรวจด้านบน</p></div>
    <button id="import-button" disabled class="px-5 py-3 rounded-xl bg-indigo-700 text-white font-medium disabled:opacity-40">ยืนยันนำเข้า</button>
  </div>
  <label class="flex items-start gap-2 mt-4 text-sm text-slate-700"><input type="checkbox" id="replace-data" class="mt-1"><span>แทนที่ข้อมูลของชนิดที่เลือก (ทะเบียนทั้งหมด หรือบัญชีตรวจของปีที่เลือก) หลังตรวจสอบและสำรองฐานข้อมูลแล้ว</span></label>
  <p class="text-xs text-slate-500 mt-2">หากไม่เลือก ระบบจะข้ามแถวที่เคยนำเข้าแล้ว การนำเข้าทะเบียนจากสมุดเดิมจะใช้ชื่อชีตเป็นหมวด เก็บประเภทหัวกระดาษแยกไว้ รวมแถวต่อเนื่องและรักษาข้อมูลต้นฉบับ รหัสที่ซ้ำและรายการที่ไม่มีรหัสยังคงอยู่</p>
  <div id="import-result" role="status" class="hidden mt-4 p-3 rounded-xl text-sm"></div>
  <div class="overflow-x-auto mt-5"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left px-3 py-2">แหล่งข้อมูล</th><th class="text-left px-3 py-2">รายการ</th><th class="text-left px-3 py-2">รหัส</th><th class="text-left px-3 py-2">หมวด</th></tr></thead><tbody id="preview-rows"></tbody></table></div>
</section>
<script src="assets/import-parser.js"></script>
<script>
let selected = null;
const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
function notice(message,error=false) {
  const box=document.getElementById('import-result');
  box.textContent=message;box.className='mt-4 p-3 rounded-xl text-sm '+(error?'bg-rose-50 text-rose-800':'bg-emerald-50 text-emerald-800');
}
async function readFile(file,type) {
  if(!file) return;
  if(file.size>50*1024*1024) return notice('ไฟล์เกิน 50 MB',true);
  if(typeof XLSX==='undefined') return notice('ไม่สามารถโหลดตัวอ่าน Excel ได้ กรุณาตรวจสอบอินเทอร์เน็ต',true);
  try {
    const wb=XLSX.read(await file.arrayBuffer(),{type:'array',raw:true});
    const year=Number(document.getElementById('fiscal-year').value)||2568;
    const parsed=SenaImport.parse(wb,type,file.name,year);
    if(!parsed.rows.length) throw new Error('ไม่พบรายการที่นำเข้าได้ ตรวจรูปแบบหัวตารางและชีต');
    selected={type,rows:parsed.rows,sheets:parsed.sheets||[],file:file.name};
    const codes=new Map(),cats=new Set();
    parsed.rows.forEach(r=>{if(r.equipment_code)codes.set(r.equipment_code,(codes.get(r.equipment_code)||0)+1);if(r.category)cats.add(r.category)});
    const dup=[...codes.values()].reduce((n,c)=>n+Math.max(0,c-1),0);
    const summary=`${file.name}: ${parsed.rows.length.toLocaleString()} รายการ จาก ${wb.SheetNames.length} ชีต${type==='registry'?` · ${parsed.sheets?parsed.sheets.length:cats.size} หมวด · รหัสซ้ำ ${dup}`:''}${parsed.warnings.length?` · ข้อควรตรวจ ${parsed.warnings.length}`:''}`;
    document.getElementById('summary-'+type).textContent=summary;
    document.getElementById('preview-summary').textContent=summary;
    const tbody=document.getElementById('preview-rows');tbody.replaceChildren();
    parsed.rows.slice(0,15).forEach(r=>{
      const tr=document.createElement('tr');tr.className='border-b border-slate-100';
      [r.source_sheet+' #'+r.source_row,r.equipment_name||r.item_name,r.equipment_code||r.asset_code||'—',r.category||'—'].forEach(v=>{const td=document.createElement('td');td.className='px-3 py-2';td.textContent=v;tr.appendChild(td)});
      tbody.appendChild(tr);
    });
    document.getElementById('import-button').disabled=false;
    if(parsed.warnings.length) notice(parsed.warnings.slice(0,5).join(' / '),true);
  } catch(e) { selected=null;document.getElementById('import-button').disabled=true;notice(e.message,true); }
}
for(const type of ['registry','inspection']) document.getElementById('file-'+type).addEventListener('change',e=>readFile(e.target.files[0],type));
document.getElementById('import-button').addEventListener('click',async()=>{
  if(!selected) return;
  const replace=document.getElementById('replace-data').checked;
  if(replace&&!confirm('ยืนยันแทนที่ข้อมูลเดิมของ '+(selected.type==='registry'?'ทะเบียนทั้งหมด':'บัญชีตรวจปีที่เลือก')+'?')) return;
  const btn=document.getElementById('import-button');btn.disabled=true;btn.textContent='กำลังบันทึก...';
  try {
    const response=await fetch('actions/import_handler.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:selected.type,rows:selected.rows,sheets:selected.sheets,replace,csrf_token:csrfToken})});
    const result=await response.json();if(!response.ok||!result.success)throw new Error(result.error||'นำเข้าไม่สำเร็จ');
    notice(`บันทึกใหม่ ${result.inserted.toLocaleString()} รายการ · ปรับปรุง ${(result.updated||0).toLocaleString()} รายการ · ข้ามรายการเดิม ${result.skipped.toLocaleString()} · อ่านทั้งหมด ${result.total.toLocaleString()} รายการ`);
  } catch(e) {notice(e.message,true)} finally {btn.disabled=false;btn.textContent='ยืนยันนำเข้า'}
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
