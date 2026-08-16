<?php
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DeviceController;
use App\Controllers\DispatchController;
use App\Controllers\CitizenController;
use App\Controllers\PetController;
use App\Controllers\ReportController;

$router->get('/', [AuthController::class, 'home']);
$router->get('/ingresar', [AuthController::class, 'loginForm'], ['guest']);
$router->post('/ingresar', [AuthController::class, 'login'], ['guest']);
$router->get('/registro', [AuthController::class, 'registerForm'], ['guest']);
$router->post('/registro', [AuthController::class, 'register'], ['guest']);
$router->post('/salir', [AuthController::class, 'logout'], ['auth']);

$router->get('/descargar-vecinos', [CitizenController::class, 'landing']);
$router->post('/descargar-vecinos/registro', [CitizenController::class, 'registerNeighbor'], ['guest']);

$router->get('/panel', [DashboardController::class, 'index'], ['permission:dashboard.view']);
$router->get('/mi-app', [CitizenController::class, 'index'], ['auth']);
$router->post('/mi-app/panico', [CitizenController::class, 'panic'], ['auth']);
$router->get('/reportes', [ReportController::class, 'index'], ['auth']);
$router->get('/reportes/nuevo', [ReportController::class, 'create'], ['permission:reports.create']);
$router->post('/reportes', [ReportController::class, 'store'], ['permission:reports.create']);
$router->post('/reportes/sincronizar', [ReportController::class, 'offlineSync'], ['permission:reports.create']);
$router->get('/reportes/{id}', [ReportController::class, 'show'], ['auth']);
$router->post('/reportes/{id}/comentarios', [ReportController::class, 'comment'], ['auth']);
$router->post('/reportes/{id}/estado', [ReportController::class, 'status'], ['permission:reports.manage']);
$router->post('/reportes/{id}/despachar', [ReportController::class, 'dispatch'], ['permission:dispatch.manage']);
$router->get('/reportes/media/{id}', [ReportController::class, 'media'], ['auth']);

$router->get('/despachos', [DispatchController::class, 'index'], ['permission:dispatch.manage']);
$router->post('/despachos/{id}/estado', [DispatchController::class, 'status'], ['permission:dispatch.manage']);

$router->get('/mascotas', [PetController::class, 'index'], ['auth']);
$router->post('/mascotas', [PetController::class, 'store'], ['permission:pets.manage']);
$router->post('/mascotas/{id}/estado', [PetController::class, 'status'], ['permission:pets.manage']);
$router->get('/mascotas/{id}/credencial', [PetController::class, 'credential'], ['auth']);
$router->get('/mascota/qr/{token}', [PetController::class, 'publicProfile']);
$router->post('/mascota/qr/{token}/avistamiento', [PetController::class, 'sighting']);

$router->get('/dispositivos', [DeviceController::class, 'index'], ['auth']);
$router->post('/dispositivos', [DeviceController::class, 'store'], ['permission:devices.own']);
$router->post('/dispositivos/{id}/evento', [DeviceController::class, 'event'], ['permission:devices.own']);
$router->post('/api/dispositivos/{token}/evento', [DeviceController::class, 'webhook']);

$router->get('/administracion/usuarios', [AdminController::class, 'users'], ['permission:users.manage']);
$router->post('/administracion/usuarios', [AdminController::class, 'storeUser'], ['permission:users.manage']);
$router->post('/administracion/usuarios/{id}/estado', [AdminController::class, 'userStatus'], ['permission:users.manage']);
$router->get('/administracion/comunas', [AdminController::class, 'communes'], ['permission:communes.manage']);
$router->post('/administracion/comunas', [AdminController::class, 'storeCommune'], ['permission:communes.manage']);
$router->post('/administracion/sectores', [AdminController::class, 'storeSector'], ['permission:communes.manage']);
$router->get('/administracion/roles', [AdminController::class, 'roles'], ['permission:roles.manage']);
$router->post('/administracion/roles/{id}', [AdminController::class, 'updateRole'], ['permission:roles.manage']);
$router->get('/administracion/auditoria', [AdminController::class, 'audit'], ['permission:audit.view']);
$router->get('/administracion/configuracion', [AdminController::class, 'settings'], ['permission:settings.manage']);
$router->post('/administracion/configuracion', [AdminController::class, 'updateSettings'], ['permission:settings.manage']);
$router->post('/administracion/contactos', [AdminController::class, 'storeContact'], ['permission:settings.manage']);
$router->post('/administracion/contactos/{id}/estado', [AdminController::class, 'contactStatus'], ['permission:settings.manage']);
