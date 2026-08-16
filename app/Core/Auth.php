<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::query(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name, c.name AS commune_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN communes c ON c.id = u.commune_id
             WHERE u.email = ? AND u.status = 'activo' LIMIT 1",
            [mb_strtolower(trim($email))]
        )->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::$user = $user;
        return true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $user = Database::query(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name, c.name AS commune_name
             FROM users u JOIN roles r ON r.id = u.role_id
             LEFT JOIN communes c ON c.id = u.commune_id
             WHERE u.id = ? AND u.status = 'activo' LIMIT 1",
            [$_SESSION['user_id']]
        )->fetch();
        self::$user = $user ?: null;
        return self::$user;
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user['role_slug'] === 'superadmin') {
            return true;
        }
        $statement = Database::query(
            'SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.slug = ?',
            [$user['role_id'], $permission]
        );
        return (int) $statement->fetchColumn() > 0;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['_flash']['message'] = 'Inicia sesión para continuar.';
            $_SESSION['_flash']['type'] = 'warning';
            header('Location: ' . url('ingresar'));
            exit;
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            exit('No tienes permisos para realizar esta acción.');
        }
    }
}

