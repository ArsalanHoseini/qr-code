<?php

declare(strict_types=1);

function env(string $key, mixed $default = null): mixed
{
    $runtime = getenv($key);
    $value = $runtime !== false ? $runtime : ($_ENV[$key] ?? $_SERVER[$key] ?? null);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return match (strtolower((string) $value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) env('APP_URL', 'http://localhost:8080'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function fa_number(int|string $number): string
{
    return strtr((string) $number, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}

function fa_date(?string $date): string
{
    if (!$date) return '—';
    return fa_number((new DateTimeImmutable($date))->setTimezone(new DateTimeZone((string) env('APP_TIMEZONE', 'Asia/Tehran')))->format('Y/m/d H:i'));
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = basename($scriptName) === 'index.php' ? rtrim(dirname($scriptName), '/') : '';
    if ($base && $base !== '/' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
    return '/' . ltrim($path, '/');
}

function renderQrTable(array $items): string
{
    ob_start(); ?>
    <div class="table-wrap"><table><thead><tr><th>نام</th><th>نوع</th><th>محتوا / مقصد</th><th>وضعیت</th><th>اسکن</th><th>ساخته‌شده</th><th>عملیات</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?><tr><td><div class="qr-name"><img src="<?=e(url('/qr-codes/'.$item['id'].'/preview?format=svg'))?>" alt=""><div><b><?=e($item['name'])?></b><small><?=e($item['description'] ?: 'بدون توضیح')?></small></div></div></td><td><span class="badge <?=$item['type']?>"><?=$item['type']==='dynamic'?'پویا':'ثابت'?></span></td><td class="payload" title="<?=e($item['payload'])?>"><?=e($item['payload'])?></td><td><span class="status <?=$item['is_active']?'on':'off'?>"><?=$item['is_active']?'فعال':'غیرفعال'?></span></td><td><?=fa_number((int)($item['scans']??0))?></td><td><?=fa_date($item['created_at'])?></td><td><div class="actions"><a title="ویرایش" href="<?=e(url('/qr-codes/'.$item['id'].'/edit'))?>">✎</a><a title="دانلود PNG" href="<?=e(url('/qr-codes/'.$item['id'].'/download?format=png'))?>">⇩</a><button type="button" title="بیشتر" data-popover>•••</button><div class="popover"><a target="_blank" href="<?=e(url('/qr-codes/'.$item['id'].'/preview?format=svg'))?>">نمایش SVG</a><a href="<?=e(url('/qr-codes/'.$item['id'].'/download?format=svg'))?>">دانلود SVG</a><form method="post" action="<?=e(url('/qr-codes/'.$item['id'].'/toggle'))?>"><?=Csrf::field()?><button><?=$item['is_active']?'غیرفعال کردن':'فعال کردن'?></button></form><form method="post" action="<?=e(url('/qr-codes/'.$item['id'].'/delete'))?>" data-confirm="<?=$item['type']==='dynamic'?'با بایگانی این کد، نشانی چاپ‌شده دیگر کار نخواهد کرد.':'این کد از فهرست فعال بایگانی می‌شود.'?>"><?=Csrf::field()?><button class="danger-text">بایگانی</button></form></div></div></td></tr><?php endforeach ?>
    </tbody></table></div><?php
    return (string) ob_get_clean();
}

