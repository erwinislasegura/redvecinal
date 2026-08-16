<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$installed = file_exists($root . '/config/database.php');
$errors = [];
$success = false;

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $data = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'port' => trim($_POST['db_port'] ?? '3306'),
        'database' => trim($_POST['db_name'] ?? ''),
        'username' => trim($_POST['db_user'] ?? ''),
        'password' => (string) ($_POST['db_password'] ?? ''),
        'charset' => 'utf8mb4',
    ];
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = mb_strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $communeName = trim($_POST['commune_name'] ?? '');
    $regionName = trim($_POST['region_name'] ?? 'Región del Biobío');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');

    if (!$data['database'] || !$data['username']) $errors[] = 'Completa los datos de MySQL.';
    if (!$adminName || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ingresa los datos válidos del administrador.';
    if (strlen($adminPassword) < 10) $errors[] = 'La contraseña debe tener al menos 10 caracteres.';
    if (!$communeName) $errors[] = 'Indica la comuna inicial.';

    if (!$errors) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $data['host'], $data['port'], $data['database']),
                $data['username'],
                $data['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]
            );
            $schema = file_get_contents($root . '/database/schema.sql');
            if ($schema === false) throw new RuntimeException('No se encontró database/schema.sql');
            $pdo->exec($schema);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO communes (name,region,status) VALUES (?,?,\'activa\')');
            $stmt->execute([$communeName, $regionName]);
            $communeId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO users (role_id,commune_id,name,email,password,status,email_verified_at) VALUES (1,?,?,?,?, 'activo', NOW())");
            $stmt->execute([$communeId, $adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $pdo->commit();

            $configContent = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($data, true) . ";\n";
            if (file_put_contents($root . '/config/database.php', $configContent, LOCK_EX) === false) {
                throw new RuntimeException('No se pudo escribir config/database.php. Revisa los permisos de la carpeta config.');
            }

            $appFile = $root . '/config/app.php';
            $appContent = file_get_contents($appFile);
            if ($appContent !== false && $baseUrl !== '') {
                $appContent = preg_replace("/'base_url'\s*=>\s*'[^']*'/", "'base_url' => '" . addslashes($baseUrl) . "'", $appContent, 1);
                file_put_contents($appFile, $appContent, LOCK_EX);
            }
            file_put_contents($root . '/storage/installed.lock', date(DATE_ATOM) . PHP_EOL, LOCK_EX);
            $success = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'No se pudo completar la instalación: ' . $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalación | RedVecinal</title><link rel="stylesheet" href="../public/assets/css/bootstrap.min.css"><link rel="stylesheet" href="../public/assets/css/app.css"></head>
<body class="install-page"><main class="container py-5"><div class="install-shell mx-auto">
<div class="text-center mb-4"><div class="brand-mark mx-auto mb-3">RV</div><h1 class="h2 mb-1">Instalar RedVecinal</h1><p class="text-muted">Configuración inicial para servidor cPanel</p></div>
<?php if ($installed || $success): ?>
<div class="card border-0 shadow-sm"><div class="card-body p-4 text-center"><div class="success-icon">✓</div><h2 class="h4">La plataforma está instalada</h2><p>Ya puedes ingresar y comenzar a configurar tu comuna.</p><a class="btn btn-primary" href="../ingresar">Ir a RedVecinal</a><p class="small text-muted mt-3 mb-0">Por seguridad, elimina o renombra la carpeta <strong>install</strong>.</p></div></div>
<?php else: ?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Revisa lo siguiente:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card border-0 shadow-sm"><div class="card-body p-4">
<h2 class="section-title">1. Base de datos MySQL</h2><div class="row g-3">
<div class="col-md-8"><label class="form-label">Servidor</label><input class="form-control" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div>
<div class="col-md-4"><label class="form-label">Puerto</label><input class="form-control" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>" required></div>
<div class="col-md-6"><label class="form-label">Nombre base de datos</label><input class="form-control" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></div>
<div class="col-md-6"><label class="form-label">Usuario MySQL</label><input class="form-control" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></div>
<div class="col-12"><label class="form-label">Contraseña MySQL</label><input type="password" class="form-control" name="db_password"></div></div>
<hr><h2 class="section-title">2. Comuna y dirección</h2><div class="row g-3">
<div class="col-md-6"><label class="form-label">Comuna inicial</label><input class="form-control" name="commune_name" value="<?= h($_POST['commune_name'] ?? 'Los Ángeles') ?>" required></div>
<div class="col-md-6"><label class="form-label">Región</label><input class="form-control" name="region_name" value="<?= h($_POST['region_name'] ?? 'Región del Biobío') ?>" required></div>
<div class="col-12"><label class="form-label">URL de instalación</label><input type="url" class="form-control" name="base_url" value="<?= h($_POST['base_url'] ?? '') ?>" placeholder="https://tudominio.cl"><div class="form-text">Sin barra final. Si se instala en la raíz, utiliza el dominio.</div></div></div>
<hr><h2 class="section-title">3. Administrador principal</h2><div class="row g-3">
<div class="col-md-6"><label class="form-label">Nombre completo</label><input class="form-control" name="admin_name" value="<?= h($_POST['admin_name'] ?? '') ?>" required></div>
<div class="col-md-6"><label class="form-label">Correo electrónico</label><input type="email" class="form-control" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required></div>
<div class="col-12"><label class="form-label">Contraseña</label><input type="password" minlength="10" class="form-control" name="admin_password" required><div class="form-text">Mínimo 10 caracteres.</div></div></div>
<button class="btn btn-primary w-100 mt-4 py-2" type="submit">Instalar plataforma</button>
</div></form><?php endif; ?></div></main></body></html>

