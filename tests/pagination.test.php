<?php
require __DIR__.'/../includes/pagination.php';
function check($actual,$expected){if($actual!==$expected)throw new RuntimeException('Pagination assertion failed');}
check(sena_page_limit('5000'),5000);
check(sena_page_limit('1000000'),25);
check(sena_page_limit('10 OR 1=1'),25);
check(sena_page_state(1157,1000,999),['page'=>2,'pages'=>2,'offset'=>1000]);
check(sena_page_state(0,5000,-1),['page'=>1,'pages'=>1,'offset'=>0]);
check(sena_page_state(10000,25,400),['page'=>400,'pages'=>400,'offset'=>9975]);
ob_start();sena_render_pagination(1157,1000,2,['search'=>'a&b','year'=>2568]);$html=ob_get_clean();
check(str_contains($html,'name="search" value="a&amp;b"'),true);
check(str_contains($html,'aria-current="page"'),true);
check(str_contains($html,'value="5000"'),true);
echo "Pagination regressions passed\n";
