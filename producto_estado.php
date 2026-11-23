<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/conexion.php';

// Borrado lógico: nunca se ejecuta DELETE sobre productos.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    flash('danger', 'Solicitud no válida.');
    redirigir('index.php');
}

$id     = (string) ($_POST['id'] ?? '');
$accion = (string) ($_POST['accion'] ?? '');

if (!ctype_digit($id) || !in_array($accion, ['activar', 'desactivar'], true)) {
    flash('danger', 'Datos de la solicitud inválidos.');
    redirigir('index.php');
}

try {
    $nuevo = $accion === 'activar' ? 1 : 0;
    $st = obtener_conexion()->prepare('UPDATE productos SET estado = :estado WHERE id_producto = :id');
    $st->execute([':estado' => $nuevo, ':id' => (int) $id]);

    if ($st->rowCount() === 0) {
        flash('warning', 'El producto no existe o ya tenía ese estado.');
    } else {
        flash('success', $nuevo === 1 ? 'Producto reactivado correctamente.' : 'Producto desactivado. Su historial se conserva.');
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    flash('danger', 'No se pudo cambiar el estado del producto.');
}
redirigir('index.php');
