<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/conexion.php';

$pdo = obtener_conexion();
$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ctype_digit((string) ($_POST['id'] ?? ''))) {
    $id = (int) $_POST['id'];
}
$esNuevo = $id === 0;
$errores = [];
$datos = ['codigo' => '', 'nombre' => '', 'descripcion' => '', 'precio' => '', 'stock_actual' => '0', 'stock_minimo' => '0', 'id_categoria' => ''];
$original = null;

try {
    if (!$esNuevo) {
        $st = $pdo->prepare('SELECT * FROM productos WHERE id_producto = :id');
        $st->execute([':id' => $id]);
        $original = $st->fetch();
        if (!$original) {
            flash('danger', 'El producto solicitado no existe.');
            redirigir('index.php');
        }
        if ((int) $original['estado'] !== 1) {
            flash('warning', 'El producto está inactivo. Reactívelo antes de editarlo.');
            redirigir('index.php');
        }
        $datos = array_merge($datos, array_map('strval', $original));
    }
    $categorias = $pdo->query('SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre')->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    exit('Error al cargar el formulario.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        flash('danger', 'Sesión expirada. Intente nuevamente.');
        redirigir('index.php');
    }
    [$errores, $datos] = validar_producto($_POST);

    try {
        // Regla 1: código único (validado antes de insertar/editar; la UNIQUE de MySQL es la red de seguridad).
        if (!isset($errores['codigo'])) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE codigo = :c AND id_producto <> :id');
            $st->execute([':c' => $datos['codigo'], ':id' => $id]);
            if ((int) $st->fetchColumn() > 0) {
                $errores['codigo'] = 'Ya existe un producto con ese código.';
            }
        }
        if (!isset($errores['id_categoria'])) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id_categoria = :c AND estado = 1');
            $st->execute([':c' => (int) $datos['id_categoria']]);
            if ((int) $st->fetchColumn() === 0) {
                $errores['id_categoria'] = 'La categoría seleccionada no es válida.';
            }
        }

        if (!$errores) {
            $pdo->beginTransaction();
            $idUsuario = (int) $_SESSION['id_usuario'];
            $stockNuevo = (int) $datos['stock_actual'];

            if ($esNuevo) {
                // El producto nace con stock 0 y el stock inicial se registra como entrada (trazabilidad).
                $st = $pdo->prepare(
                    'INSERT INTO productos (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria)
                     VALUES (:codigo, :nombre, :descripcion, :precio, 0, :minimo, 1, :cat)'
                );
                $st->execute([
                    ':codigo' => $datos['codigo'], ':nombre' => $datos['nombre'], ':descripcion' => $datos['descripcion'],
                    ':precio' => $datos['precio'], ':minimo' => (int) $datos['stock_minimo'], ':cat' => (int) $datos['id_categoria'],
                ]);
                $id = (int) $pdo->lastInsertId();
                $dif = $stockNuevo;
            } else {
                // Se bloquea la fila para calcular la diferencia contra el stock real.
                $st = $pdo->prepare('SELECT stock_actual FROM productos WHERE id_producto = :id FOR UPDATE');
                $st->execute([':id' => $id]);
                $stockBD = (int) $st->fetchColumn();
                $dif = $stockNuevo - $stockBD;

                $st = $pdo->prepare(
                    'UPDATE productos SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion,
                            precio = :precio, stock_minimo = :minimo, id_categoria = :cat WHERE id_producto = :id'
                );
                $st->execute([
                    ':codigo' => $datos['codigo'], ':nombre' => $datos['nombre'], ':descripcion' => $datos['descripcion'],
                    ':precio' => $datos['precio'], ':minimo' => (int) $datos['stock_minimo'], ':cat' => (int) $datos['id_categoria'],
                    ':id' => $id,
                ]);
            }

            // Si cambió el stock (stock inicial o ajuste), se deja registro como movimiento.
            if ($dif !== 0) {
                $tipo = $dif > 0 ? 'entrada' : 'salida';
                $obs  = $esNuevo ? 'Stock inicial al crear el producto' : 'Ajuste manual desde edición de producto';
                $st = $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + :d WHERE id_producto = :id');
                $st->execute([':d' => $dif, ':id' => $id]);
                $st = $pdo->prepare(
                    'INSERT INTO movimientos (fecha_hora, tipo, cantidad, observacion, id_producto, id_usuario)
                     VALUES (NOW(), :tipo, :cant, :obs, :p, :u)'
                );
                $st->execute([':tipo' => $tipo, ':cant' => abs($dif), ':obs' => $obs, ':p' => $id, ':u' => $idUsuario]);
            }
            $pdo->commit();
            flash('success', $esNuevo ? 'Producto creado correctamente.' : 'Producto actualizado correctamente.');
            redirigir('index.php');
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '23000') {
            $errores['codigo'] = 'Ya existe un producto con ese código.';
        } else {
            error_log($ex->getMessage());
            $errores['general'] = 'Ocurrió un error al guardar. No se realizaron cambios.';
        }
    }
}

