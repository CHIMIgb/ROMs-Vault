-- =====================================================
-- Crear la base de datos (ejecutar como superusuario)
-- =====================================================
-- CREATE DATABASE "roms-vault" WITH ENCODING 'UTF8' LC_COLLATE='Spanish_Spain.1252' LC_CTYPE='Spanish_Spain.1252';

-- =====================================================
-- Tabla: categorias
-- =====================================================
CREATE TABLE IF NOT EXISTS public.categorias (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: consolas
-- =====================================================
CREATE TABLE IF NOT EXISTS public.consolas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fabricante VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: roles
-- =====================================================
CREATE TABLE IF NOT EXISTS public.roles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: personas
-- =====================================================
CREATE TABLE IF NOT EXISTS public.personas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: juegos
-- =====================================================
CREATE TABLE IF NOT EXISTS public.juegos (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    imagen VARCHAR(300),
    descripcion TEXT,
    consola_id INTEGER REFERENCES public.consolas(id) ON DELETE SET NULL,
    categoria_id INTEGER REFERENCES public.categorias(id) ON DELETE SET NULL,
    region VARCHAR(10),
    fecha_lanzamiento DATE,
    idiomas VARCHAR(200),
    formato_imagen VARCHAR(20),
    game_id_code VARCHAR(50),
    google_drive_file_id VARCHAR(100) NOT NULL,
    google_drive_view_link VARCHAR(500),
    size_bytes BIGINT DEFAULT 0,
    downloads_count INTEGER DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: usuarios
-- =====================================================
CREATE TABLE IF NOT EXISTS public.usuarios (
    id SERIAL PRIMARY KEY,
    persona_id INTEGER REFERENCES public.personas(id) ON DELETE CASCADE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INTEGER REFERENCES public.roles(id) ON DELETE SET NULL,
    activo BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: descargas
-- =====================================================
CREATE TABLE IF NOT EXISTS public.descargas (
    id SERIAL PRIMARY KEY,
    juego_id INTEGER REFERENCES public.juegos(id) ON DELETE CASCADE,
    cookie_id VARCHAR(64) NOT NULL,
    ip_address INET, -- PostgreSQL tiene tipo inet nativo
    user_agent TEXT,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed BOOLEAN DEFAULT FALSE
);

-- =====================================================
-- Índices para mejorar rendimiento
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_juegos_consola ON public.juegos(consola_id);
CREATE INDEX IF NOT EXISTS idx_juegos_categoria ON public.juegos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo ON public.juegos(activo);
CREATE INDEX IF NOT EXISTS idx_descargas_juego ON public.descargas(juego_id);
CREATE INDEX IF NOT EXISTS idx_descargas_cookie ON public.descargas(cookie_id);
CREATE INDEX IF NOT EXISTS idx_descargas_fecha ON public.descargas(downloaded_at);
CREATE INDEX IF NOT EXISTS idx_usuarios_persona ON public.usuarios(persona_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_rol ON public.usuarios(rol_id);

-- =====================================================
-- Función para actualizar updated_at automáticamente
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers para updated_at
CREATE TRIGGER update_juegos_updated_at BEFORE UPDATE ON public.juegos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_usuarios_updated_at BEFORE UPDATE ON public.usuarios
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- =====================================================
-- Insertar datos iniciales
-- =====================================================

-- Insertar categorías
INSERT INTO public.categorias (nombre, descripcion, activo) VALUES
('Acción', 'Juegos de acción', TRUE),
('Aventura', 'Juegos de aventura', TRUE),
('Deportes', 'Juegos de deportes', TRUE),
('Carreras', 'Juegos de carreras', TRUE),
('Disparos', 'Juegos de disparos', TRUE),
('Rol', 'Juegos de rol', TRUE),
('Estrategia', 'Juegos de estrategia', TRUE),
('Plataformas', 'Juegos de plataformas', TRUE);

-- Insertar consolas
INSERT INTO public.consolas (nombre, descripcion, fabricante, activo) VALUES
('PSP', 'PlayStation Portable', 'Sony', TRUE),
('PS1', 'PlayStation 1', 'Sony', TRUE),
('PS2', 'PlayStation 2', 'Sony', TRUE),
('PS3', 'PlayStation 3', 'Sony', TRUE),
('PS4', 'PlayStation 4', 'Sony', TRUE),
('PS5', 'PlayStation 5', 'Sony', TRUE),
('Nintendo Switch', 'Nintendo Switch', 'Nintendo', TRUE),
('Game Boy Advance', 'Game Boy Advance', 'Nintendo', TRUE),
('Nintendo DS', 'Nintendo DS', 'Nintendo', TRUE),
('Xbox 360', 'Xbox 360', 'Microsoft', TRUE);

-- Insertar roles
INSERT INTO public.roles (nombre, descripcion) VALUES
('Administrador', 'Acceso total al sistema'),
('Usuario', 'Acceso básico de lectura'),
('Editor', 'Puede editar contenido');

-- Insertar una persona de ejemplo (admin)
INSERT INTO public.personas (nombre, apellido, email, telefono) VALUES
('Admin', 'Principal', 'admin@romsvault.com', '123456789');

-- Insertar usuario admin (contraseña: 'password')
-- IMPORTANTE: Cambia este hash por uno generado con password_hash()
INSERT INTO public.usuarios (persona_id, username, password_hash, rol_id, activo) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, TRUE);

-- Insertar juegos de ejemplo
INSERT INTO public.juegos (titulo, imagen, descripcion, consola_id, categoria_id, region, fecha_lanzamiento, idiomas, formato_imagen, game_id_code, google_drive_file_id, google_drive_view_link, size_bytes, activo) VALUES
('WipEout Pulse', NULL, 'Juego de carreras futurista con naves de alta velocidad', 1, 4, 'PAL', '2007-12-07', 'Español, Inglés', 'ISO', 'ULES-12345', '1fVWgArFcMMUFVX5SXkdMR5pQYU2uyXYj', 'https://drive.google.com/file/d/1fVWgArFcMMUFVX5SXkdMR5pQYU2uyXYj', 452984832, TRUE),
('WipEout Pure', NULL, 'La primera entrega de WipEout para PSP', 1, 4, 'NTSC', '2005-03-24', 'Inglés', 'ISO', 'ULES-12346', '1zRYOBNATxrbsN1WLzLioTRQD_tTx8L-z', 'https://drive.google.com/file/d/1zRYOBNATxrbsN1WLzLioTRQD_tTx8L-z', 435159040, TRUE),
('Carnivores', NULL, 'Caza de dinosaurios en un mundo prehistórico', 1, 2, 'NTSC', '2002-11-15', 'Inglés', 'ISO', 'ULES-12347', '1Fv6nQ27Ip9sy5prOoc1dQe-hel-7A6hq', 'https://drive.google.com/file/d/1Fv6nQ27Ip9sy5prOoc1dQe-hel-7A6hq', 417333248, TRUE);

-- =====================================================
-- Verificar datos insertados
-- =====================================================
SELECT 'Categorías:' as tipo, COUNT(*) as total FROM public.categorias
UNION ALL
SELECT 'Consolas:', COUNT(*) FROM public.consolas
UNION ALL
SELECT 'Juegos:', COUNT(*) FROM public.juegos
UNION ALL
SELECT 'Usuarios:', COUNT(*) FROM public.usuarios;