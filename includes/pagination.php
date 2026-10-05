<?php
/** Shared, bounded pagination. Keep filters and clamp the page before querying. */
function sena_page_sizes(): array { return [10,25,50,100,250,500,1000,2000,5000]; }
function sena_page_limit($value): int {
    $limit=filter_var($value,FILTER_VALIDATE_INT);
    return in_array($limit,sena_page_sizes(),true)?$limit:25;
}
function sena_page_state(int $total,int $limit,$requested): array {
    $pages=max(1,(int)ceil($total/$limit));
    $page=max(1,min($pages,(int)$requested));
    return ['page'=>$page,'pages'=>$pages,'offset'=>($page-1)*$limit];
}
function sena_render_pagination(int $total,int $limit,int $page,array $filters): void {
    $state=sena_page_state($total,$limit,$page);$pages=$state['pages'];$offset=$state['offset'];
    unset($filters['page'],$filters['limit']);
    $escape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    $url=fn($p)=>'?'.http_build_query(array_merge($filters,['page'=>$p,'limit'=>$limit]));
    $link=function($p,$label,$disabled=false,$current=false) use($url,$escape) {
        $attrs=$current?' aria-current="page"':'';
        if($disabled) echo '<span aria-disabled="true" class="sena-page disabled">'.$escape($label).'</span>';
        else echo '<a class="sena-page'.($current?' current':'').'" href="'.$escape($url($p)).'"'.$attrs.'>'.$escape($label).'</a>';
    };
    ?>
    <style>.sena-pagination{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#475569}.sena-pagination form,.sena-pagination nav{display:flex;align-items:center;flex-wrap:wrap;gap:6px}.sena-page{min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;border-radius:10px;background:white;padding:0 10px}.sena-page.current{background:#4338ca;color:white;border-color:#4338ca}.sena-page.disabled{opacity:.4}.sena-pagination select,.sena-pagination input,.sena-pagination button{min-height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:6px;background:white}.sena-pagination input{width:76px}@media(max-width:640px){.sena-pagination{padding:12px}.sena-pagination nav{width:100%;justify-content:center}.sena-pagination form{font-size:14px}.sena-pagination select,.sena-pagination input{font-size:16px}}</style>
    <section class="sena-pagination" aria-label="แบ่งหน้ารายการ">
      <span>แสดง <?=number_format($total?$offset+1:0)?>–<?=number_format(min($total,$offset+$limit))?> จาก <?=number_format($total)?> รายการ</span>
      <form method="get">
        <?php foreach($filters as $key=>$v):if(is_scalar($v)):?><input type="hidden" name="<?=$escape($key)?>" value="<?=$escape($v)?>"><?php endif;endforeach;?>
        <input type="hidden" name="page" value="1">
        <label>ต่อหน้า <select name="limit" onchange="this.form.submit()" aria-label="จำนวนรายการต่อหน้า"><?php foreach(sena_page_sizes() as $size):?><option value="<?=$size?>" <?=$limit===$size?'selected':''?>><?=number_format($size)?></option><?php endforeach;?></select></label>
        <noscript><button>แสดง</button></noscript>
      </form>
      <nav aria-label="เลือกหน้า">
        <?php $link(1,'«',$page===1);$link(max(1,$page-1),'‹',$page===1);
        $numbers=array_unique([1,max(1,$page-1),$page,min($pages,$page+1),$pages]);sort($numbers);$last=0;
        foreach($numbers as $p){if($last && $p>$last+1)echo '<span aria-hidden="true">…</span>';$link($p,(string)$p,false,$p===$page);$last=$p;}
        $link(min($pages,$page+1),'›',$page===$pages);$link($pages,'»',$page===$pages);?>
      </nav>
      <?php if($pages>1):?><form method="get">
        <?php foreach($filters as $key=>$v):if(is_scalar($v)):?><input type="hidden" name="<?=$escape($key)?>" value="<?=$escape($v)?>"><?php endif;endforeach;?>
        <input type="hidden" name="limit" value="<?=$limit?>"><label>ไปหน้า <input name="page" type="number" min="1" max="<?=$pages?>" value="<?=$page?>" required></label><button type="submit">ไป</button>
      </form><?php endif;?>
    </section>
    <?php
}
