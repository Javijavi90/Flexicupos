-- ============================================================
-- SCRIPT DE ACTUALIZACIÓN para FlexiCupos (v3)
--
-- EJECUTAR EN: phpMyAdmin > Seleccionar BD > pestaña SQL
-- Compatible con MySQL 5.7+ y MariaDB 10+
--
-- AGREGAR columnas faltantes + CREAR tablas nuevas + ÍNDICES
-- Sin borrar ni modificar datos existentes.
-- ============================================================

-- ============================================================
-- 1. Agregar columna 'estado' a usuarios (si no existe)
-- ============================================================
SET @existe_estado = (SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'estado');

SET @sql_estado = IF(@existe_estado = 0,
    'ALTER TABLE usuarios ADD COLUMN estado ENUM(''activo'',''desactivado'') NOT NULL DEFAULT ''activo'' AFTER rol',
    'SELECT ''[OK] usuarios.estado ya existe''');

PREPARE stmt_estado FROM @sql_estado;
EXECUTE stmt_estado;
DEALLOCATE PREPARE stmt_estado;

-- ============================================================
-- 2. Crear tablas NUEVAS (solo si no existen)
-- ============================================================

-- Perfiles de Clientes
CREATE TABLE IF NOT EXISTS perfiles_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    ubicacion TEXT NOT NULL,
    cancelaciones INT NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Perfiles de Prestadores
CREATE TABLE IF NOT EXISTS perfiles_prestadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    tipo_entidad ENUM('particular', 'empresa') NOT NULL DEFAULT 'particular',
    nombre_legal VARCHAR(150) DEFAULT NULL,
    apellido_legal VARCHAR(150) DEFAULT NULL,
    nombre_empresa VARCHAR(200) DEFAULT NULL,
    ruc VARCHAR(30) DEFAULT NULL,
    especialidad VARCHAR(100) NOT NULL,
    categoria VARCHAR(100) DEFAULT NULL,
    direccion_fisica TEXT NOT NULL,
    descripcion TEXT,
    sitio_web VARCHAR(255) DEFAULT NULL,
    redes_sociales TEXT DEFAULT NULL,
    logo_url VARCHAR(255) DEFAULT NULL,
    activo_etiqueta TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Galeria de Portafolio
CREATE TABLE IF NOT EXISTS imagenes_portafolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    subido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE
);

-- Horarios de Trabajo
CREATE TABLE IF NOT EXISTS horarios_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes...6=Sabado',
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_horario (prestador_id, dia_semana, hora_inicio)
);

-- Excepciones de Horario
CREATE TABLE IF NOT EXISTS excepciones_horario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_excepcion (prestador_id, fecha)
);

-- ============================================================
-- 3. Agregar columnas faltantes a tablas que YA existen
--    (por si una ejecucion parcial las creo sin todas las columnas)
-- ============================================================
-- Funcion auxiliar: revisa si una tabla existe
-- Usamos variables para evitar codigo repetido

-- 3a. Columnas faltantes en servicios_precios
SET @existe_tabla_sp = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios');

SET @existe_desc = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'descripcion_servicio');

SET @sql_desc = IF(@existe_tabla_sp > 0 AND @existe_desc = 0,
    'ALTER TABLE servicios_precios ADD COLUMN descripcion_servicio TEXT DEFAULT NULL AFTER duracion_minutos',
    'SELECT ''[OK] servicios_precios.descripcion_servicio ok''');
PREPARE stmt_desc FROM @sql_desc;
EXECUTE stmt_desc;
DEALLOCATE PREPARE stmt_desc;

SET @existe_activo = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'activo');
SET @sql_activo = IF(@existe_tabla_sp > 0 AND @existe_activo = 0,
    'ALTER TABLE servicios_precios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion_servicio',
    'SELECT ''[OK] servicios_precios.activo ok''');
PREPARE stmt_activo FROM @sql_activo;
EXECUTE stmt_activo;
DEALLOCATE PREPARE stmt_activo;

SET @existe_creado_sp = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'creado_en');
SET @sql_creado_sp = IF(@existe_tabla_sp > 0 AND @existe_creado_sp = 0,
    'ALTER TABLE servicios_precios ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER activo',
    'SELECT ''[OK] servicios_precios.creado_en ok''');
PREPARE stmt_creado_sp FROM @sql_creado_sp;
EXECUTE stmt_creado_sp;
DEALLOCATE PREPARE stmt_creado_sp;

-- 3b. Columnas faltantes en perfiles_prestadores
SET @existe_tabla_pp = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores');

