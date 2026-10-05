const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = {window: {}, XLSX: {utils: {sheet_to_json: sheet => sheet}, SSF: {
  parse_date_code: () => ({y: 2013, m: 11, d: 11})
}}};
vm.createContext(context);
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/import-parser.js'), 'utf8'), context);
const parse = context.window.SenaImport.parse;
const header = ['วัน เดือน ปี','เลขที่หรือรหัส','ยี่ห้อ ชนิด ขนาด','หมายเลข','ราคาต่อหน่วย','วิธีการได้มา','เลขที่','ใช้ประจำที่','หลักฐาน','รายการ','เลขที่','หมายเหตุ'];
const form = rows => [['ทะเบียนครุภัณฑ์'],[],['ประเภท...สำนักงาน...ชื่อหรือชนิดครุภัณฑ์...โต๊ะ...'],[],header,['','','และลักษณะ','','(บาท)'],...rows];
const wb = sheets => ({SheetNames: Object.keys(sheets), Sheets: sheets});
let result = parse(wb({'โต๊ะทำงาน': form([
  ['11/11/56','7110-001/1','รุ่น A','',1000,'จัดซื้อ','','ห้องหนึ่ง'],
  ['','','รายละเอียดต่อ','','','','','','','','','ชำรุด'],
  ['12 มิ.ย. 2568','7110-001/2','”','','”','”','','”'],
  [41689],
]),'หมวดว่าง':form([])}), 'registry', 'test.xls');
assert.equal(result.rows.length, 2);
assert.equal(result.sheets.length, 2);
assert.equal(result.sheets[1].source_sheet, 'หมวดว่าง');
assert.equal(result.sheets[1].item_count, 0);
assert.equal(result.rows[0].category, 'โต๊ะทำงาน');
assert.equal(result.rows[0].asset_type, 'สำนักงาน');
assert.equal(result.rows[0].acquisition_date, '2013-11-11');
assert.equal(result.rows[1].acquisition_date, '2025-06-12');
assert.equal(result.rows[0].source_row_end, 8);
assert.equal(result.rows[0].status, 'damaged');
assert.equal(result.rows[1].unit_price, 1000);
assert.equal(result.rows[1].location, 'ห้องหนึ่ง');
assert.equal(result.audit.excluded_rows.length, 1);
assert.equal(JSON.parse(result.rows[0].source_data).rows.length, 2);
result = parse(wb({'เครื่อง':form([
  ['','อบจ.อย','ยี่ห้อ A','',3000],
  ['','7450-001/1','รุ่น B','','','','','ห้อง'],
])}), 'registry', 'test.xls');
assert.equal(result.rows.length,1);
assert.equal(result.rows[0].brand_description,'ยี่ห้อ A\nรุ่น B');
assert.equal(result.rows[0].unit_price,3000);
assert.equal(result.rows[0].status,'unverified');
result = parse(wb({'เก้าอี้':form([
  ['11/11/56','','รุ่น','20 ตัว',2200],
  ['','7110-001/1'],['','7110-001/2'],
])}), 'registry', 'test.xls');
assert.equal(result.rows.length,2);
assert.equal(result.rows[0].unit_price,null);
assert.equal(result.audit.context_rows,1);
assert.ok(result.rows[0].source_notes.includes('20 ตัว'));
result = parse(wb({'หนึ่ง':[['category','asset_type','equipment_name','acquisition_date'],['หมวด','สำนักงาน','รายการ','2025-02-31']], 'สอง':[['category','equipment_name'],['หมวดสอง','รายการสอง']]}), 'registry', 'template.xlsx');
assert.equal(result.rows.length,2);
assert.equal(result.rows[0].acquisition_date,null);
assert.equal(result.rows[0].acquisition_date_text,'2025-02-31');
assert.equal(result.rows[1].source_sheet,'สอง');
const inspectionHeaders=['ลำดับที่','รายการ','รหัสครุภัณฑ์','รหัสสินทรัพย์','รายการตรวจสอบ','','','','','หมายเหตุ'];
result=parse(wb({'ตรวจ':[inspectionHeaders,['','','','','ใช้ได้','ชำรุด','เสื่อมคุณภาพ','สูญไป','ไม่ใช้'],[1,'กล้อง','A-1','','ü']]}),'inspection','annual.xlsx',2568);
assert.equal(result.rows[0].location,null,'Missing location must not become an organization name');
result=parse(wb({'ตรวจ':[[...inspectionHeaders,'สถานที่'],['','','','','ใช้ได้','ชำรุด','เสื่อมคุณภาพ','สูญไป','ไม่ใช้'],[1,'กล้อง','A-1','','ü','','','','','','ห้องสมุด']]}),'inspection','annual.xlsx',2568);
assert.equal(result.rows[0].location,'ห้องสมุด');
console.log('Import parser regressions passed');
