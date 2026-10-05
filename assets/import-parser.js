/* Workbook normalization shared by the import screen. Source sheet and row are kept
 * because old registers contain repeated codes and items without a code. */
window.SenaImport = (() => {
  const value = v => v == null ? '' : String(v).replace(/\u00a0/g, ' ').trim();
  const clean = v => value(v).replace(/\s+/g, ' ');
  const mark = v => /^(1|true|yes|y|✓|✔|ü|√|x|\/|ใช่)$/i.test(clean(v));
  const number = v => {
    const n = Number(clean(v).replace(/,/g, ''));
    return Number.isFinite(n) && clean(v) !== '' ? n : null;
  };
  const date = v => {
    if (typeof v === 'number' && v > 20000 && v < 90000) {
      const d = XLSX.SSF.parse_date_code(v);
      return d ? `${d.y}-${String(d.m).padStart(2,'0')}-${String(d.d).padStart(2,'0')}` : null;
    }
    const s = clean(v);
    if (/^\d{4}-\d\d-\d\d$/.test(s)) return s;
    const m = s.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
    if (m) {
      const y=Number(m[3])>2400?Number(m[3])-543:Number(m[3]), mo=Number(m[2]), day=Number(m[1]);
      const check=new Date(Date.UTC(y,mo-1,day));
      if(check.getUTCFullYear()===y && check.getUTCMonth()===mo-1 && check.getUTCDate()===day)
        return `${y}-${String(mo).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    }
    return null;
  };
  const categoryFromHeading = (matrix) => {
    const heading = matrix.slice(0, 4).flat().map(value).find(v => v.includes('ประเภท') && /ชื่อ|ชนิด/.test(v)) || '';
    const part = heading.split(/ชื่อ\s*หรือ\s*ชนิด|ชื่อ\s*ครุภัณฑ์|ชื่อ\s*คุรภัณฑ์/)[0].replace(/^.*?ประเภท(?:ครุภัณฑ์)?/, '').replace(/[.…·_\s]+/g, ' ').trim();
    return part && part.length < 65 ? part : 'ไม่ระบุประเภท';
  };
  const columns = ['acquisition_date','equipment_code','brand_description','serial_number','unit_price','acquisition_method','document_number','location','receipt_evidence','change_details','change_document','remarks'];
  function parseRegistry(workbook, filename) {
    const rows = [], warnings = [];
    for (const sheetName of workbook.SheetNames) {
      if (/สารบัญ/.test(sheetName)) continue;
      const matrix = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {header:1, defval:'', raw:true, blankrows:true});
      const h = matrix.findIndex(row => row.some(cell => value(cell).includes('เลขที่หรือรหัส')) && row.some(cell => value(cell).includes('ราคาต่อหน่วย')));
      if (h < 0) { warnings.push(`ไม่พบหัวตาราง: ${sheetName}`); continue; }
      const head = matrix[h].map(value);
      const codeCol = head.findIndex(v => v.includes('เลขที่หรือรหัส'));
      const dateCol = head.findIndex(v => v.includes('วัน เดือน ปี'));
      const category = categoryFromHeading(matrix);
      let last = null;
      for (let i=h+2; i<matrix.length; i++) {
        const raw = matrix[i] || [];
        if (!raw.some(v => value(v))) continue;
        const get = j => raw[j] ?? '';
        const code = clean(get(codeCol));
        const description = clean(get(codeCol+1));
        const price = number(get(codeCol+3));
        const acquired = date(get(dateCol));
        const method = clean(get(codeCol+4));
        const location = clean(get(codeCol+6));
        const strongCode = /\d{3,}[-/]/.test(code);
        const start = strongCode || acquired || price !== null || (method && description) || (location && description && !last);
        if (!start && last) {
          if (description && description !== '"') last.brand_description = [last.brand_description, description].filter(Boolean).join(' ');
          continue;
        }
        if (!start) continue;
        const row = {category, equipment_name: sheetName, equipment_code: code, brand_description: description,
          serial_number:clean(get(codeCol+2)), unit_price:price, acquisition_method:method,
          document_number:clean(get(codeCol+5)), location, receipt_evidence:clean(get(codeCol+7)),
          change_details:clean(get(codeCol+8)), change_document:clean(get(codeCol+9)), remarks:clean(get(codeCol+10)),
          acquisition_date:acquired, status:/ชำรุด/.test(clean(get(codeCol+10)))?'damaged':/เสื่อม/.test(clean(get(codeCol+10)))?'degraded':/จำหน่าย/.test(clean(get(codeCol+10)))?'disposed':/ไม่ใช้/.test(clean(get(codeCol+10)))?'unused':'active',
          source_file:filename, source_sheet:sheetName, source_row:i+1,
          source_key:`registry:${filename}:${sheetName}:${i+1}`};
        rows.push(row); last = row;
      }
    }
    return {rows,warnings};
  }
  function parseInspection(workbook, filename, year) {
    const rows = [], warnings = [];
    for (const sheetName of workbook.SheetNames) {
      const matrix = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {header:1, defval:'', raw:true, blankrows:true});
      const h = matrix.findIndex(row => row.some(v => value(v).includes('ลำดับที่')) && row.some(v => value(v).includes('รายการ')));
      if (h < 0) { warnings.push(`ไม่พบหัวตาราง: ${sheetName}`); continue; }
      for (let i=h+2; i<matrix.length; i++) {
        const r = matrix[i] || [];
        const n = Number(r[0]);
        if (!Number.isInteger(n) || n < 1 || !clean(r[1])) continue;
        rows.push({item_number:n,item_name:clean(r[1]),asset_code:clean(r[2]),asset_id_code:clean(r[3]),
          status_usable:mark(r[4]),status_damaged:mark(r[5]),status_degraded:mark(r[6]),
          status_lost:mark(r[7]),status_unused:mark(r[8]),remarks:clean(r[9]),fiscal_year:year,
          source_file:filename,source_sheet:sheetName,source_row:i+1,source_key:`inspection:${filename}:${year}:${sheetName}:${n}`});
      }
    }
    return {rows,warnings};
  }
  function parseTemplate(workbook,type,filename,year) {
    const sheetName = workbook.SheetNames[0];
    const matrix = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {header:1,defval:'',raw:true});
    const heads = (matrix[0]||[]).map(clean);
    const required = type === 'registry' ? ['category','equipment_name'] : ['item_number','item_name'];
    if (!required.every(x => heads.includes(x))) throw new Error('หัวคอลัมน์ไม่ตรงกับเทมเพลต กรุณาดาวน์โหลดเทมเพลตใหม่');
    const rows = matrix.slice(1).map((cells,i) => {
      const r={}; heads.forEach((h,j)=>{if(h)r[h]=cells[j]??'';});
      if(type==='registry') {r.unit_price=number(r.unit_price);r.acquisition_date=date(r.acquisition_date);}
      else {r.fiscal_year=Number(r.fiscal_year)||year; for(const k of ['status_usable','status_damaged','status_degraded','status_lost','status_unused'])r[k]=mark(r[k]);}
      r.source_file=filename;r.source_sheet=sheetName;r.source_row=i+2;
      r.source_key=`${type}:${filename}:${sheetName}:${i+2}:${type==='registry'?clean(r.equipment_code):r.fiscal_year+':'+r.item_number}`;
      return r;
    }).filter(r=>clean(r[type==='registry'?'equipment_name':'item_name']));
    return {rows,warnings:[]};
  }
  function parse(workbook,type,filename,year=2568) {
    const first = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]],{header:1,defval:'',raw:true});
    const head=(first[0]||[]).map(clean);
    if(head.includes(type==='registry'?'equipment_name':'item_number')) return parseTemplate(workbook,type,filename,year);
    return type==='registry'?parseRegistry(workbook,filename):parseInspection(workbook,filename,year);
  }
  return {parse};
})();
