-- ============================================================
-- NOTA: La base de datos debe crearse desde cPanel > MySQL Databases
-- Luego seleccionas la BD en phpMyAdmin e importas este archivo
-- ============================================================

-- ============================================================
-- TABLA BASE: Usuarios (Autenticación y Datos Comunes)
-- ============================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    rol ENUM('admin', 'cliente', 'prestador') NOT NULL,
    estado ENUM('activo', 'desactivado') NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: Perfiles de Clientes
-- ============================================================
CREATE TABLE perfiles_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    ubicacion TEXT NOT NULL,
    cancelaciones INT NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLA: Perfiles de Prestadores (PROFESIONALES)
-- ============================================================
CREATE TABLE perfiles_prestadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    tipo_entidad ENUM('particular', 'empresa') NOT NULL DEFAULT 'particular',
    -- Datos de Particular
    nombre_legal VARCHAR(150) DEFAULT NULL,
    apellido_legal VARCHAR(150) DEFAULT NULL,
    -- Datos de Empresa
    nombre_empresa VARCHAR(200) DEFAULT NULL,
    ruc VARCHAR(30) DEFAULT NULL,
    -- Datos comunes
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

-- ============================================================
-- TABLA: Servicios y Precios Base
-- ============================================================
CREATE TABLE servicios_precios (
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

-- ============================================================
-- TABLA: Planes por Servicio (máx 10 por servicio)
-- ============================================================
CREATE TABLE planes_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servicio_id INT NOT NULL,
    nombre_plan VARCHAR(100) NOT NULL,
    descripcion_plan TEXT DEFAULT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 60,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servicio_id) REFERENCES servicios_precios(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLA: Galería de Portafolio (Máx 10)
-- ============================================================
CREATE TABLE imagenes_portafolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    subido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLA: Horarios de Trabajo del Prestador
-- ============================================================
CREATE TABLE horarios_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes...6=Sábado',
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_horario (prestador_id, dia_semana, hora_inicio)
);

-- ============================================================
-- TABLA: Excepciones de Horario (días específicos bloqueados)
-- ============================================================
CREATE TABLE excepciones_horario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    bloqueado TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_excepcion (prestador_id, fecha)
);

-- ============================================================
-- TABLA: Citas / Reservas
-- ============================================================
CREATE TABLE citas (
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

-- ============================================================
-- TABLA: Registro de Cancelaciones del Cliente
-- ============================================================
CREATE TABLE cancelaciones_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    cita_id INT NOT NULL,
    fecha_cancelacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
);

-- ============================================================
-- ÍNDICES
-- ============================================================
CREATE INDEX idx_citas_prestador_fecha ON citas(prestador_id, fecha_hora_inicio);
CREATE INDEX idx_citas_cliente ON citas(cliente_id, fecha_hora_inicio);
CREATE INDEX idx_servicios_categoria ON servicios_precios(prestador_id, activo);
CREATE INDEX idx_prestadores_activos ON perfiles_prestadores(activo_etiqueta);
CREATE INDEX idx_usuarios_estado ON usuarios(estado);

-- ============================================================
-- INSERT inicial para el Administrador Global
-- ============================================================
INSERT INTO usuarios (nombre, correo, password_hash, telefono, rol) 
VALUES ('Administrador Global', 'admin@flexicupos.com', SHA1('admin123'), '0000000000', 'admin');
