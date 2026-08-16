<div class="admin-toolbar">
  <div><span class="section-kicker">TRAZABILIDAD</span><h2>Registro de auditoría</h2><p>Consulta quién realizó cada acción y cuándo ocurrió.</p></div>
  <span class="audit-summary"><?= count($logs) ?> eventos encontrados</span>
</div>

<section class="card panel-card audit-filter-card">
  <form method="get" class="audit-filters">
    <div><label class="form-label">Acción</label><select class="form-select" name="action"><option value="">Todas las acciones</option><?php foreach($actions as $item): ?><option value="<?= e($item) ?>" <?= $action===$item?'selected':'' ?>><?= e(str_replace(['.','_'],' ',$item)) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label">Entidad</label><select class="form-select" name="entity"><option value="">Todas</option><?php foreach(['user'=>'Usuario','report'=>'Reporte','role'=>'Rol','commune'=>'Comuna','sector'=>'Sector','pet'=>'Mascota','device'=>'Dispositivo','emergency_contact'=>'Contacto'] as $value=>$label): ?><option value="<?= $value ?>" <?= $entity===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label">Desde</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div><label class="form-label">Hasta</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-primary">Aplicar filtros</button>
    <a class="btn btn-light" href="<?= url('administracion/auditoria') ?>">Limpiar</a>
  </form>
</section>

<section class="card panel-card table-card mt-3">
  <div class="table-responsive"><table class="table admin-table audit-table"><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Dirección IP</th><th>Detalle</th></tr></thead><tbody>
  <?php if(!$logs): ?><tr><td colspan="6" class="empty-state compact">No existen eventos para los filtros seleccionados.</td></tr><?php endif; ?>
  <?php foreach($logs as $log): ?>
    <tr>
      <td><strong class="cell-primary"><?= e(date('d/m/Y',strtotime($log['created_at']))) ?></strong><small class="d-block text-muted"><?= e(date('H:i:s',strtotime($log['created_at']))) ?></small></td>
      <td><div class="user-cell"><span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr($log['user_name']??'S',0,1))) ?></span><div><strong><?= e($log['user_name']??'Sistema') ?></strong><small><?= e($log['role_name']??'Acción automática') ?></small></div></div></td>
      <td><span class="audit-action"><?= e(str_replace(['.','_'],' ',$log['action'])) ?></span></td>
      <td><span class="role-chip"><?= e($log['entity_type']??'sistema') ?><?= $log['entity_id']?' #'.e($log['entity_id']):'' ?></span></td>
      <td><code class="ip-code"><?= e($log['ip_address']?:'—') ?></code></td>
      <td><?php if($log['old_values_json']||$log['new_values_json']): ?><details class="audit-detail"><summary>Ver cambios</summary><?php if($log['old_values_json']): ?><small>Antes</small><pre><?= e(json_encode(json_decode($log['old_values_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?><?php if($log['new_values_json']): ?><small>Después</small><pre><?= e(json_encode(json_decode($log['new_values_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?></details><?php else: ?><span class="cell-muted">Sin cambios de datos</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>

