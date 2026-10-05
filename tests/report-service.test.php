<?php
require __DIR__.'/../includes/report_service.php';
function check($actual,$expected){if($actual!==$expected)throw new RuntimeException('Report assertion failed');}
$q=sena_report_query([]);check($q['table'],'equipment_registry');check($q['params'],[]);
check(str_contains($q['sql'],'LIMIT'),false);
$q=sena_report_query(['source'=>'inspection','year'=>2570,'type'=>'damaged','loc'=>'Room','cat'=>'Desk']);
check($q['params'],[2570,'Desk','Room']);check(str_contains($q['sql'],'status_damaged=1'),true);
$q=sena_report_query(['source'=>'registry','year'=>2570,'status'=>'usable','search'=>'TEST','type'=>'dispose']);
check($q['params'],['%TEST%','%TEST%','%TEST%','active']);check(str_contains($q['sql'],'fiscal_year=?'),false);
check(str_contains($q['sql'],"status IN ('disposed','unused')"),true);
check(sena_report_status(['status_usable'=>1,'status_degraded'=>1],'inspection'),'ใช้ได้ / เสื่อมคุณภาพ');
check(sena_report_status([],'inspection'),'รอตรวจนับ');
check(sena_report_status(['status'=>'unverified'],'registry'),'ยังไม่ยืนยัน');
check(sena_csv_cell('=HYPERLINK("x")'),"'=HYPERLINK(\"x\")");
echo "Report filters, source selection and statuses passed\n";
