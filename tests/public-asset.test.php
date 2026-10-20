<?php
require __DIR__.'/../config/database.php';
require __DIR__.'/mysql_fixture.php';
require __DIR__.'/../includes/public_asset_service.php';
sena_test_database($pdo, $host);
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$pdo->prepare('INSERT INTO equipment_registry(equipment_name,equipment_code,location,category,asset_type,image_url,source_data,source_notes) VALUES(?,?,?,?,?,?,?,?)')->execute(['Master','DUP','Master place','Sheet category','Heading type','uploads/equipment/test.jpg','PRIVATE SOURCE','PRIVATE NOTE']);
$id = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO inspection_items(registry_id,fiscal_year,item_name,asset_code,location,status_usable,status_degraded) VALUES(?,?,?,?,?,?,?)')->execute([$id,2568,'Annual item','DUP','Annual place',1,1]);
$pdo->prepare('INSERT INTO inspection_items(registry_id,fiscal_year,item_name,asset_code,location,status_damaged) VALUES(?,?,?,?,?,?)')->execute([$id,2569,'Next year','DUP','',1]);
$pdo->prepare('INSERT INTO equipment_registry(equipment_name,equipment_code) VALUES(?,?)')->execute(['Other duplicate','DUP']);
$other = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO equipment_registry(equipment_name,equipment_code) VALUES(?,?)')->execute(['No code',null]);
$noCode = (int)$pdo->lastInsertId();
// Prove that the service can execute in a transaction that rejects writes.
$pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
$a = sena_public_asset($pdo,$id,2568);
check($a['equipment_name']==='Annual item' && $a['location']==='Annual place','Annual context');
check($a['status_label']==='ใช้ได้ / เสื่อมคุณภาพ','Multiple status flags');
check($a['category']==='Sheet category' && $a['asset_type']==='Heading type','Category/type');
check(!isset($a['source_data']) && !isset($a['source_notes']) && !isset($a['inspector']),'Private field exclusion');
check(sena_public_asset($pdo,$id,null)['equipment_name']==='Next year','Latest annual context');
check(sena_public_asset($pdo,$id,2569)['location']==='Master place','Blank annual location fallback');
check(sena_public_asset($pdo,$id,2570)['equipment_name']==='Master','Unlinked year master fallback');
check(sena_public_asset($pdo,$other,2568)['equipment_name']==='Other duplicate','Duplicate code identity');
check(sena_public_asset($pdo,$noCode,2568)['equipment_name']==='No code','No code identity');
check(sena_public_asset($pdo,999999999,null)===null,'Missing record');
$pdo->commit();
foreach(['javascript:alert(1)','https://example.com/a.jpg','uploads/equipment/../secret.png','uploads/equipment/a.svg','uploads/equipment/a.jpg" onerror="alert(1)'] as $unsafe) check(sena_public_asset_image($unsafe)===null,'Unsafe image');
check(sena_public_asset_image('uploads/equipment/test.jpg')==='uploads/equipment/test.jpg','Local image');
check(sena_public_escape('<script>"\'&')==='&lt;script&gt;&quot;&#039;&amp;','Escaped text');
echo "Public asset tests passed\n";
