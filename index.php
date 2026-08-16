<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);

if (!file_exists(BASE_PATH . '/config/database.php')) {
    header('Location: install/');
    exit;
}

require BASE_PATH . '/app/bootstrap.php';

use App\Core\Router;

$router = new Router();
require BASE_PATH . '/app/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

