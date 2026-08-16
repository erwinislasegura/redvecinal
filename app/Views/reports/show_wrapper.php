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
