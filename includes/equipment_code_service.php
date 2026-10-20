<?php
require_once __DIR__.'/asset_link_service.php';
/** The active inspection account is the newest year containing records, not a fixed year. */
function sena_default_inspection_year(PDO $pdo): int {
    $year = (int)$pdo->query('SELECT MAX(fiscal_year) FROM inspection_items WHERE fiscal_year BETWEEN 2500 AND 2700')->fetchColumn();
    if ($year) return $year;
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    return (int)$now->format('Y') + 543 + ((int)$now->format('n') >= 10 ? 1 : 0);
}
function sena_code_conflicts(PDO $pdo, string $code, int $year, int $registryId=0, int $inspectionId=0): array {
    $normalized = sena_link_code($code);
    if ($normalized === '') return [];
    $stmt=$pdo->prepare('SELECT i.id, i.registry_id, i.asset_code, i.item_name, i.fiscal_year,
        COALESCE(NULLIF(TRIM(i.location),\'\'),e.location) AS location
        FROM inspection_items i LEFT JOIN equipment_registry e ON e.id=i.registry_id
        WHERE i.fiscal_year=? AND i.id<>? AND (?=0 OR COALESCE(i.registry_id,0)<>?) ORDER BY i.item_number,i.id');
    $stmt->execute([$year,$inspectionId,$registryId,$registryId]);
    return array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC),fn($row)=>sena_link_code($row['asset_code'])===$normalized));
}
/** Manual edits sync the master across linked accounts; check every affected year. */
function sena_code_affected_years(PDO $pdo, int $year, int $registryId): array {
    $years=[$year];
    if($registryId>0){$stmt=$pdo->prepare('SELECT DISTINCT fiscal_year FROM inspection_items WHERE registry_id=?');$stmt->execute([$registryId]);$years=array_merge($years,$stmt->fetchAll(PDO::FETCH_COLUMN));}
    return array_unique(array_map('intval',$years));
}
function sena_assert_code_available(PDO $pdo,string $code,int $year,int $registryId=0,int $inspectionId=0): void {
    if(sena_link_code($code)==='')return;
    // Serialize manual saves across both forms, including simultaneous create requests.
    $lock='sena-code-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,24);
    static $locked=[];
    $connection=spl_object_id($pdo);
    if(!isset($locked[$connection])){
        $stmt=$pdo->prepare('SELECT GET_LOCK(?,10)');$stmt->execute([$lock]);
        if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('มีการบันทึกรหัสอยู่ กรุณาลองอีกครั้ง');
        $locked[$connection]=true;
        register_shutdown_function(static function()use($pdo,$lock){try{$stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lock]);}catch(Throwable $e){}});
    }
    foreach(sena_code_affected_years($pdo,$year,$registryId) as $affectedYear){
        $matches=sena_code_conflicts($pdo,$code,$affectedYear,$registryId,$inspectionId);
        if($matches)throw new RuntimeException('รหัสครุภัณฑ์ซ้ำกับ “'.$matches[0]['item_name'].'” ในบัญชีตรวจปี '.$affectedYear.' กรุณาตรวจสอบและแก้รหัสก่อนบันทึก');
    }
}
function sena_code_check_groups(PDO $pdo,int $year): array {
    $stmt=$pdo->prepare('SELECT e.id,i.id AS inspection_id,i.item_number,i.item_name AS equipment_name,
        i.asset_code AS equipment_code,e.category,
        COALESCE(NULLIF(TRIM(i.location),\'\'),e.location) AS location
        FROM inspection_items i JOIN equipment_registry e ON e.id=i.registry_id
        WHERE i.fiscal_year=? ORDER BY i.item_number,i.id');
    $stmt->execute([$year]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$counts=[];
    foreach($rows as $row){$code=sena_link_code($row['equipment_code']);if($code!=='')$counts[$code]=($counts[$code]??0)+1;}
    return ['all'=>$rows,
        'duplicate_code'=>array_values(array_filter($rows,fn($r)=>($counts[sena_link_code($r['equipment_code'])]??0)>1)),
        'without_code'=>array_values(array_filter($rows,fn($r)=>sena_link_code($r['equipment_code'])===''))];
}
