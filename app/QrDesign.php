<?php

declare(strict_types=1);

namespace App;

final class QrDesign
{
    public const PRESETS = ['classic', 'minimal', 'modern', 'gradient', 'branded', 'logo', 'rounded'];
    public const DOT_STYLES = ['square', 'rounded', 'dot', 'classy', 'classy_rounded', 'extra_rounded'];
    public const FINDER_STYLES = ['square', 'rounded', 'dot', 'classy', 'inpoint', 'outpoint'];
    public const FINDER_DOT_STYLES = ['square', 'rounded', 'dot'];

    public static function defaults(): array
    {
        return [
            'preset' => 'classic', 'dot_style' => 'square', 'finder_style' => 'square', 'finder_dot_style' => 'square',
            'color_mode' => 'solid', 'foreground_color' => '#111827', 'gradient_end_color' => '#315ce8', 'gradient_angle' => 45,
            'corner_color' => '#111827', 'corner_dot_color' => '#111827',
            'background_mode' => 'solid', 'background_color' => '#ffffff', 'background_end_color' => '#eef2ff', 'background_angle' => 45,
            'error_correction' => 'M', 'output_size' => 480, 'margin' => 24,
            'logo_size' => 18, 'logo_margin' => 6, 'logo_backing' => true, 'logo_rounded' => true, 'hide_modules' => true,
            'frame_style' => 'none', 'frame_color' => '#111827', 'frame_thickness' => 4, 'frame_radius' => 20,
            'frame_inner' => false, 'frame_outer' => true, 'frame_text' => '',
        ];
    }

    public static function fromRecord(?array $record): array
    {
        $defaults = self::defaults();
        if (!$record) return $defaults;
        $stored = json_decode((string) ($record['design_json'] ?? ''), true);
        if (!is_array($stored)) {
            $stored = ['foreground_color' => $record['foreground_color'] ?? '#111827', 'background_color' => $record['background_color'] ?? '#ffffff'];
        }
        return self::sanitize(array_replace($defaults, $stored));
    }

    public static function fromInput(array $input): array
    {
        $bools = ['logo_backing', 'logo_rounded', 'hide_modules', 'frame_inner', 'frame_outer'];
        foreach ($bools as $key) $input[$key] = isset($input[$key]) && (string) $input[$key] !== '0';
        return self::sanitize(array_replace(self::defaults(), $input));
    }

    public static function sanitize(array $d): array
    {
        $d['preset'] = self::choice($d['preset'] ?? '', self::PRESETS, 'classic');
        $d['dot_style'] = self::choice($d['dot_style'] ?? '', self::DOT_STYLES, 'square');
        $d['finder_style'] = self::choice($d['finder_style'] ?? '', self::FINDER_STYLES, 'square');
        $d['finder_dot_style'] = self::choice($d['finder_dot_style'] ?? '', self::FINDER_DOT_STYLES, 'square');
        $d['color_mode'] = self::choice($d['color_mode'] ?? '', ['solid','linear','radial'], 'solid');
        $d['background_mode'] = self::choice($d['background_mode'] ?? '', ['solid','linear','radial','transparent'], 'solid');
        foreach (['foreground_color','gradient_end_color','corner_color','corner_dot_color','background_color','background_end_color','frame_color'] as $key) {
            $d[$key] = self::color((string) ($d[$key] ?? ''), self::defaults()[$key]);
        }
        $d['gradient_angle'] = self::int($d['gradient_angle'] ?? 45, 0, 360);
        $d['background_angle'] = self::int($d['background_angle'] ?? 45, 0, 360);
        $d['error_correction'] = self::choice($d['error_correction'] ?? '', ['L','M','Q','H'], 'M');
        $d['output_size'] = self::int($d['output_size'] ?? 480, 280, 1000);
        $d['margin'] = self::int($d['margin'] ?? 24, 16, 80);
        $d['logo_size'] = self::int($d['logo_size'] ?? 18, 8, 24);
        $d['logo_margin'] = self::int($d['logo_margin'] ?? 6, 0, 20);
        foreach (['logo_backing','logo_rounded','hide_modules','frame_inner','frame_outer'] as $key) $d[$key] = (bool) ($d[$key] ?? false);
        $d['frame_style'] = self::choice($d['frame_style'] ?? '', ['none','simple','rounded','branded'], 'none');
        $d['frame_thickness'] = self::int($d['frame_thickness'] ?? 4, 1, 18);
        $d['frame_radius'] = self::int($d['frame_radius'] ?? 20, 0, 60);
        $text = preg_replace('/[^A-Za-z0-9 .!_-]/', '', (string) ($d['frame_text'] ?? '')) ?? '';
        $d['frame_text'] = substr(trim($text), 0, 32);
        return array_intersect_key($d, self::defaults());
    }

    public static function safety(array $d, bool $hasLogo): array
    {
        $score = 100; $notes = [];
        $contrast = self::contrast($d['foreground_color'], $d['background_color']);
        if ($contrast < 2.5) { $score -= 55; $notes[] = 'کنتراست رنگ‌ها بسیار کم است.'; }
        elseif ($contrast < 4.5) { $score -= 25; $notes[] = 'کنتراست رنگ‌ها بهتر است بیشتر شود.'; }
        if ($d['background_mode'] === 'transparent') { $score -= 18; $notes[] = 'پس‌زمینه شفاف به سطح زیرین وابسته است.'; }
        if ($d['margin'] < 20) { $score -= 18; $notes[] = 'فضای سفید اطراف کم است.'; }
        if ($hasLogo && $d['logo_size'] > 20) { $score -= 30; $notes[] = 'لوگو بیش از اندازه بزرگ است.'; }
        if ($hasLogo && !in_array($d['error_correction'], ['Q','H'], true)) { $score -= 30; $notes[] = 'برای لوگو سطح H پیشنهاد می‌شود.'; }
        if ($d['color_mode'] !== 'solid') { $score -= 7; $notes[] = 'گرادیان را روی چند دستگاه آزمایش کنید.'; }
        if (in_array($d['dot_style'], ['dot','classy','classy_rounded'], true)) $score -= 5;
        $status = $score >= 75 ? 'safe' : ($score >= 45 ? 'warning' : 'unsafe');
        return ['status'=>$status, 'score'=>max(0,$score), 'label'=>match($status){'safe'=>'قابل اسکن','warning'=>'ممکن است اسکن سخت شود',default=>'این تنظیمات ممکن است QR را غیرقابل اسکن کند'}, 'notes'=>$notes];
    }

    private static function choice(mixed $value, array $allowed, string $default): string { return in_array((string)$value,$allowed,true)?(string)$value:$default; }
    private static function int(mixed $value, int $min, int $max): int { return max($min,min($max,(int)$value)); }
    private static function color(string $value, string $default): string { return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$default; }
    private static function contrast(string $a, string $b): float
    {
        $l=[]; foreach([$a,$b] as $hex){$rgb=[];for($i=1;$i<7;$i+=2){$v=hexdec(substr($hex,$i,2))/255;$rgb[]=$v<=.03928?$v/12.92:(($v+.055)/1.055)**2.4;}$l[]=0.2126*$rgb[0]+0.7152*$rgb[1]+0.0722*$rgb[2];}
        return (max($l)+.05)/(min($l)+.05);
    }
}

