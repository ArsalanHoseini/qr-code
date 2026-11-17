<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), (int) env('DB_PORT', 3306), env('DB_DATABASE', 'qr_manager'));
        try {
            self::$pdo = new PDO($dsn, (string) env('DB_USERNAME', 'root'), (string) env('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            return self::$pdo;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new RuntimeException('اتصال به پایگاه داده برقرار نشد. تنظیمات فایل .env را بررسی کنید.');
        }
    }
}

