-- =====================================================
-- Migración: lockout por cuenta + 2FA TOTP (A07)
-- Fecha: 2026-10-08
-- Fuente: docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md (2.2)
-- Idempotente: ADD COLUMN IF NOT EXISTS
--   - login_failed_attempts: contador de fallos consecutivos de login
--   - locked_until: timestamp hasta el que la cuenta queda bloqueada (NULL = sin bloqueo)
--   - tfa_secret: secreto base32 TOTP (NULL = sin 2FA configurado)
--   - tfa_enabled: indica si el 2FA está activo (solo rol admin)
-- =====================================================

ALTER TABLE public.usuarios
    ADD COLUMN IF NOT EXISTS login_failed_attempts INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS tfa_secret VARCHAR(64),
    ADD COLUMN IF NOT EXISTS tfa_enabled BOOLEAN NOT NULL DEFAULT FALSE;

-- Índice parcial para consultar cuentas bloqueadas de forma eficiente
-- (presente solo en la migración; el schema base lo declara junto al CREATE TABLE)
CREATE INDEX IF NOT EXISTS idx_usuarios_locked_until
    ON public.usuarios (locked_until)
    WHERE locked_until IS NOT NULL;