-- ORDEN CORRECTO: primero agregamos las columnas 'intermedias', luego las finales
-- (las que referencian a otras con AFTER)

SET @existe_especialidad = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'especialidad');
SET @sql_especialidad = IF(@existe_tabla_pp > 0 AND @existe_especialidad = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN especialidad VARCHAR(100) NOT NULL DEFAULT '''' AFTER ruc',
    'SELECT ''[OK] perfiles_prestadores.especialidad ok''');
PREPARE stmt_especialidad FROM @sql_especialidad;
EXECUTE stmt_especialidad;
DEALLOCATE PREPARE stmt_especialidad;

SET @existe_categoria = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'categoria');
SET @sql_categoria = IF(@existe_tabla_pp > 0 AND @existe_categoria = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN categoria VARCHAR(100) DEFAULT NULL AFTER especialidad',
    'SELECT ''[OK] perfiles_prestadores.categoria ok''');
PREPARE stmt_categoria FROM @sql_categoria;
EXECUTE stmt_categoria;
DEALLOCATE PREPARE stmt_categoria;

SET @existe_direccion = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'direccion_fisica');
SET @sql_direccion = IF(@existe_tabla_pp > 0 AND @existe_direccion = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN direccion_fisica TEXT NOT NULL AFTER categoria',
    'SELECT ''[OK] perfiles_prestadores.direccion_fisica ok''');
PREPARE stmt_direccion FROM @sql_direccion;
EXECUTE stmt_direccion;
DEALLOCATE PREPARE stmt_direccion;

SET @existe_desc_pp = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'descripcion');
SET @sql_desc_pp = IF(@existe_tabla_pp > 0 AND @existe_desc_pp = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN descripcion TEXT AFTER direccion_fisica',
    'SELECT ''[OK] perfiles_prestadores.descripcion ok''');
PREPARE stmt_desc_pp FROM @sql_desc_pp;
EXECUTE stmt_desc_pp;
DEALLOCATE PREPARE stmt_desc_pp;

SET @existe_web = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'sitio_web');
SET @sql_web = IF(@existe_tabla_pp > 0 AND @existe_web = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN sitio_web VARCHAR(255) DEFAULT NULL AFTER descripcion',
    'SELECT ''[OK] perfiles_prestadores.sitio_web ok''');
PREPARE stmt_web FROM @sql_web;
EXECUTE stmt_web;
DEALLOCATE PREPARE stmt_web;

SET @existe_redes = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'redes_sociales');
SET @sql_redes = IF(@existe_tabla_pp > 0 AND @existe_redes = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN redes_sociales TEXT DEFAULT NULL AFTER sitio_web',
    'SELECT ''[OK] perfiles_prestadores.redes_sociales ok''');
PREPARE stmt_redes FROM @sql_redes;
EXECUTE stmt_redes;
DEALLOCATE PREPARE stmt_redes;

SET @existe_logo = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'logo_url');
SET @sql_logo = IF(@existe_tabla_pp > 0 AND @existe_logo = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER redes_sociales',
    'SELECT ''[OK] perfiles_prestadores.logo_url ok''');
PREPARE stmt_logo FROM @sql_logo;
EXECUTE stmt_logo;
DEALLOCATE PREPARE stmt_logo;

SET @existe_ae = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'activo_etiqueta');
SET @sql_ae = IF(@existe_tabla_pp > 0 AND @existe_ae = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN activo_etiqueta TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_url',
    'SELECT ''[OK] perfiles_prestadores.activo_etiqueta ok''');
PREPARE stmt_ae FROM @sql_ae;
EXECUTE stmt_ae;
DEALLOCATE PREPARE stmt_ae;

SET @existe_creado_pp = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'creado_en');
SET @sql_creado_pp = IF(@existe_tabla_pp > 0 AND @existe_creado_pp = 0,
    'ALTER TABLE perfiles_prestadores ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER activo_etiqueta',
    'SELECT ''[OK] perfiles_prestadores.creado_en ok''');
PREPARE stmt_creado_pp FROM @sql_creado_pp;
EXECUTE stmt_creado_pp;
DEALLOCATE PREPARE stmt_creado_pp;

-- 3c. Columnas faltantes en perfiles_clientes
SET @existe_tabla_pc = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_clientes');
SET @existe_cancelaciones = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_clientes' AND COLUMN_NAME = 'cancelaciones');
SET @sql_cancelaciones = IF(@existe_tabla_pc > 0 AND @existe_cancelaciones = 0,
    'ALTER TABLE perfiles_clientes ADD COLUMN cancelaciones INT NOT NULL DEFAULT 0 AFTER ubicacion',
    'SELECT ''[OK] perfiles_clientes.cancelaciones ok''');
