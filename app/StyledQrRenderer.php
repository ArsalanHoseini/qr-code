<?php

declare(strict_types=1);

namespace App;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use RuntimeException;

final class StyledQrRenderer
{
    public static function render(string $data, string $format, array $design, ?string $logoPath = null): array
    {
        $design = QrDesign::sanitize($design);
        if ($logoPath) $design['error_correction'] = $design['error_correction'] === 'L' ? 'H' : $design['error_correction'];
        $level = match ($design['error_correction']) {'L'=>ErrorCorrectionLevel::Low,'Q'=>ErrorCorrectionLevel::Quartile,'H'=>ErrorCorrectionLevel::High,default=>ErrorCorrectionLevel::Medium};
        $qrSize = $design['output_size'] - (2 * $design['margin']);
        $qr = new QrCode(data:$data,errorCorrectionLevel:$level,size:$qrSize,margin:$design['margin'],roundBlockSizeMode:RoundBlockSizeMode::Margin);
        $matrix = (new MatrixFactory())->create($qr);
        return $format === 'svg' ? self::svg($matrix,$design,$logoPath) : self::png($matrix,$design,$logoPath);
    }

    private static function svg(object $m, array $d, ?string $logo): array
    {
        $base=$m->getOuterSize();$frame=$d['frame_style']!=='none';$pad=$frame?24:0;$textH=$frame&&$d['frame_text']!==''?42:0;$w=$base+$pad*2;$h=$base+$pad*2+$textH;$off=$pad;
        $defs=self::svgGradients($d,$w,$h);$bg=self::paint('bg',$d['background_mode'],$d['background_color']);$dots=self::paint('dots',$d['color_mode'],$d['foreground_color']);
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><defs>'.$defs.'</defs>';
        if($d['background_mode']!=='transparent')$svg.='<rect width="'.$w.'" height="'.$h.'" fill="'.$bg.'"/>';
        if($frame)$svg.=self::svgFrame($d,$w,$h,$textH);
        $count=$m->getBlockCount();$s=$m->getBlockSize();$start=$off+$m->getMarginLeft();
        for($r=0;$r<$count;$r++)for($c=0;$c<$count;$c++)if($m->getBlockValue($r,$c)===1&&!self::finderCell($r,$c,$count))$svg.=self::svgModule($start+$c*$s,$start+$r*$s,$s,$d['dot_style'],$dots);
        foreach([[0,0],[$count-7,0],[0,$count-7]] as [$c,$r])$svg.=self::svgFinder($start+$c*$s,$start+$r*$s,$s,$d);
        if($logo)$svg.=self::svgLogo($logo,$d,$w,$base,$off);
        $svg.='</svg>';
        return ['content'=>$svg,'mime'=>'image/svg+xml','extension'=>'svg'];
    }

    private static function png(object $m,array $d,?string $logo): array
    {
        $base=$m->getOuterSize();$frame=$d['frame_style']!=='none';$pad=$frame?24:0;$textH=$frame&&$d['frame_text']!==''?42:0;$w=$base+$pad*2;$h=$base+$pad*2+$textH;$im=imagecreatetruecolor($w,$h);imagealphablending($im,true);imagesavealpha($im,true);
        if($d['background_mode']==='transparent'){$clear=imagecolorallocatealpha($im,255,255,255,127);imagefill($im,0,0,$clear);}else self::fillGradient($im,0,0,$w,$h,$d['background_color'],$d['background_end_color'],$d['background_mode'],$d['background_angle']);
        if($frame)self::pngFrame($im,$d,$w,$h,$textH);
        $count=$m->getBlockCount();$s=$m->getBlockSize();$start=$pad+$m->getMarginLeft();
        for($r=0;$r<$count;$r++)for($c=0;$c<$count;$c++)if($m->getBlockValue($r,$c)===1&&!self::finderCell($r,$c,$count)){$color=self::gradientColor($d['foreground_color'],$d['gradient_end_color'],$d['color_mode'],$d['gradient_angle'],($c+.5)/$count,($r+.5)/$count);self::pngModule($im,$start+$c*$s,$start+$r*$s,$s,$d['dot_style'],self::alloc($im,$color));}
        foreach([[0,0],[$count-7,0],[0,$count-7]] as [$c,$r])self::pngFinder($im,$start+$c*$s,$start+$r*$s,$s,$d);
        if($logo)self::pngLogo($im,$logo,$d,$w,$base,$pad);
        ob_start();imagepng($im, null, 6);$out=(string)ob_get_clean();imagedestroy($im);return ['content'=>$out,'mime'=>'image/png','extension'=>'png'];
    }

