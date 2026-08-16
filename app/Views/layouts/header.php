<?php
$currentUser = auth();
$message = flash('message');
$messageType = flash('type', 'success');
$viewHost = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
$isLocalView = in_array($viewHost, ['localhost', '127.0.0.1', '::1'], true);
$unreadNotifications = $currentUser
    ? (int) \App\Core\Database::query('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [$currentUser['id']])->fetchColumn()
    : 0;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#123f37">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="RedVecinal">
  <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
  <title><?= e($title ?? 'RedVecinal') ?> | RedVecinal</title>
  <link rel="manifest" href="<?= url('public/manifest.json') ?>">
  <link rel="apple-touch-icon" href="<?= asset('img/icon-192.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin-modules.css') ?>">
  <?php if(!empty($useMap)): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><?php endif; ?>
</head>
<body class="<?= !empty($publicPage) ? 'public-body' : 'app-body' ?>">
<?php if (!$isLocalView): ?><div id="offlineBanner" class="offline-banner" hidden><?= svg_icon('alert') ?> Sin conexión: los reportes nuevos se guardarán para enviarse después.</div><?php endif; ?>

<?php if ($currentUser): ?>
  <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
    <div class="sidebar-head">
      <a class="brand" href="<?= url('panel') ?>">
        <span class="brand-symbol">RV</span>
        <span class="brand-copy"><strong>RedVecinal</strong><small>Gestión comunitaria</small></span>
      </a>
      <button class="sidebar-close" data-sidebar-toggle aria-label="Cerrar menú">×</button>
    </div>

    <div class="workspace-card">
      <span class="workspace-icon"><?= svg_icon('map') ?></span>
      <div><small>Comuna activa</small><strong><?= e($currentUser['commune_name'] ?? 'Administración global') ?></strong></div>
    </div>

    <nav class="side-nav">
      <div class="nav-group">
        <div class="nav-label">Operación</div>
        <a class="<?= active('panel') ?>" href="<?= url('panel') ?>"><?= svg_icon('dashboard') ?><span>Resumen general</span></a>
        <?php if(can('reports.create')): ?><a class="<?= active('mi-app') ?>" href="<?= url('mi-app') ?>"><?= svg_icon('phone') ?><span>App para vecinos</span></a><?php endif; ?>
        <a class="<?= active('reportes') ?>" href="<?= url('reportes') ?>"><?= svg_icon('alert') ?><span>Reportes</span><i class="nav-indicator"></i></a>
        <?php if (can('dispatch.manage')): ?><a class="<?= active('despachos') ?>" href="<?= url('despachos') ?>"><?= svg_icon('truck') ?><span>Despacho de servicios</span></a><?php endif; ?>
        <a class="<?= active('mascotas') ?>" href="<?= url('mascotas') ?>"><?= svg_icon('paw') ?><span>Mascotas</span></a>
        <a class="<?= active('dispositivos') ?>" href="<?= url('dispositivos') ?>"><?= svg_icon('device') ?><span>Dispositivos</span></a>
      </div>

      <?php if (can('users.manage') || can('communes.manage') || can('roles.manage') || can('audit.view') || can('settings.manage')): ?>
        <div class="nav-group">
          <div class="nav-label">Administración</div>
          <?php if (can('users.manage')): ?><a class="<?= active('administracion/usuarios') ?>" href="<?= url('administracion/usuarios') ?>"><?= svg_icon('users') ?><span>Usuarios</span></a><?php endif; ?>
          <?php if (can('communes.manage')): ?><a class="<?= active('administracion/comunas') ?>" href="<?= url('administracion/comunas') ?>"><?= svg_icon('map') ?><span>Comunas y sectores</span></a><?php endif; ?>
          <?php if (can('roles.manage')): ?><a class="<?= active('administracion/roles') ?>" href="<?= url('administracion/roles') ?>"><?= svg_icon('shield') ?><span>Roles y permisos</span></a><?php endif; ?>
          <?php if (can('audit.view')): ?><a class="<?= active('administracion/auditoria') ?>" href="<?= url('administracion/auditoria') ?>"><?= svg_icon('history') ?><span>Auditoría</span></a><?php endif; ?>
          <?php if (can('settings.manage')): ?><a class="<?= active('administracion/configuracion') ?>" href="<?= url('administracion/configuracion') ?>"><?= svg_icon('settings') ?><span>Configuración</span></a><?php endif; ?>
        </div>
      <?php endif; ?>
    </nav>

    <div class="sidebar-user">
      <div class="avatar"><?= e(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></div>
      <div class="user-copy"><strong><?= e($currentUser['name']) ?></strong><small><?= e($currentUser['role_name']) ?></small></div>
      <form method="post" action="<?= url('salir') ?>"><?= csrf_field() ?><button class="icon-button" title="Cerrar sesión" aria-label="Cerrar sesión"><?= svg_icon('logout') ?></button></form>
    </div>
  </aside>
  <button class="sidebar-backdrop" data-sidebar-toggle aria-label="Cerrar menú"></button>

  <div class="app-main">
    <header class="topbar">
      <div class="topbar-title">
        <button class="menu-button" data-sidebar-toggle aria-label="Abrir menú"><?= svg_icon('menu') ?></button>
        <div><span class="breadcrumb-label">Panel administrativo / <?= e($currentUser['commune_name'] ?? 'Global') ?></span><h1><?= e($title ?? 'Panel') ?></h1></div>
      </div>
      <div class="top-actions">
        <button class="notification-button" aria-label="Notificaciones"><?= svg_icon('bell') ?><?php if($unreadNotifications): ?><span><?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?></span><?php endif; ?></button>
        <?php if(can('reports.create')): ?><a class="btn btn-danger btn-sm primary-action" href="<?= url('reportes/nuevo') ?>"><?= svg_icon('plus') ?><span>Nuevo reporte</span></a><?php endif; ?>
      </div>
    </header>
    <main class="content">
<?php else: ?>
  <header class="public-nav"><div class="container nav-inner"><a class="brand brand-dark" href="<?= url() ?>"><span class="brand-symbol">RV</span><span>RedVecinal</span></a><nav><a class="download-neighbor-link" href="<?= url('vecinos') ?>"><?= svg_icon('phone') ?> Descargar vecinos</a><a class="public-login-link" href="<?= url('ingresar') ?>">Ingresar</a><a class="btn btn-primary btn-sm" href="<?= url('vecinos#registro') ?>">Crear cuenta</a></nav></div></header>
  <main>
<?php endif; ?>

<?php if ($message): ?>
  <div class="alert alert-<?= e($messageType) ?> alert-dismissible" role="alert"><?= e($message) ?><button type="button" class="alert-close" data-dismiss-alert>×</button></div>
<?php endif; ?>
