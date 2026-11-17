<?php

declare(strict_types=1);

use App\Auth;
use App\Csrf;
use App\Database;
use App\DeviceParser;
use App\LogoStorage;
use App\QrDesign;
use App\QrService;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
class_alias(\App\Auth::class, 'Auth');
class_alias(\App\Csrf::class, 'Csrf');
if (is_file(dirname(__DIR__) . '/.env')) Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Tehran'));
ini_set('display_errors', env('APP_DEBUG', false) ? '1' : '0');
session_name('qr_manager_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool) env('SESSION_SECURE', false),'httponly'=>true,'samesite'=>'Lax']);
session_start();

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = null;
try { $pdo = Database::connection(); } catch (Throwable $e) { render('error', ['title'=>'خطای اتصال','message'=>$e->getMessage()], false); exit; }

if ($path === '/r' || str_starts_with($path, '/r/')) {
    $token = basename($path);
    $stmt = $pdo->prepare("SELECT * FROM qr_codes WHERE token = ? AND type = 'dynamic' AND archived_at IS NULL LIMIT 1");
    $stmt->execute([$token]); $qr = $stmt->fetch();
    if (!$qr) { http_response_code(404); render('public-status', ['title'=>'کد پیدا نشد','message'=>'این نشانی QR وجود ندارد یا حذف شده است.'], false); exit; }
    if (!(bool) $qr['is_active']) { http_response_code(410); render('public-status', ['title'=>'کد غیرفعال است','message'=>'این کد QR در حال حاضر فعال نیست.'], false); exit; }
    if (!QrService::validateHttpUrl($qr['payload'])) { http_response_code(500); render('public-status', ['title'=>'مقصد نامعتبر','message'=>'مقصد این کد نیاز به اصلاح توسط مدیر دارد.'], false); exit; }
    [$device,$browser,$os] = DeviceParser::parse(substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500));
    $pdo->prepare('INSERT INTO qr_scans (qr_code_id, referrer, user_agent, device_category, browser, operating_system) VALUES (?, ?, ?, ?, ?, ?)')->execute([$qr['id'], substr((string)($_SERVER['HTTP_REFERER'] ?? ''),0,500) ?: null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500) ?: null, $device,$browser,$os]);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Location: ' . $qr['payload'], true, 302); exit;
}

