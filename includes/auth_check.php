<?php
declare(strict_types=1);
require_once __DIR__ . '/funciones.php';

// Protege las páginas privadas: sin sesión se redirige al login.
if (empty($_SESSION['id_usuario'])) {
    flash('warning', 'Debe iniciar sesión para continuar.');
    redirigir('login.php');
}
