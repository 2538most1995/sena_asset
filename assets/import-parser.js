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
    const valid = (y,m,d) => {
      const x=new Date(Date.UTC(y,m-1,d));
      return x.getUTCFullYear()===y && x.getUTCMonth()===m-1 && x.getUTCDate()===d
        ? `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}` : null;
    };
    if (typeof v==='number' && v>20000 && v<90000) {
      const d=XLSX.SSF.parse_date_code(v); return d?valid(d.y,d.m,d.d):null;
    }
    const s=clean(v);
    let m=s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if(m) return valid(Number(m[1])>2400?Number(m[1])-543:Number(m[1]),Number(m[2]),Number(m[3]));
    m=s.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})$/);
    const year = y => y<100?2500+y-543:y>2400?y-543:y;
    if(m) return valid(year(Number(m[3])),Number(m[2]),Number(m[1]));
    const months=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    const shorts=['มค','กพ','มีค','เมย','พค','มิย','กค','สค','กย','ตค','พย','ธค'];
    const compact=s.replace(/[.\s-]/g,'');
    m=compact.match(/^(\d{1,2})([^\d]+)(\d{2,4})$/);
    if(m) { const index=months.indexOf(m[2])>=0?months.indexOf(m[2]):shorts.indexOf(m[2]);
      if(index>=0)return valid(year(Number(m[3])),index+1,Number(m[1])); }
    return null;
  };
  const isDitto = v => /^["”〃]+$/.test(clean(v));
  const isCode = v => /^\d{3,}\s*[-/]/.test(clean(v));
  const statusFrom = text => /ชำรุด/.test(text)?'damaged':/เสื่อม/.test(text)?'degraded':
    /(?:รอ|เสนอ).*จำหน่าย/.test(text)?'unverified':/จำหน่าย/.test(text)?'disposed':
    /ไม่ใช้/.test(text)?'unused':/ใช้ได้|ใช้งานปกติ/.test(text)?'active':'unverified';
  function registryHeading(matrix,h,sheetName) {
    const nameMarker=/ชื่อ\s*หรือ\s*ชนิด\s*(?:ครุภัณฑ์|คุรุภัณฑ์|คุรภัณฑ์)/;
    const row=matrix.slice(0,h).find(r=>r.some(v=>/ประเภท/.test(value(v))))||[];
    const heading=row.filter(v=>!/(?:หน่วยงาน|ส่วนราชการ)/.test(value(v))).map(value).filter(Boolean).join(' ');
    const split=heading.split(nameMarker);
    const tidy=t=>clean(t).replace(/^[.…·_\s]+|[.…·_\s]+$/g,'');
    return {asset_type:tidy((split[0]||'').replace(/^.*?ประเภท(?:ครุภัณฑ์)?/,'')),
      equipment_name:tidy(split.slice(1).join(' ')).replace(/^ก\s+/,'')||sheetName,source_header:heading};
  }
  function parseRegistry(workbook, filename) {
    const rows=[],warnings=[],sheets=[],audit={body_rows:0,record_rows:0,continuation_rows:0,context_rows:0,excluded_rows:[]};
    for(const sheetName of workbook.SheetNames) {
      if(/สารบัญ/.test(sheetName))continue;
      const ws=workbook.Sheets[sheetName];
      const matrix=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:true,blankrows:true});
      const h=matrix.findIndex(r=>r.some(v=>value(v).includes('เลขที่หรือรหัส'))&&r.some(v=>value(v).includes('ราคาต่อหน่วย')));
      if(h<0){warnings.push(`ไม่พบหัวตาราง: ${sheetName}`);continue;}
      const head=matrix[h].map(value),heading=registryHeading(matrix,h,sheetName);
      const locate=t=>head.findIndex(v=>v.includes(t));
      const documentCols=head.map((v,i)=>v==='เลขที่'?i:-1).filter(i=>i>=0);
      const map={equipment_code:locate('เลขที่หรือรหัส'),brand_description:locate('ยี่ห้อ'),serial_number:locate('หมายเลข'),
        unit_price:locate('ราคาต่อหน่วย'),acquisition_method:locate('วิธีการได้มา'),document_number:documentCols[0]??-1,
        location:locate('ใช้ประจำที่'),receipt_evidence:locate('หลักฐาน'),change_details:locate('รายการ'),change_document:documentCols[1]??-1};
      const remarkCols=head.map((v,i)=>v.includes('หมายเหตุ')?i:-1).filter(i=>i>=0);
      // Some forms split the date into two physical columns under a merged heading.
      const dateCol=locate('วัน เดือน ปี'),dateEnd=map.equipment_code;
      const body=[];
      for(let i=h+1;i<matrix.length;i++) {
        const raw=matrix[i]||[];
        if(!raw.some(v=>value(v)))continue;
        if(i===h+1&&raw.some(v=>/^(และลักษณะ|\(บาท\)|เอกสาร|การจ่าย|เปลี่ยนแปลง)$/.test(clean(v))))continue;
        if(raw.some(v=>value(v).includes('เลขที่หรือรหัส')))continue;
        const r={}; for(const [k,col] of Object.entries(map))r[k]=col>=0?clean(raw[col]):'';
        r.remarks=remarkCols.map(j=>clean(raw[j])).filter(Boolean).join('\n');
        const dateParts=raw.slice(dateCol,dateEnd).filter(v=>value(v));
        r.acquisition_date_text=dateParts.map(value).join(' ');
        r.acquisition_date=dateParts.length===1?date(dateParts[0]):date(r.acquisition_date_text);
        r.unit_price=number(r.unit_price);
        r._raw={row:i+1,cells:raw.slice(0,Math.max(...Object.values(map),...remarkCols)+1)};
        body.push(r);audit.body_rows++;
      }
      let last=null,pending=[],sheetCount=0;
      const append=(record,part,role='continuation')=>{
        record._sources.push({...part._raw,role});
        record.source_row_end=Math.max(record.source_row_end,part._raw.row);
        for(const key of [...Object.keys(map),'remarks','acquisition_date_text','acquisition_date']) {
          const v=part[key];if(v===''||v===null||v===undefined||isDitto(v))continue;
          if(key==='equipment_code'){
            if(isCode(v)&&!isCode(record[key]))record[key]=v;
          }else if(record[key]===''||record[key]===null||record[key]===undefined)record[key]=v;
          else if(record[key]!==v&&typeof v==='string'&&key!=='acquisition_date')record[key]=role==='prefix'?v+'\n'+record[key]:record[key]+'\n'+v;
          else if(record[key]!==v&&key==='unit_price')record.source_notes.push(`ราคาในแถว ${part._raw.row}: ${v}`);
        }
      };
      for(let i=0;i<body.length;i++) {
        const part=body[i],next=body[i+1],hasCode=isCode(part.equipment_code),hasDate=!!part.acquisition_date_text&&!isDitto(part.acquisition_date_text);
        const detailKeys=['equipment_code','brand_description','serial_number','unit_price','acquisition_method','document_number','location','receipt_evidence','change_details','change_document','remarks'];
        const hasDetail=detailKeys.some(k=>part[k]!==''&&part[k]!==null&&part[k]!==undefined);
        if(!hasDetail){audit.excluded_rows.push({sheet:sheetName,row:part._raw.row,reason:'มีเฉพาะวันที่ ไม่มีข้อมูลรายการ',cells:part._raw.cells});continue;}
        const groupQuantity=part.serial_number.match(/^(\d+)\s*(?:ตัว|ชุด|เครื่อง|หลัง|ใบ)$/);
        if(!hasCode&&groupQuantity&&Number(groupQuantity[1])>2&&next&&isCode(next.equipment_code)){
          pending.push({...part,_context:true});audit.context_rows++;continue;
        }
        // An organisation prefix followed by the actual code is one two-line item.
        const codePreamble=!hasCode&&part.equipment_code&&next&&isCode(next.equipment_code)&&!next.acquisition_date_text;
        const pricePreamble=!hasCode&&!hasDate&&part.unit_price!==null&&last&&last.unit_price!==null&&part.unit_price!==last.unit_price&&next&&isCode(next.equipment_code)&&next.unit_price===null;
        if(codePreamble||pricePreamble){pending.push(part);audit.continuation_rows++;continue;}
        const starts=hasCode||hasDate||!last||pending.length>0;
        if(!starts&&last){append(last,part);audit.continuation_rows++;continue;}
        const record={...heading,category:sheetName,...part,source_file:filename,source_sheet:sheetName,source_row:part._raw.row,source_row_end:part._raw.row,
          source_key:`registry:${filename}:${sheetName}:${part._raw.row}`,_sources:[],source_notes:[]};
        delete record._raw;
        for(const key of [...Object.keys(map),'remarks','acquisition_date_text','acquisition_date']){
          if(isDitto(record[key]))record[key]=last?last[key]:(key==='unit_price'||key==='acquisition_date'?null:'');
        }
        // Resolve ditto marks in the original price cell; number() deliberately rejects them.
        if(isDitto(part._raw.cells[map.unit_price]))record.unit_price=last?last.unit_price:null;
        for(const pre of pending){
          if(pre._context){record._sources.push({...pre._raw,role:'group_header'});record.source_notes.push(`หัวกลุ่มแถว ${pre._raw.row}: ${pre.serial_number} ราคา ${pre.unit_price??'ไม่ระบุ'} บาท`);}
          else {record.source_row=Math.min(record.source_row,pre._raw.row);append(record,pre,'prefix');}
        }
        pending=[];record._sources.push({...part._raw,role:'item'});
        // The code remains the code in the item row; any prefix is preserved verbatim in source_data.
        if(hasCode)record.equipment_code=part.equipment_code;
        record.source_key=`registry:${filename}:${sheetName}:${record.source_row}`;
        rows.push(record);last=record;sheetCount++;audit.record_rows++;
      }
      if(pending.length)warnings.push(`มีแถวรอจับคู่ท้ายชีต ${sheetName}`);
      for(const r of rows.filter(r=>r.source_sheet===sheetName)){
        r.status=statusFrom([r.remarks,r.change_details].join(' '));
        r.source_data=JSON.stringify({header:heading.source_header,rows:r._sources.sort((a,b)=>a.row-b.row)});
        r.source_notes=r.source_notes.join('\n');delete r._sources;
      }
      sheets.push({source_sheet:sheetName,category_name:sheetName,asset_type:heading.asset_type,equipment_name:heading.equipment_name,source_file:filename,source_header:heading.source_header,item_count:sheetCount});
    }
    for(const excluded of audit.excluded_rows)warnings.push(`${excluded.sheet} แถว ${excluded.row}: ${excluded.reason}`);
    return {rows,warnings,sheets,audit};
  }
  function parseInspection(workbook, filename, year) {
    const rows = [], warnings = [];
    for (const sheetName of workbook.SheetNames) {
      const matrix = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {header:1, defval:'', raw:true, blankrows:true});
      const h = matrix.findIndex(row => row.some(v => value(v).includes('ลำดับที่')) && row.some(v => value(v).includes('รายการ')));
      if (h < 0) { warnings.push(`ไม่พบหัวตาราง: ${sheetName}`); continue; }
      const locationColumn=(matrix[h]||[]).findIndex(v=>/สถานที่|ใช้ประจำที่/.test(value(v)));
      for (let i=h+2; i<matrix.length; i++) {
        const r = matrix[i] || [];
        const n = Number(r[0]);
        if (!Number.isInteger(n) || n < 1 || !clean(r[1])) continue;
        rows.push({item_number:n,item_name:clean(r[1]),asset_code:clean(r[2]),asset_id_code:clean(r[3]),
          status_usable:mark(r[4]),status_damaged:mark(r[5]),status_degraded:mark(r[6]),
          status_lost:mark(r[7]),status_unused:mark(r[8]),remarks:clean(r[9]),fiscal_year:year,
          location:locationColumn>=0?clean(r[locationColumn]):null,
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
      if(type==='registry') {r.acquisition_date_text=value(r.acquisition_date);r.unit_price=number(r.unit_price);r.acquisition_date=date(r.acquisition_date);r.source_row_end=i+2;r.source_data=JSON.stringify({header:matrix[0],rows:[{row:i+2,cells,role:'item'}]});r.status=clean(r.status)||'unverified';}
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
    if(head.includes(type==='registry'?'equipment_name':'item_number')) {
      const rows=[];for(const sheetName of workbook.SheetNames){
        const result=parseTemplate({SheetNames:[sheetName],Sheets:{[sheetName]:workbook.Sheets[sheetName]}},type,filename,year);rows.push(...result.rows);
      }return {rows,warnings:[]};
    }
    return type==='registry'?parseRegistry(workbook,filename):parseInspection(workbook,filename,year);
  }
  return {parse};
})();
