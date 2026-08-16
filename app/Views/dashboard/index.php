<section class="dashboard-hero">
  <div>
    <span class="section-kicker">CENTRO DE OPERACIONES</span>
    <h2>Buenos días, <?= e(explode(' ', auth()['name'])[0]) ?></h2>
    <p>Revisa la actividad comunal y coordina las acciones pendientes desde un solo lugar.</p>
  </div>
  <div class="hero-meta">
    <span><?= svg_icon('clock') ?> Actualizado <?= date('H:i') ?></span>
    <?php if(can('dispatch.manage')): ?><a class="btn btn-light" href="<?= url('despachos') ?>"><?= svg_icon('truck') ?> Ver despachos</a><?php endif; ?>
    <?php if(can('reports.create')): ?><a class="btn btn-light" href="<?= url('reportes/nuevo') ?>"><?= svg_icon('plus') ?> Crear reporte</a><?php endif; ?>
  </div>
</section>

<section class="stats-grid" aria-label="Indicadores principales">
  <article class="stat-card stat-danger"><div class="stat-top"><span class="stat-icon"><?= svg_icon('alert') ?></span><span class="stat-trend">Pendientes</span></div><strong><?= $stats['open'] ?></strong><p>Reportes activos</p><a href="<?= url('reportes?status=nuevo') ?>">Revisar reportes <?= svg_icon('chevron') ?></a></article>
  <article class="stat-card stat-success"><div class="stat-top"><span class="stat-icon"><?= svg_icon('check') ?></span><span class="stat-trend">Últimos 30 días</span></div><strong><?= $stats['resolved'] ?></strong><p>Casos resueltos</p><a href="<?= url('reportes?status=resuelto') ?>">Ver resultados <?= svg_icon('chevron') ?></a></article>
  <article class="stat-card stat-primary"><div class="stat-top"><span class="stat-icon"><?= svg_icon('paw') ?></span><span class="stat-trend">En búsqueda</span></div><strong><?= $stats['pets'] ?></strong><p>Mascotas perdidas</p><a href="<?= url('mascotas') ?>">Ir a mascotas <?= svg_icon('chevron') ?></a></article>
  <article class="stat-card stat-neutral"><div class="stat-top"><span class="stat-icon"><?= svg_icon('users') ?></span><span class="stat-trend">Comunidad</span></div><strong><?= $stats['users'] ?></strong><p>Vecinos activos</p><?php if(can('users.manage')): ?><a href="<?= url('administracion/usuarios') ?>">Gestionar usuarios <?= svg_icon('chevron') ?></a><?php else: ?><span class="stat-caption">Red comunal registrada</span><?php endif; ?></article>
</section>

<section class="card panel-card commune-map-card">
  <div class="card-header">
    <div><span class="card-eyebrow">COBERTURA TERRITORIAL</span><h2>Mapa de <?= e($mapConfig['commune']) ?></h2><p>Reportes con ubicación registrada en la comuna.</p></div>
    <div class="map-filters" aria-label="Filtrar marcadores"><button class="active" data-map-filter="all">Todos</button><button data-map-filter="critica">Críticos</button><button data-map-filter="open">Activos</button></div>
  </div>
  <div id="communeMap" class="commune-map" aria-label="Mapa de reportes"></div>
  <div class="map-footer"><span><i class="map-dot critical"></i> Crítico</span><span><i class="map-dot high"></i> Alta</span><span><i class="map-dot normal"></i> Media o baja</span><b><?= count($mapReports) ?> reportes geolocalizados</b></div>
</section>
<script type="application/json" id="dashboardMapData"><?= json_encode(['config'=>$mapConfig,'reports'=>$mapReports],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP) ?></script>

