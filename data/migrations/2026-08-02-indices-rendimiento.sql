-- =====================================================
-- Migración: Índices de rendimiento (2026-08-02)
-- PostgreSQL
--
-- Objetivos:
--   1. Acelerar findByFileId() (rom_proxy.php, play.php, download.php)
--      que consulta por google_drive_file_id en cada reproducción/descarga.
--   2. Acelerar el catálogo público filtrado por activo + consola/categoría/región.
--   3. Acelerar el orden por defecto "más recientes" (created_at DESC).
--   4. Acelerar los rankings (Top 5 por descargas / jugadas) y las estadísticas.
--
-- Todos los índices son idempotentes (IF NOT EXISTS) y no destructivos.
-- =====================================================

-- 1) Búsqueda por Google Drive File ID (usado en cada play/download)
CREATE INDEX IF NOT EXISTS idx_juegos_drive_file_id ON public.juegos(google_drive_file_id);

-- 2) Catálogo público: filtros más comunes con activo = true
CREATE INDEX IF NOT EXISTS idx_juegos_activo_consola   ON public.juegos(activo, consola_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_categoria ON public.juegos(activo, categoria_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_region    ON public.juegos(activo, region);

-- 3) Orden por defecto: más recientes (created_at DESC) filtrando activos
CREATE INDEX IF NOT EXISTS idx_juegos_activo_creado    ON public.juegos(activo, created_at DESC);

-- 4) Rankings y ordenamientos por popularidad
CREATE INDEX IF NOT EXISTS idx_juegos_activo_descargas ON public.juegos(activo, downloads_count DESC);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_jugadas   ON public.juegos(activo, plays_count DESC);
