<?php
/** Import normalized rows. A source row is the identity; repeated asset codes are retained. */
function sena_import(PDO $pdo, string $type, array $rows, bool $replace = false, array $sheets = []): array {
    if (!in_array($type, ['registry', 'inspection'], true)) throw new InvalidArgumentException('ประเภทข้อมูลไม่ถูกต้อง');
    if (count($rows) > 10000) throw new InvalidArgumentException('ไฟล์มีข้อมูลมากเกิน 10,000 รายการ');
    $table = $type === 'registry' ? 'equipment_registry' : 'inspection_items';
    $fields = $type === 'registry'
      ? ['asset_type','acquisition_date_text','source_row_end','source_data','source_notes','category','equipment_name','equipment_code','brand_description','serial_number','unit_price','acquisition_method','document_number','location','receipt_evidence','change_details','change_document','remarks','acquisition_date','status','source_file','source_sheet','source_row','source_key']
      : ['item_number','item_name','asset_code','asset_id_code','status_usable','status_damaged','status_degraded','status_lost','status_unused','remarks','fiscal_year','category','location','price','source_file','source_sheet','source_row','source_key'];
    $pdo->beginTransaction();
    try {
        $legacy=[];$incomingCodes=[];
        $normalizeCode=static fn($s)=>preg_replace('/\s+/u','',trim((string)$s));
        if($replace && $type==='registry'){
            foreach($rows as $r){$c=$normalizeCode($r['equipment_code']??'');if($c!=='')$incomingCodes[$c]=($incomingCodes[$c]??0)+1;}
            foreach($pdo->query("SELECT id,equipment_code FROM equipment_registry WHERE source_key IS NULL OR source_key='' FOR UPDATE") as $old){
                $c=$normalizeCode($old['equipment_code']);if($c!=='')$legacy[$c][]=(int)$old['id'];
            }
        }
        if ($replace && $type === 'inspection') {
            $years = array_unique(array_map(fn($r) => (int)($r['fiscal_year'] ?? 2568), $rows));
            $del = $pdo->prepare('DELETE FROM inspection_items WHERE fiscal_year = ?');
            foreach ($years as $year) $del->execute([$year]);
        }
        $updates=$replace && $type==='registry'
            ? implode(',',array_map(fn($f)=>"`$f`=VALUES(`$f`)",$fields)) : 'id=id';
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ") ON DUPLICATE KEY UPDATE $updates";
        $kept=[];$updated=0;
        $identity=$pdo->prepare('SELECT id FROM equipment_registry WHERE source_key=?');
        $stmt = $pdo->prepare($sql);
        $legacyUpdate=$type==='registry'?$pdo->prepare('UPDATE equipment_registry SET '.implode(',',array_map(fn($f)=>"`$f`=?",$fields)).' WHERE id=?'):null;
        $existing = $type === 'inspection' && !$replace ? $pdo->prepare('SELECT id FROM inspection_items WHERE fiscal_year=? AND item_number=? LIMIT 1') : null;
        $inserted=0; $skipped=0; $categories=[]; $errors=[];
        foreach ($rows as $i=>$row) {
            if (!is_array($row)) { $errors[]='รายการ '.($i+1).' ไม่ถูกต้อง'; continue; }
            $name = trim((string)($row[$type==='registry'?'equipment_name':'item_name'] ?? ''));
            if ($name === '') { $errors[]='รายการ '.($i+1).' ไม่มีชื่อ'; continue; }
            $key = trim((string)($row['source_key'] ?? ''));
            if ($key === '' || mb_strlen($key)>500) { $errors[]='รายการ '.($i+1).' ไม่มีรหัสแหล่งข้อมูล'; continue; }
            if ($existing) {
                $existing->execute([(int)($row['fiscal_year'] ?? 2568),(int)($row['item_number'] ?? 0)]);
                if ($existing->fetchColumn()) { $skipped++; continue; }
            }
            $values=[];
            foreach ($fields as $field) {
                $v=$row[$field] ?? null;
                if ($field==='status') {
                    $v=$v ?: 'unverified';
                    if (!in_array($v,['active','damaged','degraded','disposed','unused','unverified'],true)) throw new InvalidArgumentException('สถานะทะเบียนไม่ถูกต้อง');
                }
                if ($field==='fiscal_year' && !$v) $v=2568;
                if (in_array($field,['unit_price','price'],true)) $v=$v==='' || $v===null ? null : (float)str_replace(',','',(string)$v);
                if (strpos($field,'status_')===0) $v=!empty($v) ? 1:0;
                if (is_string($v)) $v=trim($v);
                $values[]=$v;
            }
            $c=$normalizeCode($row['equipment_code']??'');
            $matchLegacy=$replace && $type==='registry' && ($incomingCodes[$c]??0)===1 && count($legacy[$c]??[])===1;
            if($matchLegacy){$identity->execute([$key]);$matchLegacy=!$identity->fetchColumn();}
            if($matchLegacy){
                $legacyUpdate->execute(array_merge($values,[$legacy[$c][0]]));unset($legacy[$c]);$updated++;
            }else{
                $stmt->execute($values);
                if ($stmt->rowCount()===1) $inserted++; elseif($stmt->rowCount()===2) $updated++; else $skipped++;
            }
            if($replace && $type==='registry'){$identity->execute([$key]);$kept[]=(int)$identity->fetchColumn();}
            if ($type==='registry' && trim((string)($row['category']??''))!=='') $categories[trim($row['category'])]=true;
        }
        if ($errors) throw new InvalidArgumentException(implode('; ',array_slice($errors,0,5)));
        if ($type==='registry') {
            $sheetInsert=$pdo->prepare('INSERT INTO registry_source_sheets(source_file,source_sheet,category_name,asset_type,equipment_name,item_count,source_header) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE category_name=VALUES(category_name),asset_type=VALUES(asset_type),equipment_name=VALUES(equipment_name),item_count=VALUES(item_count),source_header=VALUES(source_header)');
            foreach ($sheets as $sheet) {
                if (!is_array($sheet) || empty($sheet['category_name']) || empty($sheet['source_sheet']) || empty($sheet['source_file'])) throw new InvalidArgumentException('ข้อมูลหมวดชีตไม่ครบ');
                $categories[trim($sheet['category_name'])]=true;
                $sheetInsert->execute([$sheet['source_file'],$sheet['source_sheet'],$sheet['category_name'],$sheet['asset_type']??null,$sheet['equipment_name']??null,(int)($sheet['item_count']??0),json_encode($sheet['source_header']??[],JSON_UNESCAPED_UNICODE)]);
            }
        }
        if ($categories) {
            $cat = $pdo->prepare('INSERT INTO asset_categories (category_name,registry_enabled) VALUES (?,1) ON DUPLICATE KEY UPDATE registry_enabled=1');
            foreach (array_keys($categories) as $name) $cat->execute([$name]);
        }
        if ($replace && $type==='registry') {
            if (!$kept) throw new InvalidArgumentException('Empty replacement is not allowed');
            $purge=$pdo->prepare('DELETE FROM equipment_registry WHERE id NOT IN ('.implode(',',array_fill(0,count($kept),'?')).')');
            $purge->execute($kept);
            $pdo->exec('UPDATE asset_categories c SET registry_enabled=0 WHERE NOT EXISTS (SELECT 1 FROM equipment_registry e WHERE e.category=c.category_name) AND NOT EXISTS (SELECT 1 FROM registry_source_sheets s WHERE s.category_name=c.category_name)');
        }
        $pdo->commit();
        return ['success'=>true,'inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped,'count'=>$inserted,'total'=>count($rows)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