if ($path === '/setup') {
    if (Auth::hasAdmins()) redirect('/login');
    if ($method === 'POST') {
        Csrf::verify(); $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['password_confirmation']??'');
        $errors=[]; if (mb_strlen($name)<2) $errors[]='نام مدیر حداقل ۲ حرف باشد.'; if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='ایمیل معتبر وارد کنید.'; if (strlen($password)<10) $errors[]='رمز عبور حداقل ۱۰ نویسه باشد.'; if ($password!==$confirm) $errors[]='تکرار رمز عبور یکسان نیست.';
        if (!$errors) { $pdo->prepare('INSERT INTO admins (name,email,password_hash) VALUES (?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); flash('success','حساب مدیر ساخته شد؛ اکنون وارد شوید.'); redirect('/login'); }
        $_SESSION['_old']=['name'=>$name,'email'=>$email]; render('setup',['title'=>'راه‌اندازی اولیه','errors'=>$errors],false); exit;
    }
    render('setup',['title'=>'راه‌اندازی اولیه','errors'=>[]],false); exit;
}

if ($path === '/login') {
    if (!Auth::hasAdmins()) redirect('/setup');
    if (Auth::check()) redirect('/dashboard');
    if ($method === 'POST') { Csrf::verify(); if (Auth::attempt((string)($_POST['email']??''),(string)($_POST['password']??''))) { flash('success','خوش آمدید.'); redirect('/dashboard'); } flash('error','ایمیل یا رمز عبور نادرست است.'); }
    render('login',['title'=>'ورود مدیر'],false); exit;
}

if ($path === '/logout' && $method === 'POST') { Csrf::verify(); Auth::logout(); header('Location: '.url('/login'), true, 303); exit; }
Auth::require();

if ($path === '/' || $path === '/dashboard') {
    $stats=$pdo->query("SELECT COUNT(*) total, SUM(type='static') static_count, SUM(type='dynamic') dynamic_count, SUM(is_active=1) active_count FROM qr_codes WHERE archived_at IS NULL")->fetch();
    $stats['scans']=(int)$pdo->query('SELECT COUNT(*) FROM qr_scans')->fetchColumn();
    $recent=$pdo->query("SELECT q.*, (SELECT COUNT(*) FROM qr_scans s WHERE s.qr_code_id=q.id) scans FROM qr_codes q WHERE archived_at IS NULL ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recentScans=$pdo->query('SELECT s.*,q.name FROM qr_scans s JOIN qr_codes q ON q.id=s.qr_code_id ORDER BY s.scanned_at DESC LIMIT 6')->fetchAll();
    $chart=$pdo->query("SELECT DATE(scanned_at) day, COUNT(*) count FROM qr_scans WHERE scanned_at >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(scanned_at) ORDER BY day")->fetchAll();
    render('dashboard',compact('stats','recent','recentScans','chart')+['title'=>'داشبورد']); exit;
}

if ($path === '/qr-codes') {
    $page=max(1,(int)($_GET['page']??1)); $per=10; $q=trim((string)($_GET['q']??'')); $type=(string)($_GET['type']??''); $status=(string)($_GET['status']??'');
    $where=['q.archived_at IS NULL'];$params=[];
    if ($q!=='') { $where[]='(q.name LIKE ? OR q.payload LIKE ?)';$params[]="%$q%";$params[]="%$q%"; }
    if (in_array($type,['static','dynamic'],true)) {$where[]='q.type=?';$params[]=$type;}
    if (in_array($status,['active','inactive'],true)) {$where[]='q.is_active=?';$params[]=$status==='active'?1:0;}
    $sql=implode(' AND ',$where);$stmt=$pdo->prepare("SELECT COUNT(*) FROM qr_codes q WHERE $sql");$stmt->execute($params);$total=(int)$stmt->fetchColumn();$pages=max(1,(int)ceil($total/$per));$page=min($page,$pages);$offset=($page-1)*$per;
    $stmt=$pdo->prepare("SELECT q.*,(SELECT COUNT(*) FROM qr_scans s WHERE s.qr_code_id=q.id) scans FROM qr_codes q WHERE $sql ORDER BY q.created_at DESC LIMIT $per OFFSET $offset");$stmt->execute($params);$items=$stmt->fetchAll();
    render('qr-list',compact('items','total','page','pages','q','type','status')+['title'=>'کدهای QR']); exit;
}

if ($path === '/qr-codes/design-preview' && $method === 'POST') {
    Csrf::verify();
    $design = QrDesign::fromInput($_POST);
    $existing = null;
    if (!empty($_POST['qr_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM qr_codes WHERE id = ? AND archived_at IS NULL');
        $stmt->execute([(int) $_POST['qr_id']]);
        $existing = $stmt->fetch() ?: null;
    }
    $logo = LogoStorage::temporary($_FILES['logo'] ?? []) ?: LogoStorage::absolute($existing['logo_path'] ?? null);
    if ($logo && !in_array($design['error_correction'], ['Q','H'], true)) $design['error_correction'] = 'H';
    $type = $existing['type'] ?? (string) ($_POST['type'] ?? 'static');
    $payload = trim((string) ($_POST['payload'] ?? ''));
    if ($type === 'dynamic') $payload = $existing ? QrService::payload($existing) : url('/r/PREVIEW');
    if ($payload === '') $payload = 'https://qr.example.com';
    $format = ($_POST['preview_format'] ?? 'svg') === 'png' ? 'png' : 'svg';
    try {
        $out = QrService::render($payload, $format, design:$design, logoPath:$logo);
        $safety = QrDesign::safety($design, (bool) $logo);
        header('Content-Type: ' . $out['mime']); header('X-Content-Type-Options: nosniff');
        header('X-QR-Safety: ' . $safety['status']); header('X-QR-Safety-Score: ' . $safety['score']);
        echo $out['content'];
    } catch (Throwable $e) {
        error_log($e->getMessage()); http_response_code(422); echo 'پیش‌نمایش با این تنظیمات ساخته نشد.';
    }
    exit;
}

if ($path === '/qr-codes/create') {
    if ($method === 'POST') { Csrf::verify(); [$data,$errors]=validateQr($_POST); $design=QrDesign::fromInput($_POST); $logoPath=saveLogo(null,$errors); if($logoPath&&!in_array($design['error_correction'],['Q','H'],true))$design['error_correction']='H'; if (!$errors) { $token=$data['type']==='dynamic'?uniqueToken($pdo):null; $pdo->prepare('INSERT INTO qr_codes (admin_id,name,description,type,content_type,payload,token,foreground_color,background_color,design_json,logo_path,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)')->execute([$_SESSION['admin_id'],$data['name'],$data['description'],$data['type'],$data['content_type'],$data['payload'],$token,$design['foreground_color'],$design['background_color'],json_encode($design,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$logoPath]); flash('success','کد QR با طراحی انتخابی ساخته شد.'); redirect('/qr-codes'); } $_SESSION['_old']=$_POST; render('qr-form',['title'=>'ساخت کد QR','errors'=>$errors,'item'=>null,'design'=>$design]); exit; }
    render('qr-form',['title'=>'ساخت کد QR','errors'=>[],'item'=>null]); exit;
}

if (preg_match('#^/qr-codes/(\d+)/(edit|toggle|delete|preview|download)$#',$path,$m)) {
    $id=(int)$m[1];$action=$m[2];$stmt=$pdo->prepare('SELECT * FROM qr_codes WHERE id=? AND archived_at IS NULL');$stmt->execute([$id]);$item=$stmt->fetch();if(!$item){http_response_code(404);render('error',['title'=>'یافت نشد','message'=>'کد موردنظر پیدا نشد.']);exit;}
    if ($action==='edit') { if($method==='POST'){Csrf::verify();[$data,$errors]=validateQr($_POST,$item);$design=QrDesign::fromInput($_POST);$oldLogo=$item['logo_path']??null;$logoPath=saveLogo($item,$errors);if($logoPath&&!in_array($design['error_correction'],['Q','H'],true))$design['error_correction']='H';if(!$errors){$payload=$item['type']==='static'?$item['payload']:$data['payload'];$pdo->prepare('UPDATE qr_codes SET name=?,description=?,payload=?,foreground_color=?,background_color=?,design_json=?,logo_path=? WHERE id=?')->execute([$data['name'],$data['description'],$payload,$design['foreground_color'],$design['background_color'],json_encode($design,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$logoPath,$id]);if($oldLogo&&$oldLogo!==$logoPath)LogoStorage::delete($oldLogo);flash('success','محتوا و طراحی QR ذخیره شد.');redirect('/qr-codes');}render('qr-form',['title'=>'ویرایش کد QR','errors'=>$errors,'item'=>$item,'design'=>$design]);exit;}render('qr-form',['title'=>'ویرایش کد QR','errors'=>[],'item'=>$item]);exit; }
    if ($action==='toggle' && $method==='POST'){Csrf::verify();$pdo->prepare('UPDATE qr_codes SET is_active=NOT is_active WHERE id=?')->execute([$id]);flash('success','وضعیت کد تغییر کرد.');redirect('/qr-codes');}
    if ($action==='delete' && $method==='POST'){Csrf::verify();$pdo->prepare('UPDATE qr_codes SET archived_at=NOW(),is_active=0 WHERE id=?')->execute([$id]);flash('success','کد بایگانی شد.');redirect('/qr-codes');}
    if (in_array($action,['preview','download'],true)){ $format=in_array($_GET['format']??'png',['png','svg'],true)?$_GET['format']:'png';$design=QrDesign::fromRecord($item);$out=QrService::render(QrService::payload($item),$format,$item['foreground_color'],$item['background_color'],$design,LogoStorage::absolute($item['logo_path']??null));header('Content-Type: '.$out['mime']);header('X-Content-Type-Options: nosniff');if($action==='download')header('Content-Disposition: attachment; filename="qr-'.$id.'.'.$out['extension'].'"');echo $out['content'];exit; }
}

if ($path === '/analytics') {
    $from=(string)($_GET['from']??date('Y-m-d',strtotime('-29 days')));$to=(string)($_GET['to']??date('Y-m-d'));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-d',strtotime('-29 days'));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d');
    $stmt=$pdo->prepare("SELECT DATE(scanned_at) day,COUNT(*) count FROM qr_scans WHERE scanned_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY) GROUP BY DATE(scanned_at) ORDER BY day");$stmt->execute([$from,$to]);$daily=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT q.id,q.name,COUNT(s.id) scans FROM qr_codes q LEFT JOIN qr_scans s ON s.qr_code_id=q.id AND s.scanned_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY) WHERE q.type='dynamic' AND q.archived_at IS NULL GROUP BY q.id ORDER BY scans DESC");$stmt->execute([$from,$to]);$byQr=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT s.*,q.name FROM qr_scans s JOIN qr_codes q ON q.id=s.qr_code_id WHERE s.scanned_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY) ORDER BY s.scanned_at DESC LIMIT 50");$stmt->execute([$from,$to]);$scans=$stmt->fetchAll();
    render('analytics',compact('from','to','daily','byQr','scans')+['title'=>'آمار اسکن']);exit;
}

if ($path === '/settings') { render('settings',['title'=>'تنظیمات']); exit; }
http_response_code(404);render('error',['title'=>'صفحه پیدا نشد','message'=>'نشانی واردشده وجود ندارد.']);

function validateQr(array $input, ?array $existing=null): array {
    $data=['name'=>trim((string)($input['name']??'')),'description'=>trim((string)($input['description']??'')),'type'=>$existing['type']??(string)($input['type']??'static'),'content_type'=>$existing['content_type']??(string)($input['content_type']??'url'),'payload'=>trim((string)($input['payload']??'')),'foreground_color'=>(string)($input['foreground_color']??'#111827'),'background_color'=>(string)($input['background_color']??'#ffffff')];$errors=[];
    if(mb_strlen($data['name'])<2||mb_strlen($data['name'])>150)$errors[]='نام باید بین ۲ تا ۱۵۰ نویسه باشد.';if(mb_strlen($data['description'])>500)$errors[]='توضیحات حداکثر ۵۰۰ نویسه است.';if(!in_array($data['type'],['static','dynamic'],true))$errors[]='نوع کد نامعتبر است.';if(!in_array($data['content_type'],['url','text','email'],true))$errors[]='نوع محتوا نامعتبر است.';if($data['payload']===''||mb_strlen($data['payload'])>2048)$errors[]='محتوا الزامی و حداکثر ۲۰۴۸ نویسه است.';
    if($data['type']==='dynamic'&&!QrService::validateHttpUrl($data['payload']))$errors[]='مقصد کد پویا باید نشانی معتبر HTTP یا HTTPS باشد.';if($data['type']==='static'&&$data['content_type']==='url'&&!QrService::validateHttpUrl($data['payload']))$errors[]='نشانی وب معتبر HTTP یا HTTPS وارد کنید.';if($data['type']==='static'&&$data['content_type']==='email'&&!filter_var($data['payload'],FILTER_VALIDATE_EMAIL))$errors[]='ایمیل معتبر وارد کنید.';if(!QrService::validateColor($data['foreground_color'])||!QrService::validateColor($data['background_color']))$errors[]='رنگ انتخاب‌شده معتبر نیست.';return[$data,$errors];
}
function uniqueToken(PDO $pdo): string { do{$token=rtrim(strtr(base64_encode(random_bytes(9)),'+/','-_'),'=');$s=$pdo->prepare('SELECT COUNT(*) FROM qr_codes WHERE token=?');$s->execute([$token]);}while((int)$s->fetchColumn()>0);return $token; }
function saveLogo(?array $existing, array &$errors): ?string {
    $current=$existing['logo_path']??null;
    if(isset($_POST['delete_logo'])) return null;
    if(($_FILES['logo']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $current;
    try { return LogoStorage::store($_FILES['logo']); } catch(Throwable $e) { $errors[]=$e->getMessage(); return $current; }
}
function render(string $view,array $data=[],bool $layout=true): void { extract($data,EXTR_SKIP);$viewFile=dirname(__DIR__).'/app/views/'.$view.'.php';if(!$layout){require $viewFile;$_SESSION['_old']=[];return;}ob_start();require $viewFile;$content=ob_get_clean();$flashes=$_SESSION['_flash']??[];unset($_SESSION['_flash']);require dirname(__DIR__).'/app/views/layout.php';$_SESSION['_old']=[]; }
