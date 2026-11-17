<?php

declare(strict_types=1);

namespace App;

final class DeviceParser
{
    public static function parse(string $ua): array
    {
        $device = preg_match('/mobile|android|iphone|ipad/i', $ua) ? (preg_match('/ipad|tablet/i', $ua) ? 'تبلت' : 'موبایل') : 'دسکتاپ';
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'سایر'
        };
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Android') => 'Android', preg_match('/iPhone|iPad/', $ua) === 1 => 'iOS', str_contains($ua, 'Mac OS') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => 'سایر'
        };
        return [$device, $browser, $os];
    }
}
