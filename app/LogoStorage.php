<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class LogoStorage
{
    public static function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری لوگو کامل نشد.');
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('حجم لوگو حداکثر ۲ مگابایت است.');
        $info = @getimagesize((string) $file['tmp_name']);
        $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
        if (!$info || !isset($allowed[$info['mime']])) throw new RuntimeException('لوگو باید PNG، JPG یا WebP معتبر باشد. SVG به‌دلیل ملاحظات امنیتی پذیرفته نمی‌شود.');
        if ($info[0] > 3000 || $info[1] > 3000) throw new RuntimeException('ابعاد لوگو حداکثر ۳۰۰۰ در ۳۰۰۰ پیکسل است.');
        $dir = dirname(__DIR__) . '/storage/logos';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('پوشه ذخیره لوگو قابل ایجاد نیست.');
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$info['mime']];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('ذخیره لوگو انجام نشد. دسترسی پوشه storage را بررسی کنید.');
        return 'storage/logos/' . $name;
    }

    public static function absolute(?string $relative): ?string
    {
        if (!$relative || !preg_match('#^storage/logos/[a-f0-9]{32}\.(png|jpg|webp)$#', $relative)) return null;
        $path = dirname(__DIR__) . '/' . $relative;
        return is_file($path) ? $path : null;
    }

    public static function temporary(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 2 * 1024 * 1024) return null;
        $info = @getimagesize((string) $file['tmp_name']);
        return $info && in_array($info['mime'], ['image/png','image/jpeg','image/webp'], true) ? (string) $file['tmp_name'] : null;
    }

    public static function delete(?string $relative): void
    {
        $path = self::absolute($relative);
        if ($path) @unlink($path);
    }
}
