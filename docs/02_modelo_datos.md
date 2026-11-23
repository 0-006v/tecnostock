# 2. Modelo de datos

Script: `tecnostock.sql` (importar en phpMyAdmin → Importar).

## Relaciones
`categorias 1──N productos 1──N movimientos N──1 usuarios` (todas las FK con `ON DELETE RESTRICT`).

## Normalización
- **1FN:** atributos atómicos, sin grupos repetidos; cada tabla tiene PK.
- **2FN:** todas las PK son simples (un solo campo), no hay dependencias parciales.
- **3FN:** el nombre de categoría y el del usuario se guardan una sola vez y se referencian por FK; no hay dependencias transitivas. `stock_actual` es un valor mantenido por transacción y auditable con `movimientos`.

## Diccionario de datos
**usuarios:** id_usuario (PK, AI) · nombre VARCHAR(100) · correo VARCHAR(120) UNIQUE · clave VARCHAR(255) hash bcrypt · estado TINYINT (1/0)
**categorias:** id_categoria (PK) · nombre VARCHAR(80) UNIQUE · descripcion VARCHAR(255) · estado TINYINT
**productos:** id_producto (PK) · codigo VARCHAR(30) UNIQUE · nombre · descripcion · precio DECIMAL(10,2) CHECK ≥0 · stock_actual INT CHECK ≥0 · stock_minimo INT CHECK ≥0 · estado TINYINT (1 activo, 0 inactivo) · id_categoria (FK)
**movimientos:** id_movimiento (PK) · fecha_hora DATETIME · tipo CHECK ('entrada','salida') · cantidad INT CHECK >0 · observacion VARCHAR(255) · id_producto (FK) · id_usuario (FK)

## Credenciales de prueba
Correo `admin@tecnostock.cl` · Clave `Admin123*` (guardada como hash bcrypt `$2y$`).
Datos: 4 categorías, 8 productos (TS-003 y TS-005 con stock bajo, TS-008 inactivo) y 5 movimientos.
Requiere MySQL 8.0.16+ o MariaDB 10.2+ (XAMPP actual) para que los CHECK se apliquen.
