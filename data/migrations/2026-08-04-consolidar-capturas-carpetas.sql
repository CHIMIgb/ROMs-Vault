-- =====================================================
-- Migración: Consolidar capturas en la carpeta del juego (2026-08-04)
-- PostgreSQL
--
-- Objetivo:
--   Corregir las rutas de capturas que quedaron con sufijo de carpeta
--   duplicado por un bug de resolución de carpeta (cada imagen se guardaba
--   en su propia subcarpeta, p. ej.
--   public/uploads/{consola}/{juego}-2/captura-2.webp).
--
--   Ahora todas las imágenes del juego viven en la misma carpeta {juego}/,
--   por lo que estas rutas deben apuntar a:
--   public/uploads/{consola}/{juego}/captura-N.webp
--
--   Solo reescribe rutas cuyo archivo sea una captura (captura-N.webp)
--   dentro de una carpeta con sufijo numérico, así que no afecta juegos
--   legítimos. Idempotente y no destructivo.
-- =====================================================

UPDATE public.juegos
SET capturas = (
    SELECT json_agg(
        regexp_replace(
            elem,
            '^(public/uploads/[^/]+/[^/]+)-[0-9]+/(captura-[0-9]+\.webp)$',
            '\1/\2'
        )
    )
    FROM json_array_elements_text(capturas::json) AS elem
)
WHERE capturas IS NOT NULL
  AND capturas <> '[]'
  AND capturas ~ '-[0-9]+/captura-[0-9]+\.webp';
