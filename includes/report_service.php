<?php
require_once __DIR__.'/inspection_location.php';
/** One filter builder for preview, CSV and print; registry and annual inspection stay explicit. */
function sena_report_query(array $input): array {
    $source=($input['source']??'registry')==='inspection'?'inspection':'registry';
    $year=max(2500,min(2700,(int)($input['year']??2568)));
    $where=['1=1'];$params=[];
    if($source==='inspection'){$where[]='fiscal_year=?';$params[]=$year;}
    foreach(['cat'=>'category','loc'=>'location'] as $key=>$column){
        $v=trim((string)($input[$key]??''));
        if($v!==''&&$v!=='ทั้งหมด'){
            $filterColumn=$source==='inspection' && $column==='location'?'effective_location':$column;
            $where[]="$filterColumn=?";$params[]=$v;
        }
    }
    $search=trim((string)($input['search']??''));
    if($search!==''){
        $columns=$source==='registry'?['equipment_name','equipment_code','brand_description']:['item_name','asset_code','asset_id_code'];
        $where[]='('.implode(' OR ',array_map(fn($col)=>"$col LIKE ?",$columns)).')';
        foreach($columns as $col)$params[]='%'.$search.'%';
    }
    $status=trim((string)($input['status']??''));$type=$input['type']??'';
    $statuses=[$status];if(in_array($type,['damaged','degraded','dispose'],true))$statuses[]=$type;
    foreach(array_unique($statuses) as $st){
        if($source==='registry'){
            if($st==='usable')$st='active';
            if($st==='dispose')$where[]="status IN ('disposed','unused')";
            elseif(in_array($st,['active','damaged','degraded','disposed','unused','unverified'],true)){$where[]='status=?';$params[]=$st;}
        }else{
            if($st==='active')$st='usable';
            if($st==='dispose')$where[]='(status_lost=1 OR status_unused=1)';
            elseif(in_array($st,['usable','damaged','degraded','lost','unused'],true))$where[]="status_$st=1";
            elseif($st==='unverified')$where[]='(status_usable=0 AND status_damaged=0 AND status_degraded=0 AND status_lost=0 AND status_unused=0)';
        }
    }
    $table=$source==='registry'?'equipment_registry':'inspection_items';
    $from=$source==='registry'?$table:sena_inspection_source_sql();
    $columns=$source==='registry'
        ? 'id AS item_number,equipment_code AS asset_code,equipment_name AS item_name,category,asset_type,location,status,unit_price AS price,NULL AS fiscal_year,source_sheet,source_row,source_row_end,remarks'
        : "item_number,asset_code,item_name,category,'' AS asset_type,effective_location AS location,location_origin,status_usable,status_damaged,status_degraded,status_lost,status_unused,price,fiscal_year,source_sheet,source_row,NULL AS source_row_end,remarks";
    $order=$source==='registry'?'id':'item_number,id';
    $condition=implode(' AND ',$where);
    return ['source'=>$source,'year'=>$year,'sql'=>"SELECT $columns FROM $from WHERE $condition ORDER BY $order",'count_sql'=>"SELECT COUNT(*) FROM $from WHERE $condition",'params'=>$params,'table'=>$table];
}
function sena_report_status(array $row,string $source): string {
    if($source==='registry')return ['active'=>'ใช้ได้','damaged'=>'ชำรุด','degraded'=>'เสื่อมคุณภาพ','disposed'=>'จำหน่ายแล้ว','unused'=>'ไม่ใช้','unverified'=>'ยังไม่ยืนยัน'][$row['status']??'unverified']??'ยังไม่ยืนยัน';
    $labels=[];foreach(['usable'=>'ใช้ได้','damaged'=>'ชำรุด','degraded'=>'เสื่อมคุณภาพ','lost'=>'สูญไป','unused'=>'ไม่ใช้'] as $key=>$label)if(!empty($row['status_'.$key]))$labels[]=$label;
    return $labels?implode(' / ',$labels):'รอตรวจนับ';
}
function sena_csv_cell($value): string {
    $value=(string)($value??'');return preg_match('/^[\s]*[=+@-]/u',$value)?"'".$value:$value;
}
