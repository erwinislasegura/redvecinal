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
    if ($base === '') {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        if (str_ends_with($scriptDirectory, '/install')) {
            $scriptDirectory = dirname($scriptDirectory);
        }
        $scriptDirectory = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : '/' . trim($scriptDirectory, '/');
        $base = $scheme . '://' . $host . $scriptDirectory;
    }
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

function setting(string $key, mixed $default = null, ?int $communeId = null): mixed
{
    static $cache = [];
    $communeId = $communeId ?? (int) (Auth::user()['commune_id'] ?? 0);
    if ($communeId <= 0) return $default;
    $cacheKey = $communeId . ':' . $key;
    if (!array_key_exists($cacheKey, $cache)) {
        $value = \App\Core\Database::query('SELECT setting_value FROM settings WHERE commune_id=? AND setting_key=? LIMIT 1', [$communeId, $key])->fetchColumn();
        $cache[$cacheKey] = $value === false ? $default : $value;
    }
    return $cache[$cacheKey];
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

function dispatch_badge(string $status): string
{
    return match ($status) {
        'solicitado' => 'warning',
        'aceptado' => 'info',
        'en_camino' => 'primary',
        'en_sitio', 'finalizado' => 'success',
        'cancelado' => 'secondary',
        default => 'secondary',
    };
}

function svg_icon(string $name, string $class = ''): string
{
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'alert' => '<path d="M10.3 3.5 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.5a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'paw' => '<circle cx="11" cy="4.5" r="2"/><circle cx="18" cy="8.5" r="2"/><circle cx="5" cy="8.5" r="2"/><path d="M12 9c-4 0-7 3.4-7 6.5 0 2.2 1.7 3.5 3.6 3.5 1.4 0 2.2-.8 3.4-.8s2 .8 3.4.8c1.9 0 3.6-1.3 3.6-3.5C19 12.4 16 9 12 9Z"/>',
        'device' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 19v2"/><path d="M16 19v2"/><circle cx="12" cy="12" r="3"/><path d="M12 9V7"/>',
        'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M10 5h4"/><path d="M11 18h2"/>',
        'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'map' => '<path d="m3 6 5-3 8 3 5-3v15l-5 3-8-3-5 3Z"/><path d="M8 3v15"/><path d="M16 6v15"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
    ];
    $body = $icons[$name] ?? $icons['dashboard'];
    return '<svg class="ui-icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
