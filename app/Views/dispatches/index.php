<?php
$serviceLabels=['seguridad_municipal'=>'Seguridad municipal','carabineros'=>'Carabineros','bomberos'=>'Bomberos','ambulancia'=>'Ambulancia / SAMU','transito'=>'Tránsito','aseo'=>'Aseo y ornato','alumbrado'=>'Alumbrado público','otro'=>'Otro'];
$statusLabels=['solicitado'=>'Solicitado','aceptado'=>'Aceptado','en_camino'=>'En camino','en_sitio'=>'En el lugar','finalizado'=>'Finalizado','cancelado'=>'Cancelado'];
?>
<div class="dispatch-summary">
  <?php foreach(['solicitado','aceptado','en_camino','en_sitio'] as $key): ?>
    <article class="dispatch-stat status-<?= e($key) ?>"><span><?= svg_icon($key==='en_camino'?'truck':'clock') ?></span><div><strong><?= (int)$summary[$key] ?></strong><small><?= e($statusLabels[$key]) ?></small></div></article>
  <?php endforeach; ?>
</div>

<div class="card dispatch-filter-card mb-3">
  <form class="dispatch-filters" method="get">
    <div><label class="form-label">Buscar</label><input class="form-control" name="search" value="<?= e($filters['search']) ?>" placeholder="Código, reporte, unidad o contacto"></div>
    <div><label class="form-label">Servicio</label><select class="form-select" name="service"><option value="">Todos los servicios</option><?php foreach($serviceLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['service']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label">Estado</label><select class="form-select" name="status"><option value="">Todos los estados</option><?php foreach($statusLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary">Filtrar</button><a class="btn btn-light" href="<?= url('despachos') ?>">Limpiar</a>
  </form>
</div>

<div class="card table-card"><div class="table-responsive"><table class="table dispatch-table"><thead><tr><th>Despacho</th><th>Reporte</th><th>Servicio / unidad</th><th>Ubicación</th><th>Solicitado</th><th>Estado operativo</th></tr></thead><tbody>
<?php if(!$dispatches): ?><tr><td colspan="6" class="empty-state">No hay despachos para los filtros seleccionados. Para crear uno, abre un reporte y usa “Despachar servicio”.</td></tr><?php endif; ?>
<?php foreach($dispatches as $item): ?><tr>
  <td><strong>#<?= (int)$item['id'] ?></strong><small class="d-block text-muted">por <?= e($item['creator_name']) ?></small></td>
  <td><a href="<?= url('reportes/'.$item['report_id']) ?>"><strong><?= e($item['public_code']) ?></strong></a><span class="d-block dispatch-title"><?= e($item['title']) ?></span><span class="priority priority-<?= e($item['priority']) ?>"><?= e($item['priority']) ?></span></td>
  <td><strong><?= e($serviceLabels[$item['service']]??$item['service']) ?></strong><small class="d-block text-muted"><?= e($item['unit_name']?:'Unidad no indicada') ?><?= $item['contact_name']?' · '.e($item['contact_name']):'' ?></small></td>
  <td><?= e($item['address']?:$item['commune_name']) ?><small class="d-block text-muted"><?= e($item['commune_name']) ?></small></td>
  <td><?= e(date('d/m/Y',strtotime($item['requested_at']))) ?><small class="d-block text-muted"><?= e(date('H:i',strtotime($item['requested_at']))) ?> h</small></td>
  <td><form class="dispatch-status-form" method="post" action="<?= url('despachos/'.$item['id'].'/estado') ?>"><?= csrf_field() ?><span class="badge bg-<?= dispatch_badge($item['status']) ?>"><?= e($statusLabels[$item['status']]??$item['status']) ?></span><select class="form-select form-select-sm" name="status" onchange="this.form.submit()" aria-label="Cambiar estado del despacho"><?php foreach($statusLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $item['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></form></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
