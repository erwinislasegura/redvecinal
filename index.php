<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);

$host = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
$hasInstallLock = file_exists(BASE_PATH . '/storage/installed.lock');
$localDatabaseReady = false;

if ($isLocalHost && file_exists(BASE_PATH . '/config/database.php')) {
    try {
        $db = require BASE_PATH . '/config/database.php';
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'] ?? '3306', $db['database'], $db['charset'] ?? 'utf8mb4'),
            $db['username'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->query('SELECT 1 FROM users LIMIT 1');
        $localDatabaseReady = true;
    } catch (Throwable) {
        $localDatabaseReady = false;
    }
}

if (!$hasInstallLock && !$localDatabaseReady) {
    header('Location: install/');
    exit;
}

require BASE_PATH . '/app/bootstrap.php';

use App\Core\Router;

$router = new Router();
require BASE_PATH . '/app/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
