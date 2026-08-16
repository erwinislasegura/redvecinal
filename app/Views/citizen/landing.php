<?php
$registrationErrors=$registrationErrors??[];
$error=static fn(string $field): string => (string)($registrationErrors[$field]??'');
$invalid=static fn(string $field): string => isset($registrationErrors[$field])?' is-invalid':'';
?>
<section class="neighbor-download-hero compact-hero">
  <div class="container neighbor-hero-grid">
    <div class="neighbor-hero-copy">
      <span class="eyebrow">REDVECINAL PARA VECINOS</span>
      <h1>Tu comunidad y seguridad, siempre contigo</h1>
      <p>Denuncia situaciones, activa alertas de pánico y sigue la respuesta de tu comuna desde el celular.</p>
      <div class="neighbor-hero-actions"><a class="btn btn-danger" href="#registro">Crear cuenta</a><button class="btn btn-light" data-install-app hidden>Instalar aplicación</button><a class="btn btn-outline-light" href="<?= url('ingresar?next=vecinos') ?>">Ya tengo cuenta</a></div>
      <div class="neighbor-trust"><span>✓ Gratuita</span><span>✓ Reportes sin conexión</span><span>✓ Datos protegidos</span></div>
    </div>
    <div class="neighbor-phone"><div class="neighbor-phone-top"><span>9:41</span><b>RedVecinal</b><i></i></div><div class="neighbor-phone-body"><span class="mini-label">MI COMUNIDAD</span><h2>Hola, vecino</h2><div class="phone-panic"><span>!</span><strong>Botón de pánico</strong><small>Alerta crítica con ubicación</small></div><div class="phone-actions"><i>Denunciar</i><i>Reportes</i><i>Mascotas</i><i>Seguridad</i></div><div class="phone-case"><span></span><div><strong>Luminaria apagada</strong><small>En proceso · Hace 12 min</small></div></div></div></div>
  </div>
</section>

<section class="container neighbor-benefits compact-benefits">
  <div class="neighbor-benefit-grid"><article><?= svg_icon('alert') ?><h3>Denuncias</h3><p>Reportes geolocalizados y seguimiento.</p></article><article><?= svg_icon('shield') ?><h3>Alerta de pánico</h3><p>Aviso crítico a la central comunal.</p></article><article><?= svg_icon('paw') ?><h3>Mascotas</h3><p>Búsqueda y ayuda comunitaria.</p></article><article><?= svg_icon('device') ?><h3>Hogar conectado</h3><p>Cámaras, alarmas y sensores.</p></article></div>
</section>

