# Revisión de credenciales por defecto — ítem 2.6

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [2.6 Revisar credenciales por defecto](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)

## Verificación: CUMPLE

Revisión de credenciales por defecto (evidencia recogida durante la auditoría de datos
sensibles en tests, sesión del 2026-10-08):

1. **`admin` / `admin123`** solo existe en `data/test_seeds.sql` (BD local de pruebas
   `roms-vault-test`, se reseedea en cada clase de Integration). **No** es credencial de
   producción.
2. **Producción** no usa credenciales por defecto: la BD (Neon) se provisiona con
   credenciales reales a través de variables de entorno (`DB_USER`, `DB_PASSWORD`, etc.),
   regeneradas por `docker-entrypoint.sh` en cada despliegue; `JWT_SECRET` y
   `SESSION_SECRET` también entran por `.env` y **nunca** están en el repo.
3. Estados de `usuario`: el hash de `admin123` en test_seeds es bcrypt (nunca texto plano);
   en la BD de desarrollo local `roms-vault` también se usa la semilla de tests solo para
   desarrollo, no en Vercel/Neon.
4. `.gitignore` excluye `.env*` (excepto `.env.example`, que no contiene secretos).
5. Sin coincidencias de secretos reales (JWT/SESSION/Google OAuth) en el repo.

No requiere cambios de código.