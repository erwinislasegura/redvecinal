<div class="form-layout">
  <form id="reportForm" class="card" method="post" enctype="multipart/form-data" action="<?= url('reportes') ?>" data-offline-form>
    <?= csrf_field() ?>
    <div class="card-header"><div><h2>¿Qué está ocurriendo?</h2><p>Entrega información clara para coordinar una respuesta adecuada.</p></div></div>
    <div class="card-body">
      <label class="form-label">Tipo de reporte</label>
      <div class="report-type-grid">
        <?php foreach($types as $type): ?>
          <label class="type-option"><input type="radio" name="report_type_id" value="<?= $type['id'] ?>" data-priority="<?= e($type['priority_default']) ?>" required><span style="--type-color:<?= e($type['color']) ?>"><i><?= e(mb_strtoupper(mb_substr($type['name'],0,1))) ?></i><span><strong><?= e($type['name']) ?></strong><small><?= e(ucfirst($type['category'])) ?></small></span></span></label>
        <?php endforeach; ?>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-8"><label class="form-label">Título breve</label><input class="form-control" maxlength="160" name="title" value="<?= e(old('title')) ?>" required placeholder="Ej.: Luminaria apagada frente a la plaza"></div>
        <div class="col-md-4"><label class="form-label">Prioridad</label><select class="form-select" name="priority" id="priority"><option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="critica">Crítica</option></select></div>
        <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" rows="4" name="description" required placeholder="Describe lo ocurrido, referencias y detalles útiles..."><?= e(old('description')) ?></textarea></div>
        <div class="col-md-8"><label class="form-label">Dirección o referencia</label><input class="form-control" name="address" value="<?= e(old('address',auth()['address']??'')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Sector</label><select class="form-select" name="sector_id"><option value="">Sin sector</option><?php foreach($sectors as $sector): ?><option value="<?= $sector['id'] ?>"><?= e($sector['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Fecha y hora del hecho</label><input type="datetime-local" class="form-control" name="happened_at"></div>
        <div class="col-md-6 d-flex align-items-end"><button type="button" class="btn btn-outline-primary w-100" data-geolocate>◎ Usar mi ubicación actual</button></div>
        <input type="hidden" name="latitude"><input type="hidden" name="longitude">
        <div class="col-12"><label class="form-label">Foto o video de evidencia</label><input type="file" class="form-control" name="evidence" accept="image/jpeg,image/png,image/webp,video/mp4"><div class="form-text">Opcional. JPG, PNG, WEBP o MP4, máximo <?= (int)config('uploads_max_mb',8) ?> MB. Los archivos no se guardan en el modo sin conexión.</div></div>
        <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_anonymous" value="1"><span>Ocultar mi identidad frente a otros vecinos</span></label></div>
      </div>
    </div>
    <div class="card-footer"><span class="offline-note">También puedes enviar este formulario sin conexión; quedará en espera.</span><button class="btn btn-danger btn-lg">Enviar reporte</button></div>
  </form>
  <aside class="tips-card"><h3>Antes de reportar</h3><ul><li>Si existe riesgo vital, llama directamente al servicio de emergencia correspondiente.</li><li>No te expongas ni enfrentes a personas sospechosas.</li><li>Describe hechos verificables y evita publicar datos sensibles.</li><li>Tu ubicación ayuda a los operadores a responder con mayor rapidez.</li></ul><div class="emergency-call"><strong>Emergencia inmediata</strong><span>Carabineros 133 · Bomberos 132 · SAMU 131</span></div></aside>
</div>
