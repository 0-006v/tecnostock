<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/conexion.php';

$pdo = obtener_conexion();
$errores = [];
$datos = [
    'id_producto' => ctype_digit((string) ($_GET['id_producto'] ?? '')) ? (string) $_GET['id_producto'] : '',
    'tipo'        => in_array($_GET['tipo'] ?? '', ['entrada', 'salida'], true) ? (string) $_GET['tipo'] : '',
    'cantidad'    => '',
    'observacion' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        flash('danger', 'Sesión expirada. Intente nuevamente.');
        redirigir('movimientos.php');
    }
    [$errores, $datos] = validar_movimiento($_POST);

    if (!$errores) {
        $idProd   = (int) $datos['id_producto'];
        $cantidad = (int) $datos['cantidad'];
        try {
            $pdo->beginTransaction();

            // Se bloquea la fila del producto: evita que dos salidas simultáneas dejen stock negativo.
            $st = $pdo->prepare('SELECT nombre, stock_actual, estado FROM productos WHERE id_producto = :id FOR UPDATE');
            $st->execute([':id' => $idProd]);
            $p = $st->fetch();

            if (!$p) {
                throw new RuntimeException('El producto seleccionado no existe.');
            }
            if ((int) $p['estado'] !== 1) {
                throw new RuntimeException('El producto está inactivo y no admite nuevos movimientos.');
            }
            if ($datos['tipo'] === 'salida' && $cantidad > (int) $p['stock_actual']) {
                throw new RuntimeException(
                    'Stock insuficiente: la salida de ' . $cantidad . ' supera el stock disponible (' . (int) $p['stock_actual'] . ').'
                );
            }

            $delta = $datos['tipo'] === 'entrada' ? $cantidad : -$cantidad;
            $st = $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + :d WHERE id_producto = :id');
            $st->execute([':d' => $delta, ':id' => $idProd]);

            $st = $pdo->prepare(
                'INSERT INTO movimientos (fecha_hora, tipo, cantidad, observacion, id_producto, id_usuario)
                 VALUES (NOW(), :tipo, :cant, :obs, :prod, :usr)'
            );
            $st->execute([
                ':tipo' => $datos['tipo'], ':cant' => $cantidad, ':obs' => $datos['observacion'],
                ':prod' => $idProd, ':usr' => (int) $_SESSION['id_usuario'],
            ]);

            $pdo->commit();
            flash('success', ucfirst($datos['tipo']) . ' registrada: ' . $cantidad . ' unidad(es) de "' . $p['nombre'] . '".');
            redirigir('movimientos.php');
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores['general'] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($ex->getMessage());
            $errores['general'] = 'Error al registrar el movimiento. No se realizaron cambios.';
        }
    }
}

try {
    $productos = $pdo->query('SELECT id_producto, codigo, nombre, stock_actual FROM productos WHERE estado = 1 ORDER BY nombre')->fetchAll();
    $historial = $pdo->query(
        'SELECT m.fecha_hora, m.tipo, m.cantidad, m.observacion, p.codigo, p.nombre AS producto, u.nombre AS usuario
           FROM movimientos m
           INNER JOIN productos p ON p.id_producto = m.id_producto
           INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
          ORDER BY m.fecha_hora DESC, m.id_movimiento DESC LIMIT 20'
    )->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    exit('Error al cargar los movimientos.');
}

$titulo = 'Movimientos';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h5 mb-3">Registrar movimiento</h1>
        <?php if (isset($errores['general'])): ?><div class="alert alert-danger"><?= e($errores['general']) ?></div><?php endif; ?>
        <form method="post" id="form-movimiento" novalidate>
          <?= csrf_campo() ?>
          <div class="mb-3">
            <label for="id_producto" class="form-label">Producto *</label>
            <select class="form-select <?= isset($errores['id_producto']) ? 'is-invalid' : '' ?>" id="id_producto" name="id_producto">
              <option value="">Seleccione...</option>
              <?php foreach ($productos as $p): ?>
                <option value="<?= (int) $p['id_producto'] ?>" data-stock="<?= (int) $p['stock_actual'] ?>"
                  <?= (string) $p['id_producto'] === $datos['id_producto'] ? 'selected' : '' ?>>
                  <?= e($p['codigo'] . ' - ' . $p['nombre']) ?> (stock: <?= (int) $p['stock_actual'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errores['id_producto'])): ?><div class="invalid-feedback d-block"><?= e($errores['id_producto']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Tipo *</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="tipo" id="tipo_entrada" value="entrada" <?= $datos['tipo'] === 'entrada' ? 'checked' : '' ?>>
              <label class="form-check-label" for="tipo_entrada">Entrada</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="tipo" id="tipo_salida" value="salida" <?= $datos['tipo'] === 'salida' ? 'checked' : '' ?>>
              <label class="form-check-label" for="tipo_salida">Salida</label>
            </div>
            <?php if (isset($errores['tipo'])): ?><div class="invalid-feedback d-block"><?= e($errores['tipo']) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="cantidad" class="form-label">Cantidad *</label>
            <input type="number" step="1" class="form-control <?= isset($errores['cantidad']) ? 'is-invalid' : '' ?>" id="cantidad" name="cantidad" value="<?= e($datos['cantidad']) ?>">
            <?php if (isset($errores['cantidad'])): ?><div class="invalid-feedback d-block"><?= e($errores['cantidad']) ?></div><?php endif; ?>
            <div class="form-text" id="ayuda-stock"></div>
          </div>
          <div class="mb-3">
            <label for="observacion" class="form-label">Observación *</label>
            <input type="text" maxlength="255" class="form-control <?= isset($errores['observacion']) ? 'is-invalid' : '' ?>" id="observacion" name="observacion" value="<?= e($datos['observacion']) ?>">
            <?php if (isset($errores['observacion'])): ?><div class="invalid-feedback d-block"><?= e($errores['observacion']) ?></div><?php endif; ?>
          </div>
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-circle"></i> Registrar</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5 mb-3">Últimos movimientos</h2>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead class="table-dark"><tr><th>Fecha y hora</th><th>Producto</th><th>Tipo</th><th class="text-end">Cant.</th><th>Observación</th><th>Usuario</th></tr></thead>
            <tbody>
            <?php if (!$historial): ?><tr><td colspan="6" class="text-center text-muted py-3">Sin movimientos registrados.</td></tr><?php endif; ?>
            <?php foreach ($historial as $m): ?>
              <tr>
                <td class="text-nowrap"><?= e(date('d-m-Y H:i', strtotime($m['fecha_hora']))) ?></td>
                <td><code><?= e($m['codigo']) ?></code> <?= e($m['producto']) ?></td>
                <td><span class="badge <?= $m['tipo'] === 'entrada' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= e(ucfirst($m['tipo'])) ?></span></td>
                <td class="text-end"><?= (int) $m['cantidad'] ?></td>
                <td><?= e($m['observacion']) ?></td>
                <td><?= e($m['usuario']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
