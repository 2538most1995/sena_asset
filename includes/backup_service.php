<?php
function sena_backup_directory(PDO $pdo): string {
    $db=$pdo->query('SELECT DATABASE()')->fetchColumn();
    return sys_get_temp_dir().'/sena-asset-backups-'.hash('sha256',__DIR__.':'.$db);
}
function sena_save_backup(PDO $pdo): string {
    $directory=sena_backup_directory($pdo);
    if(is_link($directory)||(!is_dir($directory)&&!mkdir($directory,0700,true)))throw new RuntimeException('ไม่สามารถสร้างที่สำรองข้อมูลได้');
    $data=['format'=>'sena_asset_snapshot_v1','created_at'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok')))->format(DATE_ATOM),'tables'=>[]];
    foreach(['equipment_registry','inspection_items','asset_categories','registry_source_sheets'] as $table)$data['tables'][$table]=$pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $id=bin2hex(random_bytes(16));$path=$directory.'/'.$id.'.json';
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(file_put_contents($path,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('สำรองข้อมูลไม่สำเร็จ');
    chmod($path,0600);return $id;
}
