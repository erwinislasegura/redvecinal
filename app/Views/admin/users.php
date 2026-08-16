<div class="admin-toolbar">
  <div><span class="section-kicker">CONTROL DE ACCESO</span><h2>Gestión de usuarios</h2><p>Administra vecinos, operadores, organismos y autoridades comunales.</p></div>
  <button class="btn btn-primary" data-modal-open="userModal"><?= svg_icon('plus') ?> Crear usuario</button>
</div>

<section class="card panel-card table-card">
  <div class="table-toolbar">
    <div class="search-control"><?= svg_icon('users') ?><input type="search" placeholder="Buscar usuario..." data-table-search="usersTable"></div>
    <span class="result-count"><?= count($users) ?> usuarios registrados</span>
  </div>
  <div class="table-responsive">
    <table class="table admin-table" id="usersTable">
      <thead><tr><th>Usuario</th><th>Rol asignado</th><th>Comuna</th><th>Contacto</th><th>Estado</th><th>Último ingreso</th></tr></thead>
      <tbody>
      <?php foreach($users as $user): ?>
        <tr>
          <td><div class="user-cell"><span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr($user['name'],0,1))) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['email']) ?></small></div></div></td>
          <td><span class="role-chip"><?= e($user['role_name']) ?></span></td>
          <td><strong class="cell-primary"><?= e($user['commune_name'] ?: 'Administración global') ?></strong></td>
          <td><span class="cell-muted"><?= e($user['phone'] ?: 'Sin teléfono') ?></span></td>
          <td><form method="post" action="<?= url('administracion/usuarios/'.$user['id'].'/estado') ?>"><?= csrf_field() ?><select class="form-select form-select-sm status-select status-<?= e($user['status']) ?>" name="status" onchange="this.form.submit()" <?= $user['id']==auth()['id']?'disabled':'' ?>><option value="activo" <?= $user['status']==='activo'?'selected':'' ?>>Activo</option><option value="pendiente" <?= $user['status']==='pendiente'?'selected':'' ?>>Pendiente</option><option value="suspendido" <?= $user['status']==='suspendido'?'selected':'' ?>>Suspendido</option></select></form></td>
          <td><span class="cell-muted"><?= $user['last_login_at'] ? e(date('d/m/Y · H:i',strtotime($user['last_login_at']))) : 'Nunca' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="userModal" hidden><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><div><span class="section-kicker">NUEVO ACCESO</span><h2>Crear usuario</h2><p>Completa los datos y asigna el nivel de acceso.</p></div><button data-modal-close aria-label="Cerrar">×</button></div>
  <form method="post" action="<?= url('administracion/usuarios') ?>"><?= csrf_field() ?>
    <div class="modal-body"><div class="form-section"><h3>Información personal</h3><div class="row g-3"><div class="col-md-6"><label class="form-label">Nombre completo <em>*</em></label><input class="form-control" name="name" placeholder="Nombre y apellido" required></div><div class="col-md-6"><label class="form-label">Correo electrónico <em>*</em></label><input type="email" class="form-control" name="email" placeholder="nombre@correo.cl" required></div><div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" name="phone" placeholder="+56 9 0000 0000"></div><div class="col-md-6"><label class="form-label">Rol asignado <em>*</em></label><select class="form-select" name="role_id"><?php foreach($roles as $role): ?><option value="<?= $role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></div></div></div>
    <div class="form-section"><h3>Acceso a la plataforma</h3><div class="row g-3"><?php if(auth()['role_slug']==='superadmin'): ?><div class="col-md-6"><label class="form-label">Comuna <em>*</em></label><select class="form-select" name="commune_id"><?php foreach($communes as $commune): ?><option value="<?= $commune['id'] ?>"><?= e($commune['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="<?= auth()['role_slug']==='superadmin'?'col-md-6':'col-12' ?>"><label class="form-label">Contraseña temporal <em>*</em></label><input type="password" minlength="8" class="form-control" name="password" placeholder="Mínimo 8 caracteres" required></div></div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancelar</button><button class="btn btn-primary"><?= svg_icon('check') ?> Crear usuario</button></div>
  </form>
</div></div></div>