<section class="neighbor-register-section" id="registro">
  <div class="container neighbor-register-grid compact-register-grid">
    <aside class="neighbor-register-copy">
      <span class="eyebrow">REGISTRO SEGURO</span><h2>Crea tu cuenta</h2><p>Solo solicitamos datos necesarios para asociar una emergencia con tu ubicación y comuna.</p>
      <div class="registration-steps"><span><b>1</b> Identificación</span><span><b>2</b> Domicilio</span><span><b>3</b> Contacto y acceso</span></div>
      <div class="privacy-note"><?= svg_icon('shield') ?><span><strong>Información protegida</strong><small>Tu contacto de emergencia no es público.</small></span></div>
    </aside>

    <form class="neighbor-register-form compact-register-form" method="post" action="<?= url('descargar-vecinos/registro') ?>">
      <?= csrf_field() ?>
      <div class="form-heading"><span>CUENTA VECINAL</span><h2>Datos personales y de seguridad</h2><p>Los campos con * son obligatorios.</p></div>

      <?php if($registrationErrors): ?><div class="registration-error-summary" role="alert"><strong>No pudimos crear la cuenta</strong><ul><?php foreach($registrationErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <fieldset><legend>Identificación</legend><div class="row g-2">
        <div class="col-lg-4 col-md-6"><label class="form-label">Nombre completo *</label><input class="form-control<?= $invalid('name') ?>" name="name" value="<?= e(old('name')) ?>" autocomplete="name" required><?php if($error('name')): ?><div class="invalid-feedback"><?= e($error('name')) ?></div><?php endif; ?></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">RUT *</label><input class="form-control<?= $invalid('rut') ?>" name="rut" value="<?= e(old('rut')) ?>" placeholder="12.345.678-5" inputmode="text" data-rut required><?php if($error('rut')): ?><div class="invalid-feedback"><?= e($error('rut')) ?></div><?php endif; ?></div>
        <div class="col-lg-5 col-md-6"><label class="form-label">Correo electrónico *</label><input type="email" class="form-control<?= $invalid('email') ?>" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required><?php if($error('email')): ?><div class="invalid-feedback"><?= e($error('email')) ?></div><?php endif; ?></div>
        <div class="col-lg-4 col-md-6"><label class="form-label">Teléfono móvil *</label><input type="tel" class="form-control<?= $invalid('phone') ?>" name="phone" value="<?= e(old('phone')) ?>" placeholder="+56 9 1234 5678" autocomplete="tel" inputmode="tel" required><?php if($error('phone')): ?><div class="invalid-feedback"><?= e($error('phone')) ?></div><?php endif; ?></div>
      </div></fieldset>

      <fieldset><legend>Domicilio y cobertura</legend><div class="row g-2">
        <div class="col-lg-4 col-md-6"><label class="form-label">Comuna *</label><select class="form-select<?= $invalid('commune_id') ?>" name="commune_id" data-neighbor-commune required><option value="">Seleccionar</option><?php foreach($communes as $commune): ?><option value="<?= (int)$commune['id'] ?>" <?= (string)old('commune_id')===(string)$commune['id']?'selected':'' ?>><?= e($commune['name']) ?> · <?= e($commune['region']) ?></option><?php endforeach; ?></select><?php if($error('commune_id')): ?><div class="invalid-feedback"><?= e($error('commune_id')) ?></div><?php endif; ?></div>
        <div class="col-lg-4 col-md-6"><label class="form-label">Sector o villa</label><select class="form-select<?= $invalid('sector_id') ?>" name="sector_id" data-neighbor-sector><option value="">Sin sector definido</option><?php foreach($sectors as $sector): ?><option value="<?= (int)$sector['id'] ?>" data-commune="<?= (int)$sector['commune_id'] ?>" <?= (string)old('sector_id')===(string)$sector['id']?'selected':'' ?>><?= e($sector['name']) ?></option><?php endforeach; ?></select><?php if($error('sector_id')): ?><div class="invalid-feedback"><?= e($error('sector_id')) ?></div><?php endif; ?></div>
        <div class="col-lg-4"><label class="form-label">Dirección completa *</label><input class="form-control<?= $invalid('address') ?>" name="address" value="<?= e(old('address')) ?>" placeholder="Calle y número" autocomplete="street-address" required><?php if($error('address')): ?><div class="invalid-feedback"><?= e($error('address')) ?></div><?php endif; ?></div>
      </div></fieldset>

      <fieldset><legend>Contacto de emergencia</legend><div class="row g-2">
        <div class="col-lg-4 col-md-5"><label class="form-label">Nombre *</label><input class="form-control<?= $invalid('emergency_name') ?>" name="emergency_name" value="<?= e(old('emergency_name')) ?>" required><?php if($error('emergency_name')): ?><div class="invalid-feedback"><?= e($error('emergency_name')) ?></div><?php endif; ?></div>
        <div class="col-lg-3 col-md-3"><label class="form-label">Relación *</label><input class="form-control<?= $invalid('emergency_relationship') ?>" name="emergency_relationship" value="<?= e(old('emergency_relationship')) ?>" placeholder="Familiar" required><?php if($error('emergency_relationship')): ?><div class="invalid-feedback"><?= e($error('emergency_relationship')) ?></div><?php endif; ?></div>
        <div class="col-lg-5 col-md-4"><label class="form-label">Teléfono *</label><input type="tel" class="form-control<?= $invalid('emergency_phone') ?>" name="emergency_phone" value="<?= e(old('emergency_phone')) ?>" placeholder="+56 9 1234 5678" inputmode="tel" required><?php if($error('emergency_phone')): ?><div class="invalid-feedback"><?= e($error('emergency_phone')) ?></div><?php endif; ?></div>
      </div></fieldset>

      <fieldset><legend>Contraseña</legend><div class="row g-2">
        <div class="col-md-6"><label class="form-label">Contraseña *</label><input type="password" minlength="8" class="form-control<?= $invalid('password') ?>" name="password" autocomplete="new-password" required><?php if($error('password')): ?><div class="invalid-feedback"><?= e($error('password')) ?></div><?php else: ?><small>Mínimo 8 caracteres.</small><?php endif; ?></div>
        <div class="col-md-6"><label class="form-label">Confirmar *</label><input type="password" minlength="8" class="form-control<?= $invalid('password_confirmation') ?>" name="password_confirmation" autocomplete="new-password" required><?php if($error('password_confirmation')): ?><div class="invalid-feedback"><?= e($error('password_confirmation')) ?></div><?php endif; ?></div>
      </div></fieldset>

      <div class="register-actions"><label class="neighbor-consent<?= $invalid('terms') ?>"><input type="checkbox" name="terms" value="1" <?= old('terms')?'checked':'' ?> required><span>Autorizo el uso de estos datos para gestionar alertas asociadas a mi cuenta.</span></label><?php if($error('terms')): ?><div class="terms-error"><?= e($error('terms')) ?></div><?php endif; ?><button class="btn btn-danger">Crear cuenta e ingresar</button></div>
      <p class="form-login">¿Ya estás registrado? <a href="<?= url('ingresar?next=vecinos') ?>">Ingresar a la app</a></p>
    </form>
  </div>
</section>
