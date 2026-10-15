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
-- Tabla: categorias_traducciones  (i18n bloque 2)
-- Traducciones de categorias (nombre + descripcion). Solo idiomas != 'es'.
-- =====================================================
CREATE TABLE IF NOT EXISTS public.categorias_traducciones (
    categoria_id  INTEGER      NOT NULL REFERENCES public.categorias(id) ON DELETE CASCADE,
    idioma        VARCHAR(5)   NOT NULL,
    nombre        VARCHAR(120),
    descripcion   TEXT,
    origen        VARCHAR(10)  NOT NULL DEFAULT 'manual',
    revisado      BOOLEAN      NOT NULL DEFAULT FALSE,
    origen_hash   CHAR(64),
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (categoria_id, idioma),
    CONSTRAINT chk_categorias_trad_idioma CHECK (idioma <> 'es'),
    CONSTRAINT chk_categorias_trad_origen CHECK (origen IN ('auto', 'manual'))
);

-- =====================================================
-- Tabla: consolas
-- =====================================================
CREATE TABLE IF NOT EXISTS public.consolas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(120),
    descripcion TEXT,
    fabricante VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    emulacion_online BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Tabla: emuladores  (recomendados por consola)
-- =====================================================
CREATE TABLE IF NOT EXISTS public.emuladores (
    id SERIAL PRIMARY KEY,
    consola_id INTEGER NOT NULL REFERENCES public.consolas(id) ON DELETE CASCADE,
    nombre VARCHAR(100) NOT NULL,
    plataformas VARCHAR(100) NOT NULL DEFAULT 'PC',
    url VARCHAR(300) NOT NULL,
    es_alterno BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_emulador_por_consola UNIQUE (consola_id, es_alterno)
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
    slug VARCHAR(220),
    imagen VARCHAR(300),
    capturas TEXT,
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
-- Tabla: juegos_traducciones  (i18n bloque 2)
-- Traducciones de juegos (descripcion). Solo idiomas != 'es'.
-- =====================================================
CREATE TABLE IF NOT EXISTS public.juegos_traducciones (
    juego_id      INTEGER      NOT NULL REFERENCES public.juegos(id) ON DELETE CASCADE,
    idioma        VARCHAR(5)   NOT NULL,
    descripcion   TEXT,
    idiomas       TEXT,
    origen        VARCHAR(10)  NOT NULL DEFAULT 'manual',
    revisado      BOOLEAN      NOT NULL DEFAULT FALSE,
    origen_hash   CHAR(64),
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (juego_id, idioma),
    CONSTRAINT chk_juegos_trad_idioma CHECK (idioma <> 'es'),
    CONSTRAINT chk_juegos_trad_origen CHECK (origen IN ('auto', 'manual'))
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
    login_failed_attempts INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMPTZ,
    tfa_secret VARCHAR(64),
    tfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
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
-- Tabla: auditoria (registro de auditoría de autenticación, A09)
-- =====================================================
CREATE TABLE IF NOT EXISTS public.auditoria (
    id BIGSERIAL PRIMARY KEY,
    evento VARCHAR(50) NOT NULL,
    nivel VARCHAR(10) NOT NULL DEFAULT 'info',
    username VARCHAR(64),
    user_id INTEGER REFERENCES public.usuarios(id) ON DELETE SET NULL,
    rol_id INTEGER,
    ip VARCHAR(45),
    contexto JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================
-- Tabla: traduccion_cola (cola de traducciones asíncronas, sin broker)
-- =====================================================
-- Cola basada en PostgreSQL: se reclama con SELECT ... FOR UPDATE SKIP LOCKED,
-- así que varios workers pueden correr en paralelo sin pisarse. La alimenta el
-- admin al guardar (modo async) y la drena scripts/worker-traducciones.php.
-- Ojo: usa la columna `actualizado_en` (no `updated_at`), por lo que NO puede
-- reutilizar update_updated_at_column() y define su propia función más abajo.
CREATE TABLE IF NOT EXISTS public.traduccion_cola (
    id             BIGSERIAL PRIMARY KEY,
    entidad        VARCHAR(20)  NOT NULL CHECK (entidad IN ('categoria','juego')),
    entidad_id     INTEGER      NOT NULL,
    campos         JSONB        NOT NULL,
    estado         VARCHAR(20)  NOT NULL DEFAULT 'pendiente'
                   CHECK (estado IN ('pendiente','en_proceso','ok','fallido')),
    intentos       SMALLINT     NOT NULL DEFAULT 0,
    max_intentos   SMALLINT     NOT NULL DEFAULT 5,
    disponible_en  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    bloqueado_en   TIMESTAMPTZ,
    error          TEXT,
    creado_en      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    actualizado_en TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- =====================================================
-- Índices
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_juegos_consola ON public.juegos(consola_id);
CREATE INDEX IF NOT EXISTS idx_juegos_categoria ON public.juegos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo ON public.juegos(activo);
CREATE INDEX IF NOT EXISTS idx_juegos_drive_file_id ON public.juegos(google_drive_file_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_juegos_slug   ON public.juegos(slug)   WHERE slug IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_consolas_slug ON public.consolas(slug) WHERE slug IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_juegos_activo_consola   ON public.juegos(activo, consola_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_categoria ON public.juegos(activo, categoria_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_region    ON public.juegos(activo, region);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_creado    ON public.juegos(activo, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_descargas ON public.juegos(activo, downloads_count DESC);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_jugadas   ON public.juegos(activo, plays_count DESC);
CREATE INDEX IF NOT EXISTS idx_categorias_trad_idioma  ON public.categorias_traducciones(idioma);
CREATE INDEX IF NOT EXISTS idx_juegos_trad_idioma      ON public.juegos_traducciones(idioma);
CREATE INDEX IF NOT EXISTS idx_emuladores_consola ON public.emuladores(consola_id);
CREATE INDEX IF NOT EXISTS idx_descargas_juego ON public.descargas(juego_id);
CREATE INDEX IF NOT EXISTS idx_descargas_cookie ON public.descargas(cookie_id);
CREATE INDEX IF NOT EXISTS idx_descargas_fecha ON public.descargas(downloaded_at);
CREATE INDEX IF NOT EXISTS idx_usuarios_persona ON public.usuarios(persona_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_rol ON public.usuarios(rol_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_locked_until ON public.usuarios (locked_until)
    WHERE locked_until IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_auditoria_created_at ON public.auditoria (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_auditoria_evento ON public.auditoria (evento);
CREATE INDEX IF NOT EXISTS idx_auditoria_username ON public.auditoria (username);
CREATE INDEX IF NOT EXISTS idx_auditoria_user_id ON public.auditoria (user_id);

-- Cola de traducciones: los workers solo buscan jobs vencidos en 'pendiente'.
CREATE INDEX IF NOT EXISTS idx_traduccion_cola_pendientes
    ON public.traduccion_cola (estado, disponible_en);
-- Dedupe: un único job activo por entidad (el guardado reescribe el existente).
CREATE UNIQUE INDEX IF NOT EXISTS uq_traduccion_cola_activa
    ON public.traduccion_cola (entidad, entidad_id)
    WHERE estado IN ('pendiente','en_proceso');

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

-- traduccion_cola: su columna es `actualizado_en`, así que necesita su propia
-- función (update_updated_at_column() escribe NEW.updated_at y fallaría).
CREATE OR REPLACE FUNCTION fn_traduccion_cola_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.actualizado_en = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_trigger
        WHERE tgname = 'trg_traduccion_cola_updated_at'
          AND tgrelid = 'public.traduccion_cola'::regclass
    ) THEN
        CREATE TRIGGER trg_traduccion_cola_updated_at
            BEFORE UPDATE ON public.traduccion_cola
            FOR EACH ROW EXECUTE FUNCTION fn_traduccion_cola_updated_at();
    END IF;
END;
$$;