    private static function finderCell(int $r,int $c,int $n): bool{return ($r<7&&$c<7)||($r<7&&$c>=$n-7)||($r>=$n-7&&$c<7);}
    private static function svgModule(float $x,float $y,float $s,string $style,string $fill):string
    {
        $gap=$style==='square'?0:max(.06*$s,.35);$x+=$gap;$y+=$gap;$s-=2*$gap;
        if($style==='dot')return '<circle cx="'.($x+$s/2).'" cy="'.($y+$s/2).'" r="'.($s/2).'" fill="'.$fill.'"/>';
        if(in_array($style,['rounded','extra_rounded'],true))return '<rect x="'.$x.'" y="'.$y.'" width="'.$s.'" height="'.$s.'" rx="'.($s*($style==='extra_rounded'?.48:.25)).'" fill="'.$fill.'"/>';
        if(str_starts_with($style,'classy')){$r=$s*($style==='classy_rounded'?.45:.28);return '<path d="M'.($x+$r).' '.$y.'H'.($x+$s).'V'.($y+$s-$r).'Q'.($x+$s).' '.($y+$s).' '.($x+$s-$r).' '.($y+$s).'H'.$x.'V'.($y+$r).'Q'.$x.' '.$y.' '.($x+$r).' '.$y.'Z" fill="'.$fill.'"/>';}
        return '<rect x="'.$x.'" y="'.$y.'" width="'.$s.'" height="'.$s.'" fill="'.$fill.'"/>';
    }

    private static function svgFinder(float $x,float $y,float $s,array $d):string
    {
        $size=7*$s;$inner=5*$s;$ix=$x+$s;$iy=$y+$s;$fill=self::paint('corner','solid',$d['corner_color']);$dot=self::paint('cornerDot','solid',$d['corner_dot_color']);$id='m'.str_replace('.','_',((string)($x+$y)));$shape=self::svgShape($x,$y,$size,$d['finder_style'],'white');$hole=self::svgShape($ix,$iy,$inner,$d['finder_style']==='dot'?'dot':'rounded','black');$out='<defs><mask id="'.$id.'">'.$shape.$hole.'</mask></defs><rect x="'.$x.'" y="'.$y.'" width="'.$size.'" height="'.$size.'" fill="'.$fill.'" mask="url(#'.$id.')"/>';
        return $out.self::svgShape($x+2*$s,$y+2*$s,3*$s,$d['finder_dot_style'],$dot);
    }
    private static function svgShape(float $x,float $y,float $size,string $style,string $fill):string
    { if(in_array($style,['dot','inpoint'],true))return '<circle cx="'.($x+$size/2).'" cy="'.($y+$size/2).'" r="'.($size/2).'" fill="'.$fill.'"/>'; $rx=in_array($style,['rounded','classy','outpoint'],true)?$size*.22:0;return '<rect x="'.$x.'" y="'.$y.'" width="'.$size.'" height="'.$size.'" rx="'.$rx.'" fill="'.$fill.'"/>'; }
    private static function svgLogo(string $path,array $d,int $w,int $base,int $off):string
    { $bytes=@file_get_contents($path);if($bytes===false)return'';$info=@getimagesize($path);if(!$info)return'';$size=$base*$d['logo_size']/100;$x=($w-$size)/2;$y=$off+($base-$size)/2;$margin=$d['logo_margin'];$rx=$d['logo_rounded']?12:0;$out='';if($d['logo_backing']||$d['hide_modules']){$backing=$d['logo_backing']?'#fff':($d['background_mode']==='transparent'?'#fff':$d['background_color']);$out.='<rect x="'.($x-$margin).'" y="'.($y-$margin).'" width="'.($size+2*$margin).'" height="'.($size+2*$margin).'" rx="'.$rx.'" fill="'.$backing.'"/>';}$out.='<image x="'.$x.'" y="'.$y.'" width="'.$size.'" height="'.$size.'" preserveAspectRatio="xMidYMid meet" href="data:'.$info['mime'].';base64,'.base64_encode($bytes).'"/>';return$out; }
    private static function svgFrame(array $d,int $w,int $h,int $textH):string
    { $t=$d['frame_thickness'];$r=$d['frame_style']==='simple'?0:$d['frame_radius'];$bottom=$h-$textH;$out='';if($d['frame_outer'])$out.='<rect x="'.($t/2).'" y="'.($t/2).'" width="'.($w-$t).'" height="'.($bottom-$t).'" rx="'.$r.'" fill="none" stroke="'.$d['frame_color'].'" stroke-width="'.$t.'"/>';if($d['frame_style']==='branded'||$d['frame_inner'])$out.='<rect x="'.($t+7).'" y="'.($t+7).'" width="'.($w-2*($t+7)).'" height="'.($bottom-2*($t+7)).'" rx="'.max(0,$r-5).'" fill="none" stroke="'.$d['frame_color'].'" stroke-width="1" opacity=".45"/>';if($d['frame_text']!=='')$out.='<text x="'.($w/2).'" y="'.($h-13).'" text-anchor="middle" font-family="Arial,sans-serif" font-size="18" font-weight="700" fill="'.$d['frame_color'].'">'.htmlspecialchars($d['frame_text'],ENT_QUOTES|ENT_XML1,'UTF-8').'</text>';return$out; }
    private static function svgGradients(array $d,int $w,int $h):string
    { $out='';foreach([['dots',$d['color_mode'],$d['foreground_color'],$d['gradient_end_color'],$d['gradient_angle']],['bg',$d['background_mode'],$d['background_color'],$d['background_end_color'],$d['background_angle']]] as [$id,$mode,$a,$b,$angle]){if(!in_array($mode,['linear','radial'],true))continue;if($mode==='radial')$out.='<radialGradient id="'.$id.'"><stop stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></radialGradient>';else{$rad=deg2rad((float)$angle);$x1=50-50*cos($rad);$y1=50-50*sin($rad);$x2=50+50*cos($rad);$y2=50+50*sin($rad);$out.='<linearGradient id="'.$id.'" x1="'.$x1.'%" y1="'.$y1.'%" x2="'.$x2.'%" y2="'.$y2.'%"><stop stop-color="'.$a.'"/><stop offset="1" stop-color="'.$b.'"/></linearGradient>';}}return$out; }
    private static function paint(string $id,string $mode,string $solid):string{return in_array($mode,['linear','radial'],true)?'url(#'.$id.')':$solid;}

