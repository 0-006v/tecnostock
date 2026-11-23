<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Escapa texto para evitar XSS. */
function e(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Guarda un mensaje flash (success, danger, warning, info). */
function flash(string $tipo, string $texto): void
{
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $texto];
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Token CSRF por sesión. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/**
 * Validación de producto en el servidor.
 * DEBE espejar assets/js/validaciones.js (mismas reglas y mismos mensajes).
 * Devuelve [errores, datos limpios].
 */
function validar_producto(array $in): array
{
    $err = [];
    $d = [
        'codigo'       => trim((string) ($in['codigo'] ?? '')),
        'nombre'       => trim((string) ($in['nombre'] ?? '')),
        'descripcion'  => trim((string) ($in['descripcion'] ?? '')),
        'precio'       => trim((string) ($in['precio'] ?? '')),
        'stock_actual' => trim((string) ($in['stock_actual'] ?? '')),
        'stock_minimo' => trim((string) ($in['stock_minimo'] ?? '')),
        'id_categoria' => trim((string) ($in['id_categoria'] ?? '')),
    ];

    if ($d['codigo'] === '') {
        $err['codigo'] = 'El código es obligatorio.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $d['codigo'])) {
        $err['codigo'] = 'El código debe tener 3 a 30 caracteres (letras, números, guion).';
    }

    if ($d['nombre'] === '') {
        $err['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($d['nombre']) < 3 || mb_strlen($d['nombre']) > 100) {
        $err['nombre'] = 'El nombre debe tener entre 3 y 100 caracteres.';
    }

    if (mb_strlen($d['descripcion']) > 255) {
        $err['descripcion'] = 'La descripción no puede superar 255 caracteres.';
    }

    if ($d['id_categoria'] === '' || !ctype_digit($d['id_categoria'])) {
        $err['id_categoria'] = 'Seleccione una categoría.';
    }

    if ($d['precio'] === '' || !is_numeric($d['precio'])) {
        $err['precio'] = 'El precio es obligatorio y debe ser numérico.';
    } elseif ((float) $d['precio'] < 0) {
        $err['precio'] = 'El precio no puede ser negativo.';
    } elseif ((float) $d['precio'] > 99999999.99) {
        $err['precio'] = 'El precio es demasiado alto.';
    }

    foreach (['stock_actual' => 'El stock actual', 'stock_minimo' => 'El stock mínimo'] as $campo => $etiqueta) {
        if ($d[$campo] === '' || !preg_match('/^-?\d+$/', $d[$campo])) {
            $err[$campo] = $etiqueta . ' es obligatorio y debe ser un entero.';
        } elseif ((int) $d[$campo] < 0) {
            $err[$campo] = $etiqueta . ' no puede ser negativo.';
        } elseif ((int) $d[$campo] > 1000000) {
            $err[$campo] = $etiqueta . ' no puede superar 1.000.000.';
        }
    }
    return [$err, $d];
}

/** Validación de movimiento (espejada en JS). El chequeo de stock se hace con la fila bloqueada. */
function validar_movimiento(array $in): array
{
    $err = [];
    $d = [
        'id_producto' => trim((string) ($in['id_producto'] ?? '')),
        'tipo'        => trim((string) ($in['tipo'] ?? '')),
        'cantidad'    => trim((string) ($in['cantidad'] ?? '')),
        'observacion' => trim((string) ($in['observacion'] ?? '')),
    ];
    if ($d['id_producto'] === '' || !ctype_digit($d['id_producto'])) {
        $err['id_producto'] = 'Seleccione un producto.';
    }
    if (!in_array($d['tipo'], ['entrada', 'salida'], true)) {
        $err['tipo'] = 'Seleccione el tipo de movimiento.';
    }
    if ($d['cantidad'] === '' || !preg_match('/^-?\d+$/', $d['cantidad'])) {
        $err['cantidad'] = 'La cantidad es obligatoria y debe ser un entero.';
    } elseif ((int) $d['cantidad'] <= 0) {
        $err['cantidad'] = 'La cantidad debe ser mayor que cero.';
    } elseif ((int) $d['cantidad'] > 1000000) {
        $err['cantidad'] = 'La cantidad no puede superar 1.000.000.';
    }
    if ($d['observacion'] === '') {
        $err['observacion'] = 'La observación es obligatoria.';
    } elseif (mb_strlen($d['observacion']) > 255) {
        $err['observacion'] = 'La observación no puede superar 255 caracteres.';
    }
    return [$err, $d];
}
