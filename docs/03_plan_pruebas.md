# 4. Plan de pruebas (marcar "Estado" al ejecutarlas y adjuntar capturas)

| ID | Tipo | Entrada | Resultado esperado | Estado |
|---|---|---|---|---|
| CP01 | Normal | Login admin@tecnostock.cl / Admin123* | Ingresa al inventario con mensaje de bienvenida | ✔ |
| CP02 | Negativo | Clave incorrecta | "Credenciales incorrectas." | ✔ |
| CP03 | Negativo | Acceder a `index.php` sin sesión | Redirige a login | ☐ |
| CP04 | Negativo | Usuario con estado 0 (`UPDATE usuarios SET estado=0`) | "Su usuario está inactivo" | ☐ |
| CP05 | Normal | Crear producto TS-100, precio 9990, stock 10, mínimo 3 | Aparece en el listado; movimiento "Stock inicial" | ☐ |
| CP06 | Negativo | Crear con código TS-001 | "Ya existe un producto con ese código." | ✔ |
| CP07 | Límite | Precio -1 | Error "no puede ser negativo" (JS y PHP) | ✔ |
| CP08 | Límite | Stock mínimo -5 / stock inicial -1 | Rechazado en cliente y servidor | ✔ |
| CP09 | Límite | Precio 0 y stock 0 | Se acepta (≥ 0) | ☐ |
| CP10 | Negativo | Campos vacíos | Mensajes por campo, sin envío | ☐ |
| CP11 | Normal | Buscar "audio", "TS-00", "cable" | Filtra por categoría, código y nombre | ✔ |
| CP12 | Normal | Editar precio, descripción, categoría | Cambios visibles en el listado | ✔ |
| CP13 | Normal | Editar stock 10→15 | stock = 15 y movimiento entrada de 5 (ajuste) | ✔ |
| CP14 | Normal | Entrada de 10 a TS-001 | Stock +10, movimiento con usuario y fecha | ☐ |
| CP15 | Normal | Salida de 5 a TS-001 | Stock -5 | ☐ |
| CP16 | Límite | Salida exactamente igual al stock | Stock queda en 0, permitido | ✔ |
| CP17 | Límite | Salida = stock + 1 (dejaría -1) | Bloqueada: "Stock insuficiente…", stock intacto | ✔ |
| CP18 | Negativo | Cantidad 0 o -3 | "La cantidad debe ser mayor que cero." | ✔ |
| CP19 | Negativo | Movimiento a producto inactivo (POST manual) | "El producto está inactivo…" | ✔ |
| CP20 | Normal | Desactivar TS-007 con confirmación | Estado Inactivo, sin DELETE, historial se conserva | ✔ |
| CP21 | Normal | Reactivar TS-007 | Vuelve a Activo | ✔ |
| CP22 | Normal | Alerta: TS-003 (3 < 10) y TS-005 (2 < 15) | Badge rojo, banner y tarjeta = 2 | ✔ |
| CP23 | Normal | Dejar TS-003 con stock 10 | La alerta desaparece (10 no es < 10) | ✔ |
| CP24 | Seguridad | Nombre `<script>alert(1)</script>` | Se muestra como texto, no se ejecuta | ☐ |
| CP25 | Seguridad | Código `' OR 1=1 --` | Rechazado por formato; consultas preparadas | ☐ |
| CP26 | Seguridad | POST sin token CSRF | "Solicitud no válida" | ☐ |
| CP27 | Integridad | Atomicidad: apagar MySQL durante un movimiento | Sin cambios parciales (rollBack) | ☐ |
