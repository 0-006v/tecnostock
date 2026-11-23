<?php
// Requiere: funciones.php cargado. Variable opcional: $titulo
$titulo = $titulo ?? 'TecnoStock';
$pagina = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> | TecnoStock</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php if (!empty($_SESSION['id_usuario'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="index.php"><i class="bi bi-box-seam"></i> TecnoStock</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
            aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= $pagina === 'index.php' ? 'active' : '' ?>" href="index.php">Inventario</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina === 'producto_form.php' ? 'active' : '' ?>" href="producto_form.php">Nuevo producto</a></li>
        <li class="nav-item"><a class="nav-link <?= $pagina === 'movimientos.php' ? 'active' : '' ?>" href="movimientos.php">Movimientos</a></li>
      </ul>
      <span class="navbar-text me-3"><i class="bi bi-person-circle"></i> <?= e($_SESSION['nombre']) ?></span>
      <a class="btn btn-outline-light btn-sm" href="logout.php">Cerrar sesión</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container pb-5">
<?php if (!empty($_SESSION['mensaje'])): $m = $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
  <div class="alert alert-<?= e($m['tipo']) ?> alert-dismissible fade show" role="alert">
    <?= e($m['texto']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>
