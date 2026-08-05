-- =====================================================
-- Migración: Capturas de juegos (2026-08-04)
-- PostgreSQL
--
-- Objetivo:
--   Almacenar las capturas de cada juego para el carrusel de la ficha
--   de detalle. El campo guarda un array JSON de rutas relativas
--   (p. ej. ["public/uploads/playstation/resident-evil-2/captura-1.webp"]).
--
--   La portada se sigue guardando en `imagen`; `capturas` es opcional.
--   Idempotente (IF NOT EXISTS) y no destructivo.
-- =====================================================

ALTER TABLE public.juegos
    ADD COLUMN IF NOT EXISTS capturas TEXT;
