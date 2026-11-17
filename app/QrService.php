<?php

declare(strict_types=1);

namespace App;

final class QrService
{
    public static function render(string $data, string $format, string $foreground = '#111827', string $background = '#ffffff', ?array $design = null, ?string $logoPath = null): array
    {
        $design ??= array_replace(QrDesign::defaults(), ['foreground_color'=>$foreground, 'background_color'=>$background]);
        return StyledQrRenderer::render($data, $format, $design, $logoPath);
    }

    public static function validateColor(string $color): bool { return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color); }

    public static function validateHttpUrl(string $url): bool
    {
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    public static function payload(array $qr): string
    {
        return $qr['type'] === 'dynamic' ? url('/r/' . $qr['token']) : $qr['payload'];
    }

}

