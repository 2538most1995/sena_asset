<?php
require_once __DIR__.'/backup_service.php';

function sena_registry_source_sql(int $year): string {
    $year=max(2500,min(2700,$year));
    $columns=['id','category','asset_type','brand_description','serial_number','unit_price','acquisition_method','document_number','receipt_evidence','change_details','change_document','remarks','acquisition_date','acquisition_date_text','image_url','source_file','source_sheet','source_row','source_row_end','source_key','source_data','source_notes','created_at','updated_at'];
    $select=implode(',',array_map(fn($column)=>'e.'.$column,$columns));
    foreach(['usable','damaged','degraded','lost','unused'] as $flag)$select.=",i.status_$flag AS annual_status_$flag";
    $label="COALESCE(NULLIF(CONCAT_WS(' / ',IF(i.status_usable=1,'ใช้ได้',NULL),IF(i.status_damaged=1,'ชำรุด',NULL),IF(i.status_degraded=1,'เสื่อมคุณภาพ',NULL),IF(i.status_lost=1,'สูญไป',NULL),IF(i.status_unused=1,'ไม่ใช้',NULL)),''),'รอตรวจนับ')";
    // Status and the item label belong to the selected annual account, not whichever year was opened last.
    $status="CASE WHEN i.status_damaged=1 THEN 'damaged' WHEN i.status_degraded=1 THEN 'degraded' WHEN i.status_lost=1 THEN 'unverified' WHEN i.status_unused=1 THEN 'unused' WHEN i.status_usable=1 THEN 'active' ELSE 'unverified' END";
    return "(SELECT $select,i.item_number AS inspection_item_number,i.item_name AS equipment_name,i.asset_code AS equipment_code,COALESCE(NULLIF(TRIM(i.location),''),e.location) AS location,$status AS status,$label AS inspection_status_label FROM equipment_registry e JOIN inspection_items i ON i.registry_id=e.id WHERE i.fiscal_year=$year) annual_registry";
}
function sena_link_code($code): string {
    $code=preg_replace('/\s+/u','',trim((string)$code));
    return in_array($code,['','-','ไม่มี','ไม่ระบุ'],true)?'':$code;
}
function sena_linked_status_filter(string $status): ?string {
    $flag=['active'=>'usable','usable'=>'usable','damaged'=>'damaged','degraded'=>'degraded','unused'=>'unused','disposed'=>'unused','lost'=>'lost'][$status]??null;
    if($flag)return "annual_status_$flag=1";
    if($status==='dispose')return '(annual_status_lost=1 OR annual_status_unused=1)';
    if($status==='unverified')return '(annual_status_usable=0 AND annual_status_damaged=0 AND annual_status_degraded=0 AND annual_status_lost=0 AND annual_status_unused=0)';
    return null;
}
function sena_master_status(array $row): string {
    foreach(['damaged'=>'damaged','degraded'=>'degraded','lost'=>'unverified','unused'=>'unused','usable'=>'active'] as $flag=>$status)
        if(!empty($row['status_'.$flag]))return $status;
    return 'unverified';
}
function sena_year_lock_key(PDO $pdo,int $year): string {
    return 'sena-link-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,20).'-'.$year;
}
function sena_year_lock(PDO $pdo,int $year,callable $action) {
    $key=sena_year_lock_key($pdo,$year);
    $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$key]);
    if((int)$lock->fetchColumn()!==1)throw new RuntimeException('มีการบันทึกปีนี้อยู่ กรุณาลองอีกครั้ง');
    $ownsTransaction=!$pdo->inTransaction();
    try {
        if($ownsTransaction)$pdo->beginTransaction();
        $result=$action();
        if($ownsTransaction)$pdo->commit();
        return $result;
    }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    finally {$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$key]);}
}
/** Backfill stable identities once, without deleting old registry records or annual observations. */
function sena_link_inspection_year(PDO $pdo,int $year,bool $backup=true): array {
    if($year<2500||$year>2700)throw new InvalidArgumentException('ปีงบประมาณไม่ถูกต้อง');
    $pending=$pdo->prepare('SELECT COUNT(*) FROM inspection_items i LEFT JOIN equipment_registry e ON e.id=i.registry_id WHERE i.fiscal_year=? AND e.id IS NULL');
    $pending->execute([$year]);if(!(int)$pending->fetchColumn())return ['linked'=>0,'created'=>0];
    return sena_year_lock($pdo,$year,function()use($pdo,$year,$backup){
        $stmt=$pdo->prepare('SELECT i.* FROM inspection_items i LEFT JOIN equipment_registry e ON e.id=i.registry_id WHERE i.fiscal_year=? AND e.id IS NULL ORDER BY i.item_number,i.id FOR UPDATE');
        $stmt->execute([$year]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows)return ['linked'=>0,'created'=>0];
        if($backup)sena_save_backup($pdo);
        $byCode=[];
        foreach($pdo->query('SELECT id,equipment_code,category,image_url FROM equipment_registry') as $asset){$code=sena_link_code($asset['equipment_code']);if($code!=='')$byCode[$code][]=$asset;}
        $used=$pdo->prepare('SELECT registry_id FROM inspection_items WHERE fiscal_year=? AND registry_id IS NOT NULL');$used->execute([$year]);$claimed=array_fill_keys($used->fetchAll(PDO::FETCH_COLUMN),true);
        $previous=[];
        foreach($pdo->query('SELECT i.item_number,i.asset_code,i.registry_id FROM inspection_items i JOIN equipment_registry e ON e.id=i.registry_id') as $old){
            $code=sena_link_code($old['asset_code']);if($code!=='')$previous[$code.':'.$old['item_number']][(int)$old['registry_id']]=true;
        }
        $knownCategories=array_fill_keys($pdo->query('SELECT category_name FROM asset_categories WHERE registry_enabled=1')->fetchAll(PDO::FETCH_COLUMN),true);
        $create=$pdo->prepare('INSERT INTO equipment_registry(equipment_name,equipment_code,category,location,unit_price,status,image_url,source_notes) VALUES(?,?,?,?,?,?,?,?)');
        $categoryInsert=$pdo->prepare('INSERT INTO asset_categories(category_name,registry_enabled) VALUES(?,1) ON DUPLICATE KEY UPDATE registry_enabled=1');
        $link=$pdo->prepare('UPDATE inspection_items SET registry_id=?,image_url=? WHERE id=?');
        $update=$pdo->prepare('UPDATE equipment_registry SET equipment_name=?,status=?,location=COALESCE(NULLIF(?,\'\'),location),unit_price=COALESCE(?,unit_price),image_url=COALESCE(NULLIF(image_url,\'\'),?) WHERE id=?');
        $created=0;
        foreach($rows as $row){
            $code=sena_link_code($row['asset_code']);$candidates=$code!==''?($byCode[$code]??[]):[];
            $prior=$previous[$code.':'.$row['item_number']]??[];
            if(count($prior)===1)$candidates=array_values(array_filter($candidates,fn($a)=>isset($prior[(int)$a['id']])));
            $asset=count($candidates)===1&&!isset($claimed[$candidates[0]['id']])?$candidates[0]:null;
            if(!$asset){
                $candidateCategories=array_values(array_unique(array_filter(array_column($candidates,'category'),fn($category)=>trim((string)$category)!=='')));
                $category=count($candidateCategories)===1?$candidateCategories[0]:(isset($knownCategories[$row['category']??''])?$row['category']:'ไม่ระบุหมวด');
                $notes=!empty($row['category'])&&$category!==$row['category']?'หมวดเดิมในบัญชีตรวจ: '.$row['category']:null;
                $create->execute([$row['item_name'],$row['asset_code'],$category,$row['location'],$row['price'],sena_master_status($row),$row['image_url'],$notes]);
                $createdRegistryId=(int)$pdo->lastInsertId();
                $categoryInsert->execute([$category]);
                $asset=['id'=>$createdRegistryId,'image_url'=>$row['image_url']];$created++;
            }
            $photo=$asset['image_url']?:$row['image_url'];
            $update->execute([$row['item_name'],sena_master_status($row),$row['location'],$row['price'],$photo,$asset['id']]);
            $link->execute([$asset['id'],$photo,$row['id']]);$claimed[$asset['id']]=true;
        }
        return ['linked'=>count($rows),'created'=>$created];
    });
}
function sena_sync_registry_details(PDO $pdo,int $registryId): void {
    $stmt=$pdo->prepare('UPDATE inspection_items i JOIN equipment_registry e ON e.id=i.registry_id SET i.item_name=e.equipment_name,i.asset_code=e.equipment_code,i.category=e.category,i.location=e.location,i.price=e.unit_price,i.image_url=e.image_url WHERE e.id=?');
    $stmt->execute([$registryId]);
}
function sena_registry_status_to_year(PDO $pdo,int $registryId,int $year,string $status): void {
    $stmt=$pdo->prepare('UPDATE inspection_items SET status_usable=?,status_damaged=?,status_degraded=?,status_lost=0,status_unused=?,is_overlap=0 WHERE registry_id=? AND fiscal_year=?');
    $stmt->execute([(int)($status==='active'),(int)($status==='damaged'),(int)($status==='degraded'),(int)in_array($status,['unused','disposed'],true),$registryId,$year]);
}
function sena_sync_inspection_details(PDO $pdo,int $inspectionId): void {
    $stmt=$pdo->prepare('SELECT * FROM inspection_items WHERE id=?');$stmt->execute([$inspectionId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('ไม่พบรายการตรวจ');
    sena_link_inspection_year($pdo,(int)$row['fiscal_year']);
    $stmt->execute([$inspectionId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    $update=$pdo->prepare('UPDATE equipment_registry SET equipment_name=?,equipment_code=?,category=?,location=?,unit_price=?,image_url=COALESCE(NULLIF(?,\'\'),image_url) WHERE id=?');
    $update->execute([$row['item_name'],$row['asset_code'],$row['category'],$row['location'],$row['price'],$row['image_url'],$row['registry_id']]);
    sena_sync_registry_details($pdo,(int)$row['registry_id']);
}
function sena_attach_registry_to_year(PDO $pdo,int $registryId,int $year): int {
    if($year<2500||$year>2700)throw new InvalidArgumentException('ปีงบประมาณไม่ถูกต้อง');
    return sena_year_lock($pdo,$year,function()use($pdo,$registryId,$year){
        $existing=$pdo->prepare('SELECT id FROM inspection_items WHERE registry_id=? AND fiscal_year=?');$existing->execute([$registryId,$year]);if($id=$existing->fetchColumn())return (int)$id;
        $stmt=$pdo->prepare('SELECT * FROM equipment_registry WHERE id=?');$stmt->execute([$registryId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('ไม่พบครุภัณฑ์');
        $max=$pdo->prepare('SELECT COALESCE(MAX(item_number),0)+1 FROM inspection_items WHERE fiscal_year=?');$max->execute([$year]);$number=(int)$max->fetchColumn();
        $insert=$pdo->prepare('INSERT INTO inspection_items(registry_id,item_number,item_name,asset_code,category,location,price,image_url,fiscal_year) VALUES(?,?,?,?,?,?,?,?,?)');
        $insert->execute([$registryId,$number,$row['equipment_name'],$row['equipment_code'],$row['category'],$row['location'],$row['unit_price'],$row['image_url'],$year]);
        return (int)$pdo->lastInsertId();
    });
}
/** Photo updates use stable IDs, never asset codes that can be duplicated. */
function sena_set_asset_photo(PDO $pdo,int $registryId,string $url): void {
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id FROM equipment_registry WHERE id=? FOR UPDATE');$stmt->execute([$registryId]);if(!$stmt->fetchColumn())throw new RuntimeException('ไม่พบครุภัณฑ์');
        $stmt=$pdo->prepare('UPDATE equipment_registry SET image_url=? WHERE id=?');$stmt->execute([$url,$registryId]);
        $stmt=$pdo->prepare('UPDATE inspection_items SET image_url=? WHERE registry_id=?');$stmt->execute([$url,$registryId]);
        if($own)$pdo->commit();
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
