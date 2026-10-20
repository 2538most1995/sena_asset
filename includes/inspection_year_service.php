<?php
require_once __DIR__.'/asset_link_service.php';

function sena_fiscal_year($value): int {
    $year=filter_var($value,FILTER_VALIDATE_INT);
    if($year===false||$year<2500||$year>2700)throw new InvalidArgumentException('กรุณาระบุปีงบประมาณ พ.ศ. 2500–2700');
    return (int)$year;
}
function sena_lock_inspection_years(PDO $pdo,array $years,callable $action) {
    $years=array_values(array_unique(array_map('sena_fiscal_year',$years)));sort($years);
    $run=function(int $index)use(&$run,$pdo,$years,$action){
        return isset($years[$index])?sena_year_lock($pdo,$years[$index],fn()=>$run($index+1)):$action();
    };
    return $run(0);
}
function sena_copy_inspection_year(PDO $pdo,int $source,int $target,bool $reset,bool $replace,string $inspector=''): array {
    sena_fiscal_year($source);sena_fiscal_year($target);
    if($source===$target)throw new InvalidArgumentException('ปีต้นทางและปีปลายทางต้องไม่เป็นปีเดียวกัน');
    if(mb_strlen($inspector)>100)throw new InvalidArgumentException('ชื่อผู้ตรวจต้องไม่เกิน 100 ตัวอักษร');
    sena_link_inspection_year($pdo,$source);
    return sena_lock_inspection_years($pdo,[$source,$target],function()use($pdo,$source,$target,$reset,$replace,$inspector){
        $stmt=$pdo->prepare('SELECT * FROM inspection_items WHERE fiscal_year=? ORDER BY item_number,id FOR UPDATE');
        $stmt->execute([$source]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows)throw new RuntimeException('ไม่พบรายการในปีต้นทาง จึงยังไม่เปลี่ยนข้อมูลปีปลายทาง');
        $stmt->execute([$target]);$oldRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if($oldRows&&!$replace)throw new RuntimeException('ปีปลายทางมีบัญชีตรวจแล้ว กรุณาเลือก “แทนที่บัญชีตรวจปีปลายทาง”');
        $backupId=$oldRows?sena_save_backup($pdo):null;
        $oldByRegistry=[];foreach($oldRows as $row)if($row['registry_id'])$oldByRegistry[(int)$row['registry_id']]=$row;
        $fields=['registry_id','item_number','item_name','asset_code','asset_id_code','status_usable','status_damaged','status_degraded','status_lost','status_unused','remarks','fiscal_year','location','inspector','category','price','is_overlap','image_url','source_file','source_sheet','source_row','source_key'];
        $insert=$pdo->prepare('INSERT INTO inspection_items (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');
        $update=$pdo->prepare('UPDATE inspection_items SET '.implode(',',array_map(fn($f)=>"`$f`=?",$fields)).' WHERE id=?');
        $kept=[];
        foreach($rows as $row){
            $row['fiscal_year']=$target;
            if($inspector!=='')$row['inspector']=$inspector;
            if($reset){foreach(['status_usable','status_damaged','status_degraded','status_lost','status_unused','is_overlap'] as $flag)$row[$flag]=0;$row['remarks']='';}
            $row['source_key']='copy:'.$source.':'.$target.':'.$row['registry_id'];
            $values=array_map(fn($field)=>$row[$field]??null,$fields);
            $old=$oldByRegistry[(int)$row['registry_id']]??null;
            if($old){$update->execute([...$values,$old['id']]);$kept[]=(int)$old['id'];}
            else {$insert->execute($values);$kept[]=(int)$pdo->lastInsertId();}
        }
        if($replace){
            $delete=$pdo->prepare('DELETE FROM inspection_items WHERE fiscal_year=? AND id NOT IN ('.implode(',',array_fill(0,count($kept),'?')).')');
            $delete->execute([$target,...$kept]);
        }
        return ['count'=>count($kept),'replaced'=>count($oldRows),'backup_id'=>$backupId];
    });
}
/** Changing a year moves the observation and keeps its asset/photo identity. */
function sena_update_inspection_assignment(PDO $pdo,int $id,int $target,string $inspector): void {
    sena_fiscal_year($target);
    if(mb_strlen($inspector)>100)throw new InvalidArgumentException('ชื่อผู้ตรวจต้องไม่เกิน 100 ตัวอักษร');
    $stmt=$pdo->prepare('SELECT fiscal_year FROM inspection_items WHERE id=?');$stmt->execute([$id]);$oldYear=$stmt->fetchColumn();
    if(!$oldYear)throw new RuntimeException('ไม่พบรายการตรวจ');
    sena_lock_inspection_years($pdo,[(int)$oldYear,$target],function()use($pdo,$id,$target,$inspector,$oldYear){
        $stmt=$pdo->prepare('SELECT * FROM inspection_items WHERE id=? FOR UPDATE');$stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row||(int)$row['fiscal_year']!==(int)$oldYear)throw new RuntimeException('รายการถูกเปลี่ยนแปลง กรุณาเปิดใหม่แล้วลองอีกครั้ง');
        $number=(int)$row['item_number'];
        if((int)$oldYear!==$target){
            $check=$pdo->prepare('SELECT id FROM inspection_items WHERE registry_id=? AND fiscal_year=?');$check->execute([$row['registry_id'],$target]);
            if($check->fetchColumn())throw new RuntimeException('ครุภัณฑ์ชิ้นนี้มีในปีปลายทางแล้ว จึงยังไม่ย้ายหรือเขียนทับรายการเดิม');
            $max=$pdo->prepare('SELECT COALESCE(MAX(item_number),0)+1 FROM inspection_items WHERE fiscal_year=? FOR UPDATE');$max->execute([$target]);$number=(int)$max->fetchColumn();
        }
        $sourceKey=(int)$oldYear!==$target && $row['source_key']!==null?'moved:'.$id.':'.$target:$row['source_key'];
        $save=$pdo->prepare('UPDATE inspection_items SET inspector=?,fiscal_year=?,item_number=?,source_key=? WHERE id=?');$save->execute([$inspector,$target,$number,$sourceKey,$id]);
    });
}
