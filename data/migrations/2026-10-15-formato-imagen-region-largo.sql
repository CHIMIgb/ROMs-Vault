-- =====================================================
-- Migración: ampliar formato_imagen y region en `juegos`
-- Fecha: 2026-10-15
-- Idempotente: solo amplía, nunca recorta. No destructiva.
--
-- Problema: `formato_imagen VARCHAR(20)` desbordaba con valores
-- legítimos del formulario como "Windows Binary (.exe)" (21 chars).
-- PostgreSQL no trunca: `Model::update()` lanzaba
--   SQLSTATE[22001] String data, right truncated:
--   value too long for type character varying(20)
-- y el controlador no la capturaba → HTTP 500 al guardar el juego.
--
-- `region VARCHAR(10)` no puede desbordarse desde el formulario
-- (es un <select> con opciones cortas: PAL, NTSC, NTSC-J, NTSC-U),
-- pero se amplía igualmente por consistencia y para eliminar el
-- riesgo latente ante peticiones manipuladas o futuros valores.
--
-- Límite ampliado a 50 en ambos casos; la validación server-side
-- (AdminController::validarJuego) y los `maxlength` de los inputs
-- deben coincidir con este valor.
-- =====================================================

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
          FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = 'juegos'
           AND column_name  = 'formato_imagen'
           AND data_type    = 'character varying'
           AND character_maximum_length < 50
    ) THEN
        ALTER TABLE public.juegos
            ALTER COLUMN formato_imagen TYPE VARCHAR(50);
    END IF;
END $$;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
          FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = 'juegos'
           AND column_name  = 'region'
           AND data_type    = 'character varying'
           AND character_maximum_length < 50
    ) THEN
        ALTER TABLE public.juegos
            ALTER COLUMN region TYPE VARCHAR(50);
    END IF;
END $$;
