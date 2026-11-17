<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public static function check(): bool { return isset($_SESSION['admin_id']); }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        $stmt = Database::connection()->prepare('SELECT id, name, email FROM admins WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function require(): void
    {
        if (!self::check() || !self::user()) {
            self::logout(false);
            flash('error', 'برای ادامه وارد حساب مدیریت شوید.');
            redirect('/login');
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        self::clearExpiredAttempts($pdo);
        $key = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_key = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND succeeded = 0');
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() >= 5) {
            flash('error', 'تلاش‌های ناموفق بیش از حد مجاز است؛ ۱۵ دقیقه دیگر دوباره امتحان کنید.');
            return false;
        }
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $admin = $stmt->fetch();
        $ok = $admin && password_verify($password, $admin['password_hash']);
        $pdo->prepare('INSERT INTO login_attempts (attempt_key, succeeded, attempted_at) VALUES (?, ?, NOW())')->execute([$key, $ok ? 1 : 0]);
        if (!$ok) return false;
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
        return true;
    }

    public static function logout(bool $destroy = true): void
    {
        unset($_SESSION['admin_id']);
        if ($destroy) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        }
    }

    public static function hasAdmins(): bool
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    }

    private static function clearExpiredAttempts(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
    }
}

