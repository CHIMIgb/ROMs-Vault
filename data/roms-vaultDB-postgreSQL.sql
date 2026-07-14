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
    nombre VARCHAR(100) UNIQUE NOT NULL,
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
    plays_count INTEGER DEFAULT 0,
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
    ip_address INET,
    user_agent TEXT,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed BOOLEAN DEFAULT FALSE
);

-- =====================================================
-- Índices
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
-- Función y triggers para updated_at
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_juegos_updated_at BEFORE UPDATE ON public.juegos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_usuarios_updated_at BEFORE UPDATE ON public.usuarios
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();