<section class="panel">
  <form class="filters date-filter" method="get"><label>از تاریخ<input type="date" name="from" value="<?=e($from)?>"></label><label>تا تاریخ<input type="date" name="to" value="<?=e($to)?>"></label><button class="secondary">به‌روزرسانی گزارش</button></form>
  <div class="analytics-summary"><div><strong><?=fa_number(array_sum(array_column($daily,'count')))?></strong><span>اسکن در بازه انتخابی</span></div><p>این اعداد تعداد رویدادهای اسکن هستند، نه افراد یکتا. اسکن تکراری، ربات‌ها و پیش‌نمایش پیام‌رسان‌ها ممکن است در آمار باشند.</p></div>
</section>
<section class="dashboard-grid analytics-grid">
  <article class="panel"><div class="panel-head"><div><h2>اسکن روزانه</h2><p>توزیع رویدادها در بازه</p></div></div><?php $max=max([1,...array_map(fn($x)=>(int)$x['count'],$daily)]);?><div class="bars tall"><?php if(!$daily):?><div class="empty mini">داده‌ای در این بازه نیست.</div><?php else:foreach($daily as $row):?><div class="bar-item"><b><?=fa_number($row['count'])?></b><span style="height:<?=max(8,(int)$row['count']/$max*100)?>%"></span><small><?=fa_number(substr($row['day'],5))?></small></div><?php endforeach;endif?></div></article>
  <article class="panel"><div class="panel-head"><div><h2>عملکرد کدها</h2><p>بر اساس تعداد اسکن</p></div></div><?php if(!$byQr):?><div class="empty mini">کد پویایی وجود ندارد.</div><?php else:foreach($byQr as $row):?><div class="rank-row"><span><?=e($row['name'])?></span><b><?=fa_number($row['scans'])?></b></div><?php endforeach;endif?></article>
</section>
<section class="panel">
  <div class="panel-head"><div><h2>تاریخچه اخیر</h2><p>حداکثر ۵۰ رویداد آخر در بازه</p></div></div>
  <div class="table-wrap"><table><thead><tr><th>کد</th><th>زمان</th><th>دستگاه</th><th>مرورگر</th><th>سیستم‌عامل</th><th>ارجاع‌دهنده</th></tr></thead><tbody><?php if(!$scans):?><tr><td colspan="6" class="empty-cell">اسکنی ثبت نشده است.</td></tr><?php else:foreach($scans as $s):?><tr><td><b><?=e($s['name'])?></b></td><td><?=fa_date($s['scanned_at'])?></td><td><?=e($s['device_category'])?></td><td><?=e($s['browser'])?></td><td><?=e($s['operating_system'])?></td><td class="truncate"><?=e($s['referrer']?:'—')?></td></tr><?php endforeach;endif?></tbody></table></div>
</section>
