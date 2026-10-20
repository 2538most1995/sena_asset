<?php
require __DIR__.'/../config/database.php';
require __DIR__.'/mysql_fixture.php';
require __DIR__.'/../includes/equipment_code_service.php';
sena_test_database($pdo,$host);
function codeCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
function codeAsset(PDO $pdo,string $name,string $code): int {$stmt=$pdo->prepare('INSERT INTO equipment_registry(equipment_name,equipment_code) VALUES(?,?)');$stmt->execute([$name,$code]);return (int)$pdo->lastInsertId();}
function codeAccount(PDO $pdo,int $asset,int $year,string $code): int {$stmt=$pdo->prepare('INSERT INTO inspection_items(registry_id,fiscal_year,item_number,item_name,asset_code,location) VALUES(?,?,1,?,?,?)');$stmt->execute([$asset,$year,'Account '.$asset,$code,'Actual location']);return (int)$pdo->lastInsertId();}
$a=codeAsset($pdo,'Asset A','ABC-1');$ia=codeAccount($pdo,$a,2568,'ABC-1');
$b=codeAsset($pdo,'Asset B','A B C-1');$ib=codeAccount($pdo,$b,2568,'A B C-1');
$archive=codeAsset($pdo,'Archived only','ABC-1');
$c=codeAsset($pdo,'No code','-');codeAccount($pdo,$c,2568,'-');
$d=codeAsset($pdo,'Next year','DIFFERENT');codeAccount($pdo,$d,2569,'DIFFERENT');
codeAccount($pdo,$a,2569,'ABC-1');
codeCheck(sena_default_inspection_year($pdo)===2569,'Newest populated account');
codeCheck(count(sena_code_conflicts($pdo,' A B C-1 ',2568,$a,$ia))===1,'Whitespace matching excluding own ID');
codeCheck(!sena_code_conflicts($pdo,'ABC-1',2569,$a),'Same asset across years allowed');
codeCheck(!sena_code_conflicts($pdo,'-',2568),'Placeholder is not a duplicate code');
codeCheck(!sena_code_conflicts($pdo,'NEW',2568),'Unique code');
$g=sena_code_check_groups($pdo,2568);
codeCheck(count($g['all'])===3 && count($g['duplicate_code'])===2 && count($g['without_code'])===1,'Only linked selected-year records');
codeCheck(!in_array($archive,array_column($g['all'],'id')),'Archive excluded');
codeCheck(count(sena_code_check_groups($pdo,2570)['all'])===0,'Empty explicit year stays empty');
$blocked=false;try{sena_assert_code_available($pdo,'ABC-1',2568,$a,$ia);}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'ซ้ำ');}
codeCheck($blocked,'Server blocks duplicate');
sena_assert_code_available($pdo,'SAFE',2568,$a,$ia);
$blocked=false;try{sena_assert_code_available($pdo,'DIFFERENT',2568,$a,$ia);}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'2569');}
codeCheck($blocked,'Cross-year master synchronization conflicts checked');
codeCheck((int)$pdo->query('SELECT COUNT(*) FROM equipment_registry')->fetchColumn()===5,'Check never deletes duplicates');
echo "Equipment code checks passed\n";
