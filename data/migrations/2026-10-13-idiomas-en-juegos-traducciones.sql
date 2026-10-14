-- =====================================================
-- Migración: idiomas traducidos de juegos (i18n, bloque 2)
-- Fecha: 2026-10-13
-- Idempotente: ADD COLUMN IF NOT EXISTS. No destructiva.
--
-- `juegos.idiomas` es contenido editorial traducible (lista de idiomas
-- disponibles del juego). Su traducción al idioma activo se guarda aquí,
-- junto a la descripción, con el mismo patrón que juegos.descripcion.
--
-- El valor almacenado es la lista ya traducida y normalizada, separada por
-- comas (p. ej. en.json => "Spanish, English, French"), lista para pintarse
-- en el catálogo y en la ficha sin cambios de presentación.
-- =====================================================

ALTER TABLE public.juegos_traducciones
    ADD COLUMN IF NOT EXISTS idiomas TEXT;
