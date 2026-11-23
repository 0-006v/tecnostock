<?php
declare(strict_types=1);

// Parámetros por defecto de XAMPP (usuario root sin clave).
const DB_HOST = 'localhost';
const DB_NAME = 'tecnostock';
const DB_USER = 'root';
const DB_PASS = '';

/**
 * Devuelve una única conexión PDO (patrón singleton).
 * Las excepciones se activan para poder usar try-catch y rollBack.
 */
function obtener_conexion(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // consultas preparadas reales
                ]
            );
        } catch (PDOException $e) {
            error_log('Error de conexión: ' . $e->getMessage());
            http_response_code(500);
            exit('No fue posible conectar con la base de datos. Verifique que MySQL esté activo en XAMPP.');
        }
    }
    return $pdo;
}
