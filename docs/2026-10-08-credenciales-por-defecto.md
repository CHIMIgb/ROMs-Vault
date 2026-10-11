# Revisión de credenciales por defecto — ítem 2.6

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [2.6 Revisar credenciales por defecto](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)

## Verificación: CUMPLE

Revisión de credenciales por defecto (evidencia recogida durante la auditoría de datos
sensibles en tests, sesión del 2026-10-08):

1. **El usuario `admin`** de la BD de prueba usa una contraseña que **ya no está en el
   repo**: vive en `tests/.env.test` (gitignored; plantilla `tests/.env.test.example`).
   El seed `data/test_seeds.sql` solo contiene el placeholder `__ADMIN_PASSWORD_HASH__`,
   que `IntegrationTestCase::resetDatabase()` sustituye por `password_hash()` en runtime.
   **No** es credencial de producción.
2. **Producción** no usa credenciales por defecto: la BD (Neon) se provisiona con
   credenciales reales a través de variables de entorno (`DB_USER`, `DB_PASSWORD`, etc.),
   regeneradas por `docker-entrypoint.sh` en cada despliegue; `JWT_SECRET` y
   `SESSION_SECRET` también entran por `.env` y **nunca** están en el repo.
3. Estados de `usuario`: el hash del admin de test se genera en runtime con
   `password_hash()` (bcrypt, nunca texto plano); en la BD de desarrollo local `roms-vault`
   también se usa la semilla de tests solo para desarrollo, no en Vercel/Neon.
4. `.gitignore` excluye `.env*` (excepto `.env.example`, que no contiene secretos).
5. Sin coincidencias de secretos reales (JWT/SESSION/Google OAuth) en el repo.

No requiere cambios de código.

## Actualización (2026-10-10) — hashes fuera del repo

Verificación original (arriba) correcta, pero se endureció la política: **ningún hash
ni secreto de test se versiona**. Cambios posteriores a esta revisión:

- `data/test_seeds.sql` — hash del admin y `tfa_secret` → placeholders
  `__ADMIN_PASSWORD_HASH__` / `__TFA_SECRET__` (resueltos en runtime desde `tests/.env.test`).
- `data/import_data.sql` y `data/roms-vaultDB-MySQL.sql` — el hash por defecto del admin de
  producción (bcrypt de una contraseña conocida) → placeholder `__CAMBIAR_PASSWORD_HASH__`
  (no es un hash válido: el login queda bloqueado hasta asignar una contraseña real).
- `tests/.env.test` (gitignored) + `tests/.env.test.example` — secretos de test centralizados.

Detalle en [`2026-10-10-eliminacion-credenciales-hardcoded.md`](./2026-10-10-eliminacion-credenciales-hardcoded.md).