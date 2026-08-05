-- =====================================================
-- Migración: Emulación online por consola (2026-08-04)
-- PostgreSQL
--
-- Objetivo:
--   Permitir al administrador activar/desactivar la emulación online
--   (EmulatorJS) de cada consola desde el CRUD de consolas, sin afectar
--   la descarga de ROMs.
--
-- Default TRUE: las consolas existentes conservan su comportamiento
-- actual (la emulación queda habilitada hasta que el admin la desactive).
-- Idempotente (IF NOT EXISTS) y no destructivo.
-- =====================================================

ALTER TABLE public.consolas
    ADD COLUMN IF NOT EXISTS emulacion_online BOOLEAN NOT NULL DEFAULT TRUE;
