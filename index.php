<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/conexion.php';

$pdo = obtener_conexion();

// ---- Filtros (GET) ----
$q      = trim((string) ($_GET['q'] ?? ''));
$cat    = ctype_digit((string) ($_GET['categoria'] ?? '')) ? (int) $_GET['categoria'] : 0;
$estado = in_array($_GET['estado'] ?? 'activos', ['activos', 'inactivos', 'todos'], true)
    ? $_GET['estado'] : 'activos';

try {
    // ---- Tarjetas resumen ----
    $totalActivos = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn();
    $totalAlertas = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1 AND stock_actual < stock_minimo')->fetchColumn();
    $movHoy       = (int) $pdo->query('SELECT COUNT(*) FROM movimientos WHERE DATE(fecha_hora) = CURDATE()')->fetchColumn();

    $alertas = $pdo->query(
        'SELECT codigo, nombre, stock_actual, stock_minimo
           FROM productos WHERE estado = 1 AND stock_actual < stock_minimo
          ORDER BY (stock_minimo - stock_actual) DESC'
    )->fetchAll();

    $categorias = $pdo->query('SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre')->fetchAll();

    // ---- Listado con búsqueda (consulta preparada) ----
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(p.nombre LIKE :q1 OR p.codigo LIKE :q2 OR c.nombre LIKE :q3)';
        $like = '%' . $q . '%';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
    }
    if ($cat > 0) {
        $where[] = 'p.id_categoria = :cat';
        $params[':cat'] = $cat;
    }
    if ($estado === 'activos') {
        $where[] = 'p.estado = 1';
    } elseif ($estado === 'inactivos') {
        $where[] = 'p.estado = 0';
    }
    $sql = 'SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.stock_actual, p.stock_minimo, p.estado, c.nombre AS categoria
              FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY p.nombre';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $productos = $st->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    exit('Error al consultar el inventario.');
}

$titulo = 'Inventario';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card border-primary h-100"><div class="card-body">
    <div class="text-muted small">Productos activos</div><div class="display-6"><?= $totalActivos ?></div></div></div></div>
  <div class="col-md-4"><div class="card <?= $totalAlertas > 0 ? 'border-danger' : 'border-success' ?> h-100"><div class="card-body">
    <div class="text-muted small">Alertas de stock bajo</div>
    <div class="display-6 <?= $totalAlertas > 0 ? 'text-danger' : 'text-success' ?>"><?= $totalAlertas ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-info h-100"><div class="card-body">
    <div class="text-muted small">Movimientos de hoy</div><div class="display-6"><?= $movHoy ?></div></div></div></div>
</div>

<?php if ($totalAlertas > 0): ?>
<div class="alert alert-danger" role="alert">
  <strong><i class="bi bi-exclamation-triangle-fill"></i> Stock bajo en <?= $totalAlertas ?> producto(s):</strong>
  <ul class="mb-0 mt-1">
    <?php foreach ($alertas as $a): ?>
      <li><?= e($a['codigo']) ?> &ndash; <?= e($a['nombre']) ?>:
        <strong><?= (int) $a['stock_actual'] ?></strong> (mínimo <?= (int) $a['stock_minimo'] ?>)</li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h1 class="h5 mb-0">Catálogo de productos</h1>
      <a href="producto_form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo producto</a>
    </div>

    <form method="get" class="row g-2 mb-3">
      <div class="col-md-5">
        <input type="search" id="buscador" name="q" class="form-control" value="<?= e($q) ?>"
               placeholder="Buscar por nombre, código o categoría..." aria-label="Buscar">
      </div>
      <div class="col-md-3">
        <select name="categoria" class="form-select" aria-label="Categoría">
          <option value="0">Todas las categorías</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int) $c['id_categoria'] ?>" <?= $cat === (int) $c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="estado" class="form-select" aria-label="Estado">
          <option value="activos" <?= $estado === 'activos' ? 'selected' : '' ?>>Activos</option>
          <option value="inactivos" <?= $estado === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
          <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option>
        </select>
      </div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Filtrar</button></div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle" id="tabla-productos">
        <thead class="table-dark">
          <tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="text-end">Precio</th>
              <th class="text-center">Stock</th><th class="text-center">Mínimo</th><th>Estado</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
        <?php if (!$productos): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron productos.</td></tr>
        <?php endif; ?>
        <?php foreach ($productos as $p):
            $activo = (int) $p['estado'] === 1;
            $bajo   = $activo && (int) $p['stock_actual'] < (int) $p['stock_minimo'];
        ?>
          <tr class="<?= $bajo ? 'table-danger' : '' ?>" data-texto="<?= e(mb_strtolower($p['codigo'] . ' ' . $p['nombre'] . ' ' . $p['categoria'])) ?>">
            <td><code><?= e($p['codigo']) ?></code></td>
            <td><?= e($p['nombre']) ?></td>
            <td><?= e($p['categoria']) ?></td>
            <td class="text-end">$<?= number_format((float) $p['precio'], 0, ',', '.') ?></td>
            <td class="text-center">
              <?= (int) $p['stock_actual'] ?>
              <?php if ($bajo): ?><span class="badge bg-danger ms-1">Stock bajo</span><?php endif; ?>
            </td>
            <td class="text-center"><?= (int) $p['stock_minimo'] ?></td>
            <td><span class="badge <?= $activo ? 'bg-success' : 'bg-secondary' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
            <td class="text-end text-nowrap">
              <?php if ($activo): ?>
                <a class="btn btn-sm btn-outline-success" title="Registrar entrada" href="movimientos.php?id_producto=<?= (int) $p['id_producto'] ?>&tipo=entrada"><i class="bi bi-box-arrow-in-down"></i></a>
                <a class="btn btn-sm btn-outline-warning" title="Registrar salida" href="movimientos.php?id_producto=<?= (int) $p['id_producto'] ?>&tipo=salida"><i class="bi bi-box-arrow-up"></i></a>
                <a class="btn btn-sm btn-outline-primary" title="Editar" href="producto_form.php?id=<?= (int) $p['id_producto'] ?>"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <form method="post" action="producto_estado.php" class="d-inline form-estado"
                    data-accion="<?= $activo ? 'desactivar' : 'activar' ?>" data-nombre="<?= e($p['nombre']) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id_producto'] ?>">
                <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                <button class="btn btn-sm <?= $activo ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"
                        title="<?= $activo ? 'Desactivar' : 'Reactivar' ?>">
                  <i class="bi <?= $activo ? 'bi-slash-circle' : 'bi-arrow-counterclockwise' ?>"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
