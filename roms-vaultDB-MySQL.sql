-- =====================================================
-- Tabla: categorias
-- =====================================================
CREATE TABLE `categorias` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: consolas
-- =====================================================
CREATE TABLE `consolas` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) UNIQUE NOT NULL,
    `descripcion` TEXT,
    `fabricante` VARCHAR(100),
    `activo` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: roles
-- =====================================================
CREATE TABLE `roles` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(50) NOT NULL,
    `descripcion` TEXT,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roles_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: personas
-- =====================================================
CREATE TABLE `personas` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `telefono` VARCHAR(20),
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personas_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: juegos
-- =====================================================
CREATE TABLE `juegos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `titulo` VARCHAR(200) NOT NULL,
    `imagen` VARCHAR(300) NOT NULL,
    `descripcion` TEXT,
    `consola_id` INT,
    `categoria_id` INT,
    `region` VARCHAR(10),
    `fecha_lanzamiento` DATE,
    `idiomas` VARCHAR(200),
    `formato_imagen` VARCHAR(20),
    `game_id_code` VARCHAR(50),
    `google_drive_file_id` VARCHAR(100) NOT NULL,
    `google_drive_view_link` VARCHAR(500),
    `size_bytes` BIGINT DEFAULT 0,
    `downloads_count` INT DEFAULT 0,
    `activo` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_juegos_consola` (`consola_id`),
    KEY `idx_juegos_categoria` (`categoria_id`),
    KEY `idx_juegos_activo` (`activo`),
    CONSTRAINT `fk_juegos_consola` FOREIGN KEY (`consola_id`) REFERENCES `consolas` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_juegos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: usuarios
-- =====================================================
CREATE TABLE `usuarios` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `persona_id` INT,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol_id` INT,
    `activo` BOOLEAN DEFAULT TRUE,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuarios_username_unique` (`username`),
    KEY `idx_usuarios_persona` (`persona_id`),
    KEY `idx_usuarios_rol` (`rol_id`),
    CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: descargas
