<div class="citizen-app" data-citizen-app>
  <section class="citizen-welcome">
    <div><span class="section-kicker">MI REDVECINAL</span><h2>Hola, <?= e(explode(' ',auth()['name'])[0]) ?></h2><p><?= e(auth()['commune_name']??'Tu comunidad') ?> · Tu red de ayuda cercana</p></div>
    <button class="install-app-button" data-install-app hidden><?= svg_icon('phone') ?> Instalar app</button>
  </section>

  <section class="panic-card">
    <div class="panic-copy"><span class="panic-shield"><?= svg_icon('alert') ?></span><div><span>EMERGENCIA</span><h2>Botón de pánico</h2><p>Envía una alerta crítica con tu ubicación a la central comunal.</p></div></div>
    <form action="<?= url('mi-app/panico') ?>" method="post" data-panic-form><?= csrf_field() ?><input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="address" value="<?= e(auth()['address']??'') ?>"><button type="button" class="panic-button" data-panic-trigger><i></i><strong>MANTENER Y ACTIVAR</strong><small>Presiona durante 2 segundos</small></button><p class="panic-status" data-panic-status>En riesgo vital, llama directamente al 133, 132 o 131.</p></form>
  </section>

  <section class="citizen-actions" aria-label="Acciones principales">
    <a href="<?= url('reportes/nuevo') ?>"><span class="action-icon danger"><?= svg_icon('plus') ?></span><strong>Nueva denuncia</strong><small>Seguridad o barrio</small></a>
    <a href="<?= url('reportes') ?>"><span class="action-icon primary"><?= svg_icon('alert') ?></span><strong>Mis reportes</strong><small>Revisar seguimiento</small></a>
    <a href="<?= url('mascotas') ?>"><span class="action-icon warning"><?= svg_icon('paw') ?></span><strong>Mascotas</strong><small><?= $pets ?> registradas</small></a>
    <a href="<?= url('dispositivos') ?>"><span class="action-icon success"><?= svg_icon('device') ?></span><strong>Mi seguridad</strong><small><?= $devices ?> dispositivos</small></a>
  </section>

  <div class="citizen-grid">
    <section class="card panel-card">
      <div class="card-header"><div><span class="card-eyebrow">SEGUIMIENTO</span><h2>Mis denuncias recientes</h2><p>Estado informado por la central.</p></div><a href="<?= url('reportes') ?>">Ver todas</a></div>
      <div class="citizen-report-list"><?php if(!$reports): ?><div class="empty-state"><strong>Aún no tienes reportes</strong><p>Puedes crear el primero desde “Nueva denuncia”.</p></div><?php endif; ?><?php foreach($reports as $report): ?><a href="<?= url('reportes/'.$report['id']) ?>"><span class="citizen-report-icon" style="--report-color:<?= e($report['color']) ?>"><?= e(mb_strtoupper(mb_substr($report['type_name'],0,1))) ?></span><div><strong><?= e($report['title']) ?></strong><small><?= e($report['public_code']) ?> · <?= e(date('d/m/Y H:i',strtotime($report['created_at']))) ?></small></div><span class="badge bg-<?= report_badge($report['status']) ?>"><?= e(str_replace('_',' ',$report['status'])) ?></span></a><?php endforeach; ?></div>
    </section>
    <section class="card panel-card citizen-emergency">
      <div class="card-header"><div><span class="card-eyebrow">LLAMADA DIRECTA</span><h2>Contactos de emergencia</h2><p>Disponibles para tu comuna.</p></div></div>
      <div><?php foreach($contacts as $contact): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/','',$contact['phone'])) ?>"><span><strong><?= e($contact['service']) ?></strong><small><?= e($contact['name']) ?><?= $contact['available_24h']?' · 24/7':'' ?></small></span><b><?= e($contact['phone']) ?></b></a><?php endforeach; ?></div>
    </section>
  </div>

  <nav class="citizen-bottom-nav" aria-label="Navegación de la app"><a class="active" href="<?= url('mi-app') ?>"><?= svg_icon('dashboard') ?><span>Inicio</span></a><a href="<?= url('reportes/nuevo') ?>"><?= svg_icon('plus') ?><span>Denunciar</span></a><a href="<?= url('mascotas') ?>"><?= svg_icon('paw') ?><span>Mascotas</span></a><a href="<?= url('dispositivos') ?>"><?= svg_icon('device') ?><span>Seguridad</span></a></nav>
</div>
