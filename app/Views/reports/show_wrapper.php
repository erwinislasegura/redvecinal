<?php if(!empty($isPanic)&&can('reports.manage')): ?>
<section class="card report-live-tracking" id="seguimiento-panico" data-report-live-tracking="<?= (int)$report['id'] ?>" data-initial-lat="<?= e((string)($report['latitude']??'')) ?>" data-initial-lng="<?= e((string)($report['longitude']??'')) ?>">
  <div class="report-live-head"><div class="report-live-title"><span class="report-live-siren">!</span><div><small>EMERGENCIA · GPS EN TIEMPO REAL</small><h2>Seguimiento del botón de pánico</h2><p>La posición y el recorrido se actualizan automáticamente cada 5 segundos.</p></div></div><div class="report-live-state" data-report-live-state><i></i><div><strong>Conectando con el dispositivo</strong><small data-report-live-time>Esperando la última posición…</small></div></div></div>
  <div class="report-live-map" id="reportLiveTrackingMap" aria-label="Mapa de seguimiento GPS en tiempo real"></div>
  <div class="report-live-meta"><div><small>Última señal</small><strong data-report-live-last>—</strong></div><div><small>Precisión GPS</small><strong data-report-live-accuracy>—</strong></div><div><small>Puntos del recorrido</small><strong data-report-live-points>0</strong></div><div><small>Coordenadas actuales</small><strong data-report-live-coordinates>—</strong></div></div>
</section>
<?php endif; ?>
<?php require BASE_PATH . '/app/Views/reports/show.php'; ?>
<?php if($media): ?>
<section class="card mt-4">
  <div class="card-header"><div><h2>Evidencia adjunta</h2><p>Archivos protegidos asociados al reporte</p></div></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.8rem;padding:1rem">
    <?php foreach($media as $item): ?>
      <?php if($item['file_type']==='imagen'): ?>
        <a target="_blank" href="<?= url('reportes/media/'.$item['id']) ?>"><img style="width:100%;height:220px;object-fit:cover;border-radius:9px" src="<?= url('reportes/media/'.$item['id']) ?>" alt="Evidencia del reporte"></a>
      <?php else: ?>
        <video style="width:100%;height:220px;border-radius:9px;background:#111" controls preload="metadata"><source src="<?= url('reportes/media/'.$item['id']) ?>" type="<?= e($item['mime_type']) ?>"></video>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