-- =====================================================
CREATE TABLE `descargas` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `juego_id` INT,
    `cookie_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45), -- MySQL no tiene tipo inet, usamos VARCHAR
    `user_agent` TEXT,
    `downloaded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `completed` BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (`id`),
    KEY `idx_descargas_juego` (`juego_id`),
    KEY `idx_descargas_cookie` (`cookie_id`),
    KEY `idx_descargas_fecha` (`downloaded_at`),
    -- Índice único para evitar descargas duplicadas por día (simulado en MySQL)
    KEY `idx_descargas_unique_daily` (`cookie_id`, `juego_id`, `downloaded_at`),
    CONSTRAINT `fk_descargas_juego` FOREIGN KEY (`juego_id`) REFERENCES `juegos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insertar datos iniciales
-- =====================================================

-- Insertar categorías
INSERT INTO `categorias` (`nombre`, `descripcion`, `activo`) VALUES
('Acción', 'Juegos de acción', TRUE),
('Aventura', 'Juegos de aventura', TRUE),
('Deportes', 'Juegos de deportes', TRUE),
('Carreras', 'Juegos de carreras', TRUE),
('Disparos', 'Juegos de disparos', TRUE),
('Rol', 'Juegos de rol', TRUE),
('Estrategia', 'Juegos de estrategia', TRUE),
('Plataformas', 'Juegos de plataformas', TRUE);

-- Insertar consolas
INSERT INTO `consolas` (`nombre`, `descripcion`, `fabricante`, `activo`) VALUES
('PSP', 'PlayStation Portable', 'Sony', TRUE),
('PSX', 'PlayStation 1', 'Sony', TRUE),
('PS2', 'PlayStation 2', 'Sony', TRUE),
('Game Boy Advance', 'Game Boy Advance', 'Nintendo', TRUE),
('Game Boy Color', 'Game Boy Color', 'Nintendo', TRUE),
('Game Boy', 'Game Boy', 'Nintendo', TRUE),
('Nintendo DS', 'Nintendo DS', 'Nintendo', TRUE),
('Nintendo 64', 'Nintendo 64', 'Nintendo', TRUE),
('Gamecube', 'Nintendo Gamecube', 'Nintendo', TRUE),
('Wii', 'Nintendo Wii', 'Nintendo', TRUE),
('NES', 'Nintendo Entretaiment System', 'Nintendo', TRUE),
('SNES', 'Super Nintendo Entretaiment System', 'Nintendo', TRUE),
('Dremcast', 'Sega Dreamcast', 'Sega', TRUE),
('Saturn', 'Sega Saturn', 'Sega', TRUE),
('Genesis', 'Sega Genesis', 'Sega', TRUE),

-- Insertar roles
INSERT INTO `roles` (`nombre`, `descripcion`) VALUES
('Administrador', 'Acceso total al sistema'),
('Usuario', 'Acceso básico de lectura'),
('Editor', 'Puede editar contenido');

-- Insertar una persona de ejemplo (admin)
INSERT INTO `personas` (`nombre`, `apellido`, `email`, `telefono`) VALUES
('Admin', 'Principal', 'admin@romsvault.com', '123456789');

-- Insertar usuario admin (contraseña: 'admin123' - deberás cambiarla)
-- IMPORTANTE: La contraseña 'admin123' hasheada con password_hash()
-- El hash de ejemplo es: $2y$10$YourHashedPasswordHere - DEBES GENERAR UNO REAL
INSERT INTO `usuarios` (`persona_id`, `username`, `password_hash`, `rol_id`, `activo`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, TRUE);

-- Insertar juegos de ejemplo (basados en tu HTML original)
INSERT INTO `juegos` (`titulo`, `descripcion`, `consola_id`, `categoria_id`, `region`, `fecha_lanzamiento`, `idiomas`, `formato_imagen`, `game_id_code`, `google_drive_file_id`, `google_drive_view_link`, `size_bytes`, `activo`) VALUES
('WipEout Pulse', 'Juego de carreras futurista con naves de alta velocidad', 1, 4, 'PAL', '2007-12-07', 'Español, Inglés', 'ISO', 'ULES-12345', '1fVWgArFcMMUFVX5SXkdMR5pQYU2uyXYj', 'https://drive.google.com/file/d/1fVWgArFcMMUFVX5SXkdMR5pQYU2uyXYj', 452984832, TRUE),
('WipEout Pure', 'La primera entrega de WipEout para PSP', 1, 4, 'NTSC', '2005-03-24', 'Inglés', 'ISO', 'ULES-12346', '1zRYOBNATxrbsN1WLzLioTRQD_tTx8L-z', 'https://drive.google.com/file/d/1zRYOBNATxrbsN1WLzLioTRQD_tTx8L-z', 435159040, TRUE),
('Carnivores', 'Caza de dinosaurios en un mundo prehistórico', 1, 2, 'NTSC', '2002-11-15', 'Inglés', 'ISO', 'ULES-12347', '1Fv6nQ27Ip9sy5prOoc1dQe-hel-7A6hq', 'https://drive.google.com/file/d/1Fv6nQ27Ip9sy5prOoc1dQe-hel-7A6hq', 417333248, TRUE);

-- =====================================================
-- Agregar columna de imagen si no existe (opcional)
-- =====================================================
ALTER TABLE `juegos` ADD COLUMN IF NOT EXISTS `imagen` VARCHAR(255) NULL AFTER `google_drive_view_link`;

-- =====================================================
-- Actualizar secuencias (no necesario en MySQL con AUTO_INCREMENT)
-- =====================================================
-- MySQL maneja automáticamente los AUTO_INCREMENT, no necesita secuencias

-- =====================================================
-- Verificar datos insertados
-- =====================================================
SELECT 'Categorías:' as '', COUNT(*) as total FROM categorias
UNION ALL
SELECT 'Consolas:', COUNT(*) FROM consolas
UNION ALL
SELECT 'Juegos:', COUNT(*) FROM juegos
UNION ALL
SELECT 'Usuarios:', COUNT(*) FROM usuarios;