<div class="dashboard-grid">
  <section class="card panel-card activity-panel">
    <div class="card-header">
      <div><span class="card-eyebrow">SEGUIMIENTO</span><h2>Actividad reciente</h2><p>Reportes ordenados por prioridad y fecha</p></div>
      <a class="text-link" href="<?= url('reportes') ?>">Ver todos <?= svg_icon('chevron') ?></a>
    </div>
    <div class="report-list">
      <?php if(!$reports): ?><div class="empty-state"><span class="empty-icon"><?= svg_icon('check') ?></span><strong>Todo está al día</strong><p>Aún no hay reportes en esta comunidad.</p></div><?php endif; ?>
      <?php foreach($reports as $report): ?>
        <a class="report-row" href="<?= url('reportes/'.$report['id']) ?>">
          <span class="report-symbol" style="--report-color:<?= e($report['color']) ?>"><?= e(mb_strtoupper(mb_substr($report['type_name'],0,1))) ?></span>
          <div class="report-copy"><strong><?= e($report['title']) ?></strong><span><?= e($report['type_name']) ?> <i></i> <?= e($report['address'] ?: 'Sin dirección informada') ?></span></div>
          <span class="priority priority-<?= e($report['priority']) ?>"><?= e($report['priority']) ?></span>
          <span class="badge bg-<?= report_badge($report['status']) ?>"><?= e(str_replace('_',' ',$report['status'])) ?></span>
          <time><strong><?= e(date('H:i',strtotime($report['created_at']))) ?></strong><span><?= e(date('d/m/Y',strtotime($report['created_at']))) ?></span></time>
          <?= svg_icon('chevron','row-chevron') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="dashboard-side">
    <?php if(can('users.manage') || can('communes.manage') || can('audit.view') || can('settings.manage')): ?>
      <section class="card panel-card quick-panel">
        <div class="card-header"><div><span class="card-eyebrow">ACCESOS DIRECTOS</span><h2>Administración</h2></div></div>
        <div class="quick-actions">
          <?php if(can('users.manage')): ?><a href="<?= url('administracion/usuarios') ?>"><span><?= svg_icon('users') ?></span><div><strong>Usuarios</strong><small>Roles y accesos</small></div><?= svg_icon('chevron') ?></a><?php endif; ?>
          <?php if(can('communes.manage')): ?><a href="<?= url('administracion/comunas') ?>"><span><?= svg_icon('map') ?></span><div><strong>Comunas</strong><small>Sectores territoriales</small></div><?= svg_icon('chevron') ?></a><?php endif; ?>
          <?php if(can('roles.manage')): ?><a href="<?= url('administracion/roles') ?>"><span><?= svg_icon('shield') ?></span><div><strong>Permisos</strong><small>Control de funciones</small></div><?= svg_icon('chevron') ?></a><?php endif; ?>
          <?php if(can('audit.view')): ?><a href="<?= url('administracion/auditoria') ?>"><span><?= svg_icon('history') ?></span><div><strong>Auditoría</strong><small>Historial de acciones</small></div><?= svg_icon('chevron') ?></a><?php endif; ?>
          <?php if(can('settings.manage')): ?><a href="<?= url('administracion/configuracion') ?>"><span><?= svg_icon('settings') ?></span><div><strong>Configuración</strong><small>Preferencias comunales</small></div><?= svg_icon('chevron') ?></a><?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="card panel-card emergency-panel">
      <div class="card-header"><div><span class="card-eyebrow">CONTACTO DIRECTO</span><h2>Emergencias</h2></div><span class="live-label"><i></i> 24/7</span></div>
      <div class="emergency-list"><?php foreach($contacts as $contact): ?><a href="tel:<?= e($contact['phone']) ?>"><span><strong><?= e($contact['service']) ?></strong><small><?= e($contact['name']) ?></small></span><b><?= e($contact['phone']) ?></b></a><?php endforeach; ?></div>
    </section>

    <section class="card panel-card notification-panel">
      <div class="card-header"><div><span class="card-eyebrow">BANDEJA</span><h2>Notificaciones</h2></div></div>
      <?php if(!$notifications): ?><div class="empty-state compact"><span class="empty-icon"><?= svg_icon('bell') ?></span><p>No tienes notificaciones nuevas.</p></div><?php endif; ?>
      <?php foreach($notifications as $notification): ?><a class="notification-row" href="<?= e($notification['action_url'] ?: '#') ?>"><span class="notification-dot"></span><div><strong><?= e($notification['title']) ?></strong><p><?= e($notification['message']) ?></p><small><?= e(date('d/m/Y H:i',strtotime($notification['created_at']))) ?></small></div></a><?php endforeach; ?>
    </section>
  </aside>
</div>
