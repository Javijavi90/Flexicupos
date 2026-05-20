-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS flexicupos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE flexicupos;

-- Tabla base de usuarios (Autenticación y Datos Comunes)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    rol ENUM('admin', 'cliente', 'prestador') NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para el perfil de clientes (Ubicación)
CREATE TABLE perfiles_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    ubicacion TEXT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla para el perfil de prestadores
CREATE TABLE perfiles_prestadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    especialidad VARCHAR(100) NOT NULL,
    direccion_fisica TEXT NOT NULL,
    descripcion TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Servicios y Precios por prestador
CREATE TABLE servicios_precios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    nombre_servicio VARCHAR(100) NOT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 60,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE
);

-- Galería de portafolio de los prestadores (Máx 10 controlado en PHP)
CREATE TABLE imagenes_portafolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestador_id INT NOT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    subido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE
);

-- Sistema de Citas
CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    prestador_id INT NOT NULL,
    servicio_id INT NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL,
    fecha_hora_fin DATETIME NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada') DEFAULT 'confirmada',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (prestador_id) REFERENCES perfiles_prestadores(id) ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios_precios(id) ON DELETE CASCADE
);

-- Índices para optimizar las consultas del calendario
CREATE INDEX idx_citas_prestador_fecha ON citas(prestador_id, fecha_hora_inicio);

-- INSERT inicial para el Administrador Global
INSERT INTO usuarios (nombre, correo, password_hash, telefono, rol) 
VALUES ('Administrador Global', 'admin@flexicupos.com', '$2y$10$WqB9b/i0p4G4rB0.sRkUxu0T.pT/yT/1/1/1/1/1/1/1/1/1/1/1/', '0000000000', 'admin');
