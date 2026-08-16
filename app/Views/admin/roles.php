<div class="admin-toolbar">
  <div><span class="section-kicker">SEGURIDAD Y ACCESO</span><h2>Roles y permisos</h2><p>Define las funciones disponibles para cada tipo de usuario.</p></div>
</div>
<div class="notice-card"><?= svg_icon('shield') ?><div><strong>Permisos protegidos</strong><p>El superadministrador conserva acceso total. Los cambios en los demás roles se aplican inmediatamente.</p></div></div>
<div class="role-grid">
<?php foreach($roles as $role): ?>
  <form class="card panel-card role-card" method="post" action="<?= url('administracion/roles/'.$role['id']) ?>"><?= csrf_field() ?>
    <div class="card-header"><div class="role-title"><span><?= svg_icon('shield') ?></span><div><h2><?= e($role['name']) ?></h2><p><?= e($role['description']) ?></p></div></div><span class="role-code"><?= e($role['slug']) ?></span></div>
    <div class="card-body permission-list">
      <?php $last=''; foreach($permissions as $permission): ?>
        <?php if($last!==$permission['module']): $last=$permission['module']; ?><h3><?= e(ucfirst($last)) ?></h3><?php endif; ?>
        <label class="permission-option"><span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['slug']) ?></small></span><input type="checkbox" name="permissions[]" value="<?= $permission['id'] ?>" <?= in_array((int)$permission['id'],$map[$role['id']]??[],true)||$role['slug']==='superadmin'?'checked':'' ?> <?= $role['slug']==='superadmin'?'disabled':'' ?>><i></i></label>
      <?php endforeach; ?>
    </div>
    <?php if($role['slug']!=='superadmin'): ?><div class="card-footer"><span>Los cambios afectarán a todos los usuarios del rol.</span><button class="btn btn-primary btn-sm"><?= svg_icon('check') ?> Guardar cambios</button></div><?php endif; ?>
  </form>
<?php endforeach; ?>
</div>
