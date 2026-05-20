-- ============================================================
-- db_fix_seguro.sql
-- Agrega columnas faltantes a la BD existente SIN errores
-- Compatible con MySQL puro (NO usa IF NOT EXISTS)
-- Se puede ejecutar múltiples veces sin riesgo
-- ============================================================

-- ============================================================
-- 1. AGREGAR COLUMNAS FALTANTES A perfiles_prestadores
-- ============================================================

-- tipo_entidad
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'tipo_entidad');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN tipo_entidad ENUM(\'particular\',\'empresa\') NOT NULL DEFAULT \'particular\' AFTER usuario_id', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- nombre_legal
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'nombre_legal');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN nombre_legal VARCHAR(150) DEFAULT NULL AFTER tipo_entidad', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- apellido_legal
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'apellido_legal');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN apellido_legal VARCHAR(150) DEFAULT NULL AFTER nombre_legal', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- nombre_empresa
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'nombre_empresa');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN nombre_empresa VARCHAR(200) DEFAULT NULL AFTER apellido_legal', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- ruc
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'ruc');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN ruc VARCHAR(30) DEFAULT NULL AFTER nombre_empresa', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- categoria
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'categoria');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN categoria VARCHAR(100) DEFAULT NULL AFTER especialidad', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- sitio_web
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'sitio_web');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN sitio_web VARCHAR(255) DEFAULT NULL AFTER descripcion', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- redes_sociales
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'redes_sociales');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN redes_sociales TEXT DEFAULT NULL AFTER sitio_web', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- logo_url
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'logo_url');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER redes_sociales', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- activo_etiqueta
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'activo_etiqueta');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN activo_etiqueta TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_url', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- creado_en
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'creado_en');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_prestadores ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER activo_etiqueta', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- ============================================================
-- 2. AGREGAR COLUMNAS FALTANTES A servicios_precios
-- ============================================================

-- descripcion_servicio
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'descripcion_servicio');
SET @sql = IF(@existe = 0, 'ALTER TABLE servicios_precios ADD COLUMN descripcion_servicio TEXT DEFAULT NULL AFTER duracion_minutos', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- activo
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'activo');
SET @sql = IF(@existe = 0, 'ALTER TABLE servicios_precios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion_servicio', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- creado_en
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'creado_en');
SET @sql = IF(@existe = 0, 'ALTER TABLE servicios_precios ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER activo', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- ============================================================
-- 3. AGREGAR COLUMNAS FALTANTES A perfiles_clientes
-- ============================================================

-- creado_en
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_clientes' AND COLUMN_NAME = 'creado_en');
SET @sql = IF(@existe = 0, 'ALTER TABLE perfiles_clientes ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER cancelaciones', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- ============================================================
-- 4. CREAR TABLAS NUEVAS (SOLO SI NO EXISTEN)
-- ============================================================

-- planes_servicio
CREATE TABLE IF NOT EXISTS planes_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servicio_id INT NOT NULL,
    nombre_plan VARCHAR(100) NOT NULL,
    descripcion_plan TEXT DEFAULT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 60,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servicio_id) REFERENCES servicios_precios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- horarios_trabajo
CREATE TABLE IF NOT EXISTS horarios_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes...6=Sábado',
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_horario (prestador_id, dia_semana, hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- excepciones_horario
CREATE TABLE IF NOT EXISTS excepciones_horario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_excepcion (prestador_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- citas
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- cancelaciones_cliente
CREATE TABLE IF NOT EXISTS cancelaciones_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    cita_id INT NOT NULL,
    fecha_cancelacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- imagenes_portafolio
CREATE TABLE IF NOT EXISTS imagenes_portafolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    subido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. ÍNDICES (solo si las columnas existen)
-- ============================================================

-- Índice para citas por prestador
SET @existe = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND INDEX_NAME = 'idx_citas_prestador_fecha');
SET @sql = IF(@existe = 0, 'CREATE INDEX idx_citas_prestador_fecha ON citas(prestador_id, fecha_hora_inicio)', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- Índice para citas por cliente
SET @existe = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND INDEX_NAME = 'idx_citas_cliente');
SET @sql = IF(@existe = 0, 'CREATE INDEX idx_citas_cliente ON citas(cliente_id, fecha_hora_inicio)', 'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- Índice para servicios
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND COLUMN_NAME = 'activo');
SET @sql = IF(@existe = 1, 
    (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios_precios' AND INDEX_NAME = 'idx_servicios_prestador') = 0,
        'CREATE INDEX idx_servicios_prestador ON servicios_precios(prestador_id, activo)',
        'SELECT 1')),
    'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- Índice para profesionales activos
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND COLUMN_NAME = 'activo_etiqueta');
SET @sql = IF(@existe = 1,
    (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'perfiles_prestadores' AND INDEX_NAME = 'idx_prestadores_activos') = 0,
        'CREATE INDEX idx_prestadores_activos ON perfiles_prestadores(activo_etiqueta)',
        'SELECT 1')),
    'SELECT 1');
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- ============================================================
-- ¡LISTO! Ya puedes usar el sistema.
-- ============================================================
