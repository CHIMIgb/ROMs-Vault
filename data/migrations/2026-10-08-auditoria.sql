-- =====================================================
-- Migración: tabla de auditoría de autenticación (A09)
-- Fecha: 2026-10-08
-- Fuente: docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md (2.1)
-- Idempotente: CREATE TABLE/INDEX IF NOT EXISTS
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

CREATE INDEX IF NOT EXISTS idx_auditoria_created_at ON public.auditoria (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_auditoria_evento ON public.auditoria (evento);
CREATE INDEX IF NOT EXISTS idx_auditoria_username ON public.auditoria (username);
CREATE INDEX IF NOT EXISTS idx_auditoria_user_id ON public.auditoria (user_id);