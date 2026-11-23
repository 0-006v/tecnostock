-- TecnoStock - Script reproducible (importar en phpMyAdmin)
-- Usuario de prueba: admin@tecnostock.cl  |  Clave: Admin123*
CREATE DATABASE IF NOT EXISTS tecnostock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tecnostock;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
  id_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(100) NOT NULL,
  correo     VARCHAR(120) NOT NULL,
  clave      VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt (password_hash)',
  estado     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',
  CONSTRAINT pk_usuarios PRIMARY KEY (id_usuario),
  CONSTRAINT uq_usuarios_correo UNIQUE (correo),
  CONSTRAINT ck_usuarios_estado CHECK (estado IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id_categoria INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(80)  NOT NULL,
  descripcion  VARCHAR(255) NULL,
  estado       TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_categorias PRIMARY KEY (id_categoria),
  CONSTRAINT uq_categorias_nombre UNIQUE (nombre),
  CONSTRAINT ck_categorias_estado CHECK (estado IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE productos (
  id_producto  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo       VARCHAR(30)  NOT NULL,
  nombre       VARCHAR(100) NOT NULL,
  descripcion  VARCHAR(255) NULL,
  precio       DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock_actual INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 0,
  estado       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo (borrado logico)',
  id_categoria INT UNSIGNED NOT NULL,
  CONSTRAINT pk_productos PRIMARY KEY (id_producto),
  CONSTRAINT uq_productos_codigo UNIQUE (codigo),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria)
    REFERENCES categorias (id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_productos_precio CHECK (precio >= 0),
  CONSTRAINT ck_productos_stock_actual CHECK (stock_actual >= 0),
  CONSTRAINT ck_productos_stock_minimo CHECK (stock_minimo >= 0),
  CONSTRAINT ck_productos_estado CHECK (estado IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE movimientos (
  id_movimiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha_hora    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo          VARCHAR(10) NOT NULL,
  cantidad      INT NOT NULL,
  observacion   VARCHAR(255) NOT NULL,
  id_producto   INT UNSIGNED NOT NULL,
  id_usuario    INT UNSIGNED NOT NULL,
  CONSTRAINT pk_movimientos PRIMARY KEY (id_movimiento),
  CONSTRAINT fk_mov_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_mov_tipo CHECK (tipo IN ('entrada','salida')),
  CONSTRAINT ck_mov_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_productos_nombre ON productos (nombre);
CREATE INDEX idx_mov_fecha ON movimientos (fecha_hora);

-- ---------- DATOS DE PRUEBA ----------
INSERT INTO usuarios (nombre, correo, clave, estado) VALUES
('Encargado de Inventario', 'admin@tecnostock.cl', '$2y$10$z84dYFZ33uKbeaLm8v0bae7rPr6p9baIitK4eghv4iSErIc00a6Du', 1);

INSERT INTO categorias (nombre, descripcion, estado) VALUES
('Periféricos', 'Mouse, teclados y webcams', 1),
('Audio', 'Audífonos y parlantes', 1),
('Cargadores y cables', 'Energía y conectividad', 1),
('Accesorios', 'Fundas, soportes y otros', 1);

INSERT INTO productos (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria) VALUES
('TS-001', 'Mouse inalámbrico M170', 'Mouse óptico 2.4 GHz', 12990, 25, 5, 1, 1),
('TS-002', 'Teclado mecánico RGB', 'Switch rojo, layout español', 34990, 15, 5, 1, 1),
('TS-003', 'Audífonos Bluetooth', 'Over-ear con micrófono', 19990, 3, 10, 1, 2),
('TS-004', 'Parlante portátil', 'Resistente al agua IPX5', 24990, 12, 4, 1, 2),
('TS-005', 'Cable USB-C 1m', 'Carga y datos', 4990, 2, 15, 1, 3),
('TS-006', 'Cargador rápido 20W', 'USB-C Power Delivery', 12990, 40, 10, 1, 3),
('TS-007', 'Funda notebook 15"', 'Neopreno acolchado', 9990, 18, 5, 1, 4),
('TS-008', 'Webcam HD 720p', 'Descontinuada', 15990, 0, 3, 0, 1);

INSERT INTO movimientos (fecha_hora, tipo, cantidad, observacion, id_producto, id_usuario) VALUES
(NOW() - INTERVAL 3 DAY, 'entrada', 30, 'Compra a proveedor', 1, 1),
(NOW() - INTERVAL 2 DAY, 'salida',   5, 'Venta en tienda', 1, 1),
(NOW() - INTERVAL 2 DAY, 'entrada', 15, 'Compra a proveedor', 3, 1),
(NOW() - INTERVAL 1 DAY, 'salida',  12, 'Venta online', 3, 1),
(NOW(),                   'salida',  13, 'Venta mayorista', 5, 1);