    private static function pngModule($im,float $x,float $y,float $s,string $style,int $color):void
    { $gap=$style==='square'?0:max(.06*$s,.35);$x=(int)round($x+$gap);$y=(int)round($y+$gap);$size=(int)max(1,round($s-2*$gap));if($style==='dot'){imagefilledellipse($im,$x+(int)($size/2),$y+(int)($size/2),$size,$size,$color);return;}if(in_array($style,['rounded','extra_rounded','classy','classy_rounded'],true)){self::roundRect($im,$x,$y,$x+$size,$y+$size,(int)max(1,$size*(in_array($style,['extra_rounded','classy_rounded'],true)?.45:.25)),$color);if(str_starts_with($style,'classy')){imagefilledrectangle($im,$x+(int)($size/2),$y,$x+$size,$y+(int)($size/2),$color);imagefilledrectangle($im,$x,$y+(int)($size/2),$x+(int)($size/2),$y+$size,$color);}return;}imagefilledrectangle($im,$x,$y,$x+$size,$y+$size,$color);}
    private static function pngFinder($im,float $x,float $y,float $s,array $d):void
    { $x=(int)round($x);$y=(int)round($y);$size=(int)round(7*$s);$outer=self::alloc($im,$d['corner_color']);$bg=self::alloc($im,$d['background_mode']==='transparent'?'#ffffff':$d['background_color'], $d['background_mode']==='transparent'?127:0);$dot=self::alloc($im,$d['corner_dot_color']);self::drawShape($im,$x,$y,$size,$d['finder_style'],$outer);self::drawShape($im,$x+(int)$s,$y+(int)$s,(int)round(5*$s),$d['finder_style']==='dot'?'dot':'rounded',$bg);self::drawShape($im,$x+(int)(2*$s),$y+(int)(2*$s),(int)round(3*$s),$d['finder_dot_style'],$dot);}
    private static function drawShape($im,int $x,int $y,int $size,string $style,int $color):void
    { if(in_array($style,['dot','inpoint'],true)){imagefilledellipse($im,$x+(int)($size/2),$y+(int)($size/2),$size,$size,$color);return;}if(in_array($style,['rounded','classy','outpoint'],true)){self::roundRect($im,$x,$y,$x+$size,$y+$size,(int)($size*.2),$color);return;}imagefilledrectangle($im,$x,$y,$x+$size,$y+$size,$color); }
    private static function roundRect($im,int $x1,int $y1,int $x2,int $y2,int $r,int $color):void
    { $r=max(1,min($r,(int)(min($x2-$x1,$y2-$y1)/2)));imagefilledrectangle($im,$x1+$r,$y1,$x2-$r,$y2,$color);imagefilledrectangle($im,$x1,$y1+$r,$x2,$y2-$r,$color);foreach([[$x1+$r,$y1+$r],[$x2-$r,$y1+$r],[$x1+$r,$y2-$r],[$x2-$r,$y2-$r]] as [$cx,$cy])imagefilledellipse($im,$cx,$cy,$r*2,$r*2,$color);}
    private static function pngLogo($im,string $path,array $d,int $w,int $base,int $off):void
    { $info=@getimagesize($path);if(!$info)return;$src=match($info['mime']){'image/png'=>@imagecreatefrompng($path),'image/jpeg'=>@imagecreatefromjpeg($path),'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($path):false,default=>false};if(!$src)return;$size=(int)round($base*$d['logo_size']/100);$x=(int)(($w-$size)/2);$y=(int)($off+($base-$size)/2);$margin=$d['logo_margin'];if($d['logo_backing']||$d['hide_modules']){$backing=$d['logo_backing']?'#ffffff':($d['background_mode']==='transparent'?'#ffffff':$d['background_color']);self::roundRect($im,$x-$margin,$y-$margin,$x+$size+$margin,$y+$size+$margin,$d['logo_rounded']?12:1,self::alloc($im,$backing));}imagecopyresampled($im,$src,$x,$y,0,0,$size,$size,imagesx($src),imagesy($src));imagedestroy($src);}
    private static function pngFrame($im,array $d,int $w,int $h,int $textH):void
    { $color=self::alloc($im,$d['frame_color']);$t=$d['frame_thickness'];$bottom=$h-$textH;if($d['frame_outer'])for($i=0;$i<$t;$i++){if($d['frame_style']==='simple')imagerectangle($im,$i,$i,$w-1-$i,$bottom-1-$i,$color);else self::outlineRoundRect($im,$i,$i,$w-1-$i,$bottom-1-$i,max(1,$d['frame_radius']-$i),$color);}if($d['frame_style']==='branded'||$d['frame_inner'])self::outlineRoundRect($im,$t+7,$t+7,$w-$t-8,$bottom-$t-8,max(1,$d['frame_radius']-5),$color);if($d['frame_text']!==''){$font=5;$tw=imagefontwidth($font)*strlen($d['frame_text']);imagestring($im,$font,(int)(($w-$tw)/2),$h-29,$d['frame_text'],$color);}}
    private static function outlineRoundRect($im,int $x1,int $y1,int $x2,int $y2,int $r,int $color):void
    { imageline($im,$x1+$r,$y1,$x2-$r,$y1,$color);imageline($im,$x1+$r,$y2,$x2-$r,$y2,$color);imageline($im,$x1,$y1+$r,$x1,$y2-$r,$color);imageline($im,$x2,$y1+$r,$x2,$y2-$r,$color);imagearc($im,$x1+$r,$y1+$r,$r*2,$r*2,180,270,$color);imagearc($im,$x2-$r,$y1+$r,$r*2,$r*2,270,360,$color);imagearc($im,$x1+$r,$y2-$r,$r*2,$r*2,90,180,$color);imagearc($im,$x2-$r,$y2-$r,$r*2,$r*2,0,90,$color);}
    private static function fillGradient($im,int $x,int $y,int $w,int $h,string $a,string $b,string $mode,int $angle):void
    { if($mode==='solid'){imagefill($im,$x,$y,self::alloc($im,$a));return;}for($py=0;$py<$h;$py++){for($px=0;$px<$w;$px++){if($mode==='radial')$t=min(1,sqrt((($px-$w/2)/($w/2))**2+(($py-$h/2)/($h/2))**2));else{$rad=deg2rad($angle);$t=.5+(($px-$w/2)*cos($rad)+($py-$h/2)*sin($rad))/max($w,$h);$t=max(0,min(1,$t));}imagesetpixel($im,$x+$px,$y+$py,self::alloc($im,self::mix($a,$b,$t)));}}}
    private static function gradientColor(string $a,string $b,string $mode,int $angle,float $x,float $y):string
    { if($mode==='solid')return$a;$t=$mode==='radial'?min(1,sqrt(($x-.5)**2+($y-.5)**2)*1.414):max(0,min(1,.5+(($x-.5)*cos(deg2rad($angle))+($y-.5)*sin(deg2rad($angle)))));return self::mix($a,$b,$t);}
    private static function mix(string $a,string $b,float $t):string
    { $out='#';for($i=1;$i<7;$i+=2)$out.=str_pad(dechex((int)round(hexdec(substr($a,$i,2))*(1-$t)+hexdec(substr($b,$i,2))*$t)),2,'0',STR_PAD_LEFT);return$out;}
    private static function alloc($im,string $hex,int $alpha=0):int{$hex=ltrim($hex,'#');return imagecolorallocatealpha($im,hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2)),$alpha);}
}
