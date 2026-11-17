<?php

declare(strict_types=1);

namespace App;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || !hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token)) {
            http_response_code(419);
            exit('درخواست منقضی یا نامعتبر است. صفحه را تازه‌سازی کنید.');
        }
    }
}

