<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/config/conexion.php';

if (!empty($_SESSION['id_usuario'])) {
    redirigir('index.php');
}

$error = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $clave  = (string) ($_POST['clave'] ?? '');

    if (!csrf_valido()) {
        $error = 'Sesión expirada. Recargue la página e intente nuevamente.';
    } elseif ($correo === '' || $clave === '') {
        $error = 'Ingrese correo y contraseña.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } else {
        try {
            $st = obtener_conexion()->prepare(
                'SELECT id_usuario, nombre, clave, estado FROM usuarios WHERE correo = :correo'
            );
            $st->execute([':correo' => $correo]);
            $u = $st->fetch();

            if (!$u || !password_verify($clave, $u['clave'])) {
                $error = 'Credenciales incorrectas.';
            } elseif ((int) $u['estado'] !== 1) {
                $error = 'Su usuario está inactivo. Contacte al administrador.';
            } else {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = (int) $u['id_usuario'];
                $_SESSION['nombre']     = $u['nombre'];
                flash('success', 'Bienvenido/a, ' . $u['nombre'] . '.');
                redirigir('index.php');
            }
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $error = 'Error interno al iniciar sesión. Intente más tarde.';
        }
    }
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center mt-5">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 text-center mb-1"><i class="bi bi-box-seam"></i> TecnoStock</h1>
        <p class="text-center text-muted">Acceso al sistema de inventario</p>
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <?= csrf_campo() ?>
          <div class="mb-3">
            <label for="correo" class="form-label">Correo</label>
            <input type="email" class="form-control" id="correo" name="correo" value="<?= e($correo) ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label for="clave" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="clave" name="clave" required>
          </div>
          <button class="btn btn-primary w-100" type="submit">Ingresar</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
