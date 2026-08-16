<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/config/app.php';
    }
    return $config[$key] ?? $default;
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('public/assets/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function flash(string $key, mixed $default = null): mixed
{
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function auth(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function active(string $prefix): string
{
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    return str_starts_with($path, trim($prefix, '/')) ? 'active' : '';
}

function report_badge(string $status): string
{
    return match ($status) {
        'nuevo' => 'danger',
        'validando' => 'warning',
        'asignado' => 'info',
        'en_proceso' => 'primary',
        'resuelto' => 'success',
        'cerrado' => 'secondary',
        default => 'secondary',
    };
}

