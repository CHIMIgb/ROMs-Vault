-- Migración: cola de traducciones asíncronas (BD-based, sin broker)
-- Idempotente. Usa patrón SKIP LOCKED para que varios workers sean seguros.

CREATE TABLE IF NOT EXISTS traduccion_cola (
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

-- Índice para reclamar trabajos pendientes
CREATE INDEX IF NOT EXISTS idx_traduccion_cola_pendientes
    ON traduccion_cola (estado, disponible_en);

-- Dedupe: un solo job activo (pendiente o en_proceso) por entidad
CREATE UNIQUE INDEX IF NOT EXISTS uq_traduccion_cola_activa
    ON traduccion_cola (entidad, entidad_id)
    WHERE estado IN ('pendiente','en_proceso');

-- Trigger para mantener actualizado_en
CREATE OR REPLACE FUNCTION fn_traduccion_cola_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.actualizado_en = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_trigger
        WHERE tgname = 'trg_traduccion_cola_updated_at'
    ) THEN
        CREATE TRIGGER trg_traduccion_cola_updated_at
        BEFORE UPDATE ON traduccion_cola
        FOR EACH ROW
        EXECUTE FUNCTION fn_traduccion_cola_updated_at();
    END IF;
END;
$$;
