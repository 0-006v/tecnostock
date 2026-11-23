# 1. Análisis y requerimientos – TecnoStock

## Problema
TecnoStock vende accesorios tecnológicos y controla su catálogo en una planilla: códigos duplicados, cantidades erróneas y stock real poco claro.

## Alcance del MVP
Aplicación web local (XAMPP) que centraliza el catálogo y las entradas/salidas de existencias, con inicio de sesión, borrado lógico y alerta de stock bajo.
**Fuera de alcance:** ventas/facturación, múltiples roles, reportes avanzados, gestión de usuarios y categorías desde la interfaz (se cargan por SQL).

## Actor
**Encargado de inventario:** inicia sesión, administra productos y registra movimientos de existencias.

## Requerimientos funcionales
| ID | Requerimiento | Regla de negocio | Archivo(s) |
|---|---|---|---|
| RF01 | Iniciar sesión con usuario registrado y activo | Usuario inactivo no ingresa | `login.php`, `logout.php`, `includes/auth_check.php` |
| RF02 | Registrar productos (código, nombre, categoría, descripción, precio, stock, mínimo) | R1 código único; R2 no negatividad | `producto_form.php`, `includes/funciones.php` |
| RF03 | Consultar listado de productos | – | `index.php` |
| RF04 | Buscar por nombre, código o categoría (+ filtro activo/inactivo) | – | `index.php`, `assets/js/validaciones.js` |
| RF05 | Modificar precio, descripción, categoría y cantidades | R2; ajuste de stock queda como movimiento (R3, R6) | `producto_form.php` |
| RF06 | Desactivar/reactivar productos | R5 borrado lógico, sin DELETE | `producto_estado.php` |
| RF07 | Registrar entradas y salidas | R3 trazabilidad; R4 sin stock negativo; R5 inactivo sin movimientos; R6 transacción | `movimientos.php` |
| RF08 | Alerta de stock bajo (`stock_actual < stock_minimo`) | R7 | `index.php` (banner, tarjeta y badge) |

## Reglas de negocio
R1 código único · R2 precio/stock/mínimo ≥ 0 y cantidad > 0 · R3 todo movimiento guarda fecha, tipo, cantidad, observación, producto y usuario (de `$_SESSION`) · R4 una salida no deja stock < 0 · R5 productos no se eliminan; inactivos no admiten movimientos · R6 stock actualizado en transacción PDO · R7 alerta visual si stock < mínimo.

## Requerimientos no funcionales
| ID | Requerimiento | Cómo se cumple |
|---|---|---|
| RNF01 | Seguridad SQL | PDO con consultas preparadas (`EMULATE_PREPARES=false`) |
| RNF02 | Seguridad XSS/CSRF | `htmlspecialchars` vía `e()`, token CSRF en todos los POST |
| RNF03 | Seguridad de acceso | `password_hash`/`password_verify`, `session_regenerate_id`, `auth_check.php` |
| RNF04 | Integridad | PK, FK `ON DELETE RESTRICT`, UNIQUE, CHECK, transacciones |
| RNF05 | Usabilidad | Bootstrap 5 responsivo, mensajes flash, confirmaciones |
| RNF06 | Validación doble | JS en cliente + PHP en servidor con las mismas reglas |
| RNF07 | Entorno | XAMPP (Apache, PHP 8+, MySQL/MariaDB), sin frameworks ni jQuery |
