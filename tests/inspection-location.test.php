<?php
// Run against local MySQL: temporary tables shadow the real tables and vanish on disconnect.
require __DIR__.'/../config/database.php';
require __DIR__.'/../includes/report_service.php';
if(!$pdo)throw new RuntimeException('Local MySQL is required');
function expect_location($actual,$expected): void {
    if($actual!==$expected)throw new RuntimeException('Location mismatch: '.json_encode([$actual,$expected],JSON_UNESCAPED_UNICODE));
}
$pdo->exec('CREATE TEMPORARY TABLE equipment_registry AS SELECT * FROM equipment_registry WHERE 0');
$pdo->exec('CREATE TEMPORARY TABLE inspection_items AS SELECT * FROM inspection_items WHERE 0');
$registry=$pdo->prepare('INSERT INTO equipment_registry(id,equipment_code,equipment_name,location) VALUES(?,?,?,?)');
foreach([[1,'A-1/1','Camera','Library'],[2,'B-1','Desk','Ban Phaen'],[3,'B-1','Desk','Ban Phaen'],
         [4,'C-1','Chair','Room 1'],[5,'C-1','Chair','Room 2'],[6,'D-1','Fan','Room 3'],[7,'D-1','Fan',''],
         [8,'','Unnamed','Room 4'],[9,'E-1','No location',null]] as $r)$registry->execute($r);
$annual=$pdo->prepare('INSERT INTO inspection_items(id,item_number,item_name,asset_code,location,fiscal_year,status_usable,status_damaged,status_degraded,status_lost,status_unused) VALUES(?,?,?,?,?,?,1,0,0,0,0)');
foreach([[1,1,'Camera'," A-1/1\n",null,2568],[2,2,'Desk','B-1','',2568],[3,3,'Chair','C-1','',2568],
         [4,4,'Fan','D-1','',2568],[5,5,'Unknown','X-1','',2568],[6,6,'Blank code','',null,2568],
         [7,7,'Manual','A-1/1','Historical location',2568],[8,1,'Different year','A-1/1','Annual 2570',2570],
         [9,9,'No location','E-1','',2568]] as $r)$annual->execute($r);
$before=$pdo->query('SELECT * FROM inspection_items ORDER BY id')->fetchAll();
$q=sena_report_query(['source'=>'inspection','year'=>2568]);$stmt=$pdo->prepare($q['sql']);$stmt->execute($q['params']);$rows=$stmt->fetchAll();
expect_location(count($rows),8);
$byNumber=array_column($rows,null,'item_number');
foreach([1=>['Library','registry'],2=>['Ban Phaen','registry'],3=>[null,'conflict'],4=>[null,'conflict'],
         5=>[null,'missing'],6=>[null,'missing'],7=>['Historical location','annual'],9=>[null,'missing']] as $n=>$expected)
    expect_location([$byNumber[$n]['location'],$byNumber[$n]['location_origin']],$expected);
$q=sena_report_query(['source'=>'inspection','year'=>2568,'loc'=>'Ban Phaen']);
$stmt=$pdo->prepare($q['count_sql']);$stmt->execute($q['params']);expect_location((int)$stmt->fetchColumn(),1);
$stmt=$pdo->prepare($q['sql']);$stmt->execute($q['params']);expect_location($stmt->fetch()['item_number'],2);
$source=sena_inspection_source_sql();$stmt=$pdo->prepare("SELECT DISTINCT effective_location FROM $source WHERE fiscal_year=? AND effective_location IS NOT NULL ORDER BY effective_location");
$stmt->execute([2568]);expect_location($stmt->fetchAll(PDO::FETCH_COLUMN),['Ban Phaen','Historical location','Library']);
expect_location($pdo->query('SELECT * FROM inspection_items ORDER BY id')->fetchAll(),$before);
echo "Annual location, normalized codes, duplicates, conflicts, blank codes, year filters, reports and unchanged observations passed\n";