$titulo = $esNuevo ? 'Nuevo producto' : 'Editar producto';
require __DIR__ . '/includes/header.php';

function campo_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<div class="invalid-feedback d-block">' . e($errores[$campo]) . '</div>' : '';
}
?>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h4 mb-3"><?= e($titulo) ?></h1>
        <?php if (isset($errores['general'])): ?><div class="alert alert-danger"><?= e($errores['general']) ?></div><?php endif; ?>
        <form method="post" id="form-producto" novalidate>
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="codigo" class="form-label">Código *</label>
              <input type="text" class="form-control <?= isset($errores['codigo']) ? 'is-invalid' : '' ?>" id="codigo" name="codigo" maxlength="30" value="<?= e($datos['codigo']) ?>">
              <?= campo_error($errores, 'codigo') ?>
            </div>
            <div class="col-md-8">
              <label for="nombre" class="form-label">Nombre *</label>
              <input type="text" class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>" id="nombre" name="nombre" maxlength="100" value="<?= e($datos['nombre']) ?>">
              <?= campo_error($errores, 'nombre') ?>
            </div>
            <div class="col-md-6">
              <label for="id_categoria" class="form-label">Categoría *</label>
              <select class="form-select <?= isset($errores['id_categoria']) ? 'is-invalid' : '' ?>" id="id_categoria" name="id_categoria">
                <option value="">Seleccione...</option>
                <?php foreach ($categorias as $c): ?>
                  <option value="<?= (int) $c['id_categoria'] ?>" <?= (string) $c['id_categoria'] === $datos['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
              <?= campo_error($errores, 'id_categoria') ?>
            </div>
            <div class="col-md-6">
              <label for="precio" class="form-label">Precio (CLP) *</label>
              <input type="number" step="0.01" class="form-control <?= isset($errores['precio']) ? 'is-invalid' : '' ?>" id="precio" name="precio" value="<?= e($datos['precio']) ?>">
              <?= campo_error($errores, 'precio') ?>
            </div>
            <div class="col-12">
              <label for="descripcion" class="form-label">Descripción</label>
              <textarea class="form-control <?= isset($errores['descripcion']) ? 'is-invalid' : '' ?>" id="descripcion" name="descripcion" rows="2" maxlength="255"><?= e($datos['descripcion']) ?></textarea>
              <?= campo_error($errores, 'descripcion') ?>
            </div>
            <div class="col-md-6">
              <label for="stock_actual" class="form-label"><?= $esNuevo ? 'Stock inicial *' : 'Stock actual (ajuste) *' ?></label>
              <input type="number" step="1" class="form-control <?= isset($errores['stock_actual']) ? 'is-invalid' : '' ?>" id="stock_actual" name="stock_actual" value="<?= e($datos['stock_actual']) ?>">
              <?= campo_error($errores, 'stock_actual') ?>
              <?php if (!$esNuevo): ?><div class="form-text">Si lo modifica, se registra un movimiento de ajuste automáticamente.</div><?php endif; ?>
            </div>
            <div class="col-md-6">
              <label for="stock_minimo" class="form-label">Stock mínimo *</label>
              <input type="number" step="1" class="form-control <?= isset($errores['stock_minimo']) ? 'is-invalid' : '' ?>" id="stock_minimo" name="stock_minimo" value="<?= e($datos['stock_minimo']) ?>">
              <?= campo_error($errores, 'stock_minimo') ?>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
            <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
