-- =====================================================
-- Migración: traducciones de contenido (i18n, bloque 2)
-- Fecha: 2026-10-12
-- Fuente: docs/2026-10-12-plan-traduccion-contenido-bd.md (sección 6.1)
-- Idempotente: CREATE TABLE / INDEX IF NOT EXISTS. No destructiva.
--
-- Traduce el contenido editorial que se muestra en el catálogo público:
--   - categorias_traducciones: categorias.nombre + categorias.descripcion
--   - juegos_traducciones:     juegos.descripcion
--
-- El 'es' canónico vive en las tablas base; aquí SOLO se guardan idiomas
-- distintos de 'es' (el resolver cae a la columna base como fallback).
--
-- Los nombres propios NO se traducen y por eso NO hay consolas_traducciones:
--   juegos.titulo, consolas.nombre, consolas.descripcion (nombre largo de la
--   plataforma), fabricante, region, emuladores.nombre.
--
-- origen:      'auto'   = generado por el proveedor (Google)
--              'manual' = corregido a mano en el admin (nunca se sobrescribe)
-- revisado:    marca de revisión humana de una traducción automática
-- origen_hash: sha256 del texto 'es' al momento de traducir; sirve para
--              detectar cambios del canónico y decidir si refrescar (incremental)
-- =====================================================

-- 1) Traducciones de categorías (nombre + descripción)
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

-- 2) Traducciones de juegos (descripción)
CREATE TABLE IF NOT EXISTS public.juegos_traducciones (
    juego_id      INTEGER      NOT NULL REFERENCES public.juegos(id) ON DELETE CASCADE,
    idioma        VARCHAR(5)   NOT NULL,
    descripcion   TEXT,
    origen        VARCHAR(10)  NOT NULL DEFAULT 'manual',
    revisado      BOOLEAN      NOT NULL DEFAULT FALSE,
    origen_hash   CHAR(64),
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (juego_id, idioma),
    CONSTRAINT chk_juegos_trad_idioma CHECK (idioma <> 'es'),
    CONSTRAINT chk_juegos_trad_origen CHECK (origen IN ('auto', 'manual'))
);

-- 3) Índices por idioma (consultas de backfill / conteo de faltantes)
CREATE INDEX IF NOT EXISTS idx_categorias_trad_idioma ON public.categorias_traducciones (idioma);
CREATE INDEX IF NOT EXISTS idx_juegos_trad_idioma     ON public.juegos_traducciones (idioma);
