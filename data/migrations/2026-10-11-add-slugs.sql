-- =====================================================
-- Migración: slugs de contenido (ítem 7.2)
-- Fecha: 2026-10-11
-- Fuente: docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md (7.2)
-- Idempotente: ADD COLUMN IF NOT EXISTS / CREATE INDEX IF NOT EXISTS
--
--   - juegos.slug:   URL limpia de la ficha       → /juego/{slug}
--   - consolas.slug: URL limpia del catálogo       → /consola/{slug}
--
-- El backfill de los slugs se hace con scripts/backfill-slugs.php (los acentos
-- y la unicidad no se resuelven de forma fiable en SQL puro). Columnas
-- anulables + índice UNIQUE parcial: se poblan SIEMPRE desde la app.
-- =====================================================

ALTER TABLE public.juegos
    ADD COLUMN IF NOT EXISTS slug VARCHAR(220);

ALTER TABLE public.consolas
    ADD COLUMN IF NOT EXISTS slug VARCHAR(120);

CREATE UNIQUE INDEX IF NOT EXISTS uq_juegos_slug
    ON public.juegos (slug)
    WHERE slug IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_consolas_slug
    ON public.consolas (slug)
    WHERE slug IS NOT NULL;
