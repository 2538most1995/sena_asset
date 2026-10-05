<?php
/** Import normalized rows. A source row is the identity; repeated asset codes are retained. */
function sena_import(PDO $pdo, string $type, array $rows, bool $replace = false): array {
    if (!in_array($type, ['registry', 'inspection'], true)) throw new InvalidArgumentException('ประเภทข้อมูลไม่ถูกต้อง');
    if (count($rows) > 10000) throw new InvalidArgumentException('ไฟล์มีข้อมูลมากเกิน 10,000 รายการ');
    $table = $type === 'registry' ? 'equipment_registry' : 'inspection_items';
    $fields = $type === 'registry'
      ? ['category','equipment_name','equipment_code','brand_description','serial_number','unit_price','acquisition_method','document_number','location','receipt_evidence','change_details','change_document','remarks','acquisition_date','status','source_file','source_sheet','source_row','source_key']
      : ['item_number','item_name','asset_code','asset_id_code','status_usable','status_damaged','status_degraded','status_lost','status_unused','remarks','fiscal_year','category','location','price','source_file','source_sheet','source_row','source_key'];
    $pdo->beginTransaction();
    try {
        $oldImages=[];
        if ($replace) {
            if ($type === 'registry') {
                foreach ($pdo->query("SELECT equipment_code,image_url FROM equipment_registry WHERE image_url IS NOT NULL AND image_url<>''") as $old)
                    if ($old['equipment_code'] !== '') $oldImages[$old['equipment_code']]=$old['image_url'];
                $pdo->exec('DELETE FROM equipment_registry');
            }
            else {
                $years = array_unique(array_map(fn($r) => (int)($r['fiscal_year'] ?? 2568), $rows));
                $del = $pdo->prepare('DELETE FROM inspection_items WHERE fiscal_year = ?');
                foreach ($years as $year) $del->execute([$year]);
            }
        }
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', array_fill(0,count($fields),'?')) . ") ON DUPLICATE KEY UPDATE id=id";
        $stmt = $pdo->prepare($sql);
        $existing = $type === 'inspection' && !$replace ? $pdo->prepare('SELECT id FROM inspection_items WHERE fiscal_year=? AND item_number=? LIMIT 1') : null;
        $inserted=0; $skipped=0; $categories=[]; $errors=[];
        foreach ($rows as $i=>$row) {
            if (!is_array($row)) { $errors[]='รายการ '.($i+1).' ไม่ถูกต้อง'; continue; }
            $name = trim((string)($row[$type==='registry'?'equipment_name':'item_name'] ?? ''));
            if ($name === '') { $errors[]='รายการ '.($i+1).' ไม่มีชื่อ'; continue; }
            $key = trim((string)($row['source_key'] ?? ''));
            if ($key === '' || strlen($key)>500) { $errors[]='รายการ '.($i+1).' ไม่มีรหัสแหล่งข้อมูล'; continue; }
            if ($existing) {
                $existing->execute([(int)($row['fiscal_year'] ?? 2568),(int)($row['item_number'] ?? 0)]);
                if ($existing->fetchColumn()) { $skipped++; continue; }
            }
            $values=[];
            foreach ($fields as $field) {
                $v=$row[$field] ?? null;
                if ($field==='status' && !$v) $v='active';
                if ($field==='fiscal_year' && !$v) $v=2568;
                if (in_array($field,['unit_price','price'],true)) $v=$v==='' || $v===null ? null : (float)str_replace(',','',(string)$v);
                if (strpos($field,'status_')===0) $v=!empty($v) ? 1:0;
                if (is_string($v)) $v=trim($v);
                $values[]=$v;
            }
            $stmt->execute($values);
            if ($stmt->rowCount()===1) $inserted++; else $skipped++;
            if ($type==='registry' && trim((string)($row['category']??''))!=='') $categories[trim($row['category'])]=true;
        }
        if ($errors) throw new InvalidArgumentException(implode('; ',array_slice($errors,0,5)));
        if ($categories) {
            $cat = $pdo->prepare('INSERT INTO asset_categories (category_name) SELECT ? WHERE NOT EXISTS (SELECT 1 FROM asset_categories WHERE category_name=?)');
            foreach (array_keys($categories) as $name) $cat->execute([$name,$name]);
        }
        if ($oldImages) {
            $attach=$pdo->prepare("UPDATE equipment_registry SET image_url=? WHERE equipment_code=? AND image_url IS NULL");
            foreach ($oldImages as $code=>$url) $attach->execute([$url,$code]);
        }
        $pdo->commit();
        return ['success'=>true,'inserted'=>$inserted,'skipped'=>$skipped,'count'=>$inserted,'total'=>count($rows)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