PREPARE stmt_cancelaciones FROM @sql_cancelaciones;
EXECUTE stmt_cancelaciones;
DEALLOCATE PREPARE stmt_cancelaciones;

-- ============================================================
-- 4. Crear tablas que dependen de otras tablas
-- ============================================================

CREATE TABLE IF NOT EXISTS servicios_precios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    nombre_servicio VARCHAR(100) NOT NULL,
    precio DECIMAL(10, 2) NOT NULL DEFAULT 0,
    duracion_minutos INT NOT NULL DEFAULT 60,
    descripcion_servicio TEXT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS planes_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servicio_id INT NOT NULL,
    nombre_plan VARCHAR(100) NOT NULL,
    descripcion_plan TEXT DEFAULT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 60,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servicio_id) REFERENCES servicios_precios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    prestador_id INT NOT NULL,
    servicio_id INT NOT NULL,
    plan_id INT DEFAULT NULL,
    fecha_hora_inicio DATETIME NOT NULL,
    fecha_hora_fin DATETIME NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada', 'completada') DEFAULT 'pendiente',
    direccion_servicio TEXT DEFAULT NULL,
    costo_final DECIMAL(10, 2) DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios_precios(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES planes_servicio(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cancelaciones_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    cita_id INT NOT NULL,
    fecha_cancelacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
);

-- ============================================================
-- 5. Indices (solo si no existen y las columnas existen)
-- ============================================================

SET @existe_idx1 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND INDEX_NAME = 'idx_citas_prestador_fecha');
SET @sql_idx1 = IF(@existe_idx1 = 0,
    'CREATE INDEX idx_citas_prestador_fecha ON citas(prestador_id, fecha_hora_inicio)',
    'SELECT ''[OK] idx_citas_prestador_fecha ok''');
PREPARE stmt_idx1 FROM @sql_idx1;
EXECUTE stmt_idx1;
DEALLOCATE PREPARE stmt_idx1;

SET @existe_idx2 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND INDEX_NAME = 'idx_citas_cliente');
SET @sql_idx2 = IF(@existe_idx2 = 0,
    'CREATE INDEX idx_citas_cliente ON citas(cliente_id, fecha_hora_inicio)',
    'SELECT ''[OK] idx_citas_cliente ok''');
PREPARE stmt_idx2 FROM @sql_idx2;
EXECUTE stmt_idx2;
DEALLOCATE PREPARE stmt_idx2;

-- Indice idx_servicios_categoria (solo si activo existe)
SET @existe_idx3 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND INDEX_NAME = 'idx_servicios_categoria');
SET @col_activo_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'activo');
SET @sql_idx3 = IF(@existe_idx3 = 0 AND @col_activo_existe > 0,
    'CREATE INDEX idx_servicios_categoria ON servicios_precios(prestador_id, activo)',
    'SELECT ''[OK] idx_servicios_categoria ok''');
PREPARE stmt_idx3 FROM @sql_idx3;
EXECUTE stmt_idx3;
DEALLOCATE PREPARE stmt_idx3;

-- Indice idx_prestadores_activos (solo si activo_etiqueta existe)
SET @existe_idx4 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND INDEX_NAME = 'idx_prestadores_activos');
SET @col_ae_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'activo_etiqueta');
SET @sql_idx4 = IF(@existe_idx4 = 0 AND @col_ae_existe > 0,
    'CREATE INDEX idx_prestadores_activos ON perfiles_prestadores(activo_etiqueta)',
    'SELECT ''[OK] idx_prestadores_activos ok''');
PREPARE stmt_idx4 FROM @sql_idx4;
EXECUTE stmt_idx4;
DEALLOCATE PREPARE stmt_idx4;

-- Indice idx_usuarios_estado (solo si estado existe)
SET @existe_idx5 = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_estado');
SET @col_estado_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'estado');
SET @sql_idx5 = IF(@existe_idx5 = 0 AND @col_estado_existe > 0,
    'CREATE INDEX idx_usuarios_estado ON usuarios(estado)',
    'SELECT ''[OK] idx_usuarios_estado ok''');
PREPARE stmt_idx5 FROM @sql_idx5;
EXECUTE stmt_idx5;
DEALLOCATE PREPARE stmt_idx5;

-- ============================================================
-- 6. Insertar admin solo si no existe
-- ============================================================
INSERT IGNORE INTO usuarios (nombre, correo, password_hash, telefono, rol)
VALUES ('Administrador Global', 'admin@flexicupos.com', SHA1('admin123'), '0000000000', 'admin');
