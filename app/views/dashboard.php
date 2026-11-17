<section class="stats-grid">
  <article class="stat blue"><span>همه کدها</span><strong><?=fa_number((int)$stats['total'])?></strong><small><?=fa_number((int)$stats['active_count'])?> کد فعال</small></article>
  <article class="stat violet"><span>کد ثابت</span><strong><?=fa_number((int)$stats['static_count'])?></strong><small>محتوای غیرقابل‌تغییر</small></article>
  <article class="stat teal"><span>کد پویا</span><strong><?=fa_number((int)$stats['dynamic_count'])?></strong><small>مقصد قابل‌ویرایش</small></article>
  <article class="stat amber"><span>کل اسکن‌ها</span><strong><?=fa_number((int)$stats['scans'])?></strong><small>شامل اسکن‌های تکراری</small></article>
</section>
<section class="dashboard-grid">
  <article class="panel chart-panel">
    <div class="panel-head"><div><h2>روند اسکن</h2><p>هفت روز گذشته</p></div><a href="<?=e(url('/analytics'))?>">جزئیات</a></div>
    <?php $max=max([1,...array_map(fn($x)=>(int)$x['count'],$chart)]); ?>
    <div class="bars"><?php if(!$chart):?><div class="empty mini">هنوز اسکنی ثبت نشده است.</div><?php else:foreach($chart as $row):?><div class="bar-item"><span style="height:<?=max(8,(int)$row['count']/$max*100)?>%" title="<?=e($row['count'])?> اسکن"></span><small><?=fa_number(substr($row['day'],5))?></small></div><?php endforeach;endif?></div>
  </article>
  <article class="panel activity">
    <div class="panel-head"><div><h2>فعالیت اخیر</h2><p>آخرین اسکن‌ها</p></div></div>
    <?php if(!$recentScans):?><div class="empty mini">فعالیتی ثبت نشده است.</div><?php else:foreach($recentScans as $scan):?><div class="activity-row"><span class="dot"></span><div><b><?=e($scan['name'])?></b><small><?=e($scan['device_category'])?> · <?=e($scan['browser'])?></small></div><time><?=fa_date($scan['scanned_at'])?></time></div><?php endforeach;endif?>
  </article>
</section>
<section class="panel"><div class="panel-head"><div><h2>کدهای تازه</h2><p>آخرین موارد ساخته‌شده</p></div><a href="<?=e(url('/qr-codes'))?>">مشاهده همه</a></div><?=renderQrTable($recent)?></section>

