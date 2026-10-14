# Runbook de despliegue - ROMs Vault

**Fecha:** 2026-10-13
**Ámbito:** operaciones - despliegue, migraciones, traducciones y smoke tests
**Estado:** vivo (mantener actualizado con cada cambio de infraestructura)
**Origen:** roadmap FASE 10, ítem "Runbook de operaciones"

---

## 0. Descripción

Procedimiento operativo reproducible para desplegar, migrar, traducir y
verificar ROMs Vault, con los runbooks de cada operación puntual referenciados.

---

## 1. Arquitectura de despliegue

- **Aplicación:** PHP 8.2 vanilla + Apache, empaquetada en contenedor Docker
  (`Dockerfile` basado en `php:8.2-apache`).
- **Vercel:** despliegue como contenedor; Vercel inyecta `$PORT` y
  `docker-entrypoint.sh` regenera el `.env` en el arranque.
- **`vercel.json` se mantiene con `"rewrites": []`**: los rewrites del edge
  pierden el query string al proxyar al contenedor y rompen las URLs limpias.
- **Enrutado:** `.htaccess` en Apache y `router.php` en `php -S`.
- **`src/`** nunca se sirve por HTTP (`router.php` 403 y `.htaccess` `[F,L]`).
- **BD:** PostgreSQL (Neon en producción con SSL; local en `127.0.0.1`).

---

## 2. Requisitos

| Entorno | Requisitos |
|---|---|
| Local (dev) | PHP 8.1+, PostgreSQL 16+, Composer |
| Contenedor | Docker |
| Deploy | Vercel linkeado (`.vercel/project.json`) o self-hosted |
| Traducción | LibreTranslate (Docker) o Google Cloud Translation |

---

## 3. Variables de entorno

La lista **completa está en `.env.example`** (47 variables comentadas). En
producción se configuran en **Vercel → Settings → Environment Variables**
(o `--env-file` en Docker).

Notas:

- Los secretos van **solo** en `.env` local / Vercel; nunca al repo
  (`.gitignore` ignora `.env*`; `.env.example` sí se versiona).
- Marca `[NO USADA]`: `SESSION_SECRET`, `GOOGLE_CLIENT_*`, `DB_CHARSET`,
  `R2_ACCOUNT_ID` (la app no las lee; la plantilla las deja vacías).
- `.env.example` y `.env` mantienen **el mismo conjunto de variables**.

### 3.1 ¿Qué variables llegan al contenedor?

`docker-entrypoint.sh` **regenera** el `.env` del contenedor a partir de esta
lista **fija** (no de todas las variables del entorno):

```
DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD, SESSION_SECRET,
JWT_SECRET/JWT_EXPIRATION/JWT_REFRESH_THRESHOLD, GOOGLE_CLIENT_ID/SECRET,
RATE_LIMIT_MAX/WINDOW, ALLOWED_ORIGINS, TRUSTED_PROXIES,
AUTH_LOG_DIR, LOG_MAX_BYTES,
R2_ACCOUNT_ID/R2_ACCESS_KEY_ID/R2_ACCESS_KEY_SECRET/R2_ENDPOINT/R2_BUCKET/
R2_PUBLIC_BASE/R2_PUBLIC_HOST
```

El resto (`SIGNED_URL_TTL`, `GDRIVE_ALLOWED_HOSTS`, `DOWNLOAD_RATE_LIMIT_*`,
`PRELOAD_MAX_BYTES`, `ENABLE_EMULATOR_THREADS`, `I18N_LANG`, `TRANSLATE_*`,
`R2_REGION`, `DB_SSLMODE`, `AUTH_*`, `TFA_*`) **no** se puede sobrescribir desde
el servidor hoy: el runbook funciona **por defecto**, no las sobrescribas.
Ampliar el entrypoint para regenerar todas es una mejora pendiente (cambio de
código; requiere aprobación).

---

## 4. Despliegue

### 4.1 Vercel (recomendado)

1. Push a `main` → deploy automático (integración Git de Vercel).
2. Ajustar variables de entorno en Vercel.
3. Redeploy tras cambiar variables (se regeneran en el arranque).

### 4.2 Self-hosted (contenedor)

```bash
docker build -t roms-vault .
docker run -p 8080:80 --env-file .env roms-vault
```

La app queda en `http://localhost:8080`.

---

## 5. Base de datos: migraciones y seeds

Orden (FKs) para un entorno nuevo:

```
psql -f data/roms-vaultDB-postgreSQL.sql
psql -f data/import_data.sql
psql -f data/emuladores.sql
```

Migraciones idempotentes: `data/migrations/*.sql` (ya fusionadas en el esquema
base; se listan en `AGENTS.md`). Aplicar en orden por fecha.

---

## 6. LibreTranslate (traducción de contenido)

Ver `docs/2026-10-13-configuracion-libretranslate-memoria-y-backfill.md`.

- **1 worker (`LT_THREADS=1`)** obligatorio: con 4 workers (~3.6 GiB) sufre
  `SIGKILL` por OOM. Con 1 worker: ~140-480 MiB.
- `LT_LOAD_ONLY=es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar` limita idiomas cargados.
- Imagen con modelos precargados (`romv-libretranslate:models`, ~6.5 GB) o
  volumen persistente `-v lt_models:/home/libretranslate/.local`.
- La app apunta con `TRANSLATE_PROVIDER` + `TRANSLATE_LIBRE_URL`.
- Health check: `GET /languages` y `POST /translate`.
- En Vercel, `TRANSLATE_*` no entra en el `.env` regenerado; el valor por
  defecto del runbook es **por defecto, no lo sobrescribas**.

---

## 7. Backfills (CLI)

`scripts/` está **excluido de la imagen** (`.dockerignore`), así que los scripts
se ejecutan **desde el clon del repo**, no dentro del contenedor.

- **Backfill de idiomas de juegos (paso pendiente en producción):**
  `docs/2026-10-13-despliegue-backfill-idiomas-produccion.md`.
- Traducciones de contenido: `docs/2026-10-13-configuracion-libretranslate-memoria-y-backfill.md`.
- Normalizar idiomas: `php scripts/normalizar-idiomas.php`.
- Migrar imágenes a R2: `docs/2026-10-12-migracion-imagenes-r2-implementacion.md`.

Los scripts son idempotentes, respetan `origen='manual'` y ofrecen `--dry-run`.

---

## 8. Smoke tests

Contra el deploy (HTTP):

| Comprobación | Esperado |
|---|---|
| `GET /` | 200 |
| `GET /home/show/1` | 200 |
| `GET /ajax_catalog.php` | 200 |
| `GET /rom_proxy.php` (sin firma) | rechazo |
| `GET /src/config/database.php` | 403 |

Y: login admin, panel admin, un juego con emulador (Range Requests) y las
imágenes desde R2.

---

## 9. Gotchas

1. **`vercel.json`** se queda con `"rewrites": []`. No lo toques.
2. **El `.env` del contenedor es regenerado**: variables fuera de la lista del
   entrypoint no se pueden sobrescribir (funciona por defecto).
3. **`vercel.json` + `.htaccess`**: las URLs limpias y legacy conviven; no
   renombres `rom_proxy.php` ni `ajax_*.php` (enlaces HMAC y `fetch()`).
4. **`DB_SSLMODE`**: dev local `disable`; Neon `require` (default de código).
5. **Rate limit file-based** en `sys_get_temp_dir()`: no se comparte entre
   procesos/instancias.
6. **Anti-SSRF del proxy**: solo hosts de Drive (`GDRIVE_ALLOWED_HOSTS`).
7. **CSP** con excepción EmulatorJS (`unsafe-eval`, `blob:`): no apretarla.
8. **Neon SSL + endpoint-ID**: `src/config/database.php` añade el workaround.
9. **`public/uploads/`**: permisos de escritura para portadas.

---

## 10. Rotación de secretos y backups

- **Rotar** `JWT_SECRET`, `R2_ACCESS_KEY_SECRET`, `DB_PASSWORD` y la contraseña
  del admin; actualizar en `.env`/Vercel y redesplegar.
- **Backup R2**: no hace falta (objeto público + originales en Drive).
- **Backup BD**: `pg_dump` de Neon antes de operaciones masivas (backfills).
- Los backfills hacen **rollback** natural por ser idempotentes; ver los docs
  fechados de cada uno.

---

## 11. Rollback

- **Código (Vercel):** promover el deploy anterior (Instant Rollback).
- **Contenedor self-hosted:** volver a la imagen/tag previa.
- **BD:** las migraciones son aditivas/idempotentes; para revertir un backfill,
  reejecutar el script o anular el campo según el doc del paso.
- **Migración `idiomas`:** `UPDATE juegos_traducciones SET idiomas = NULL`
  (recordar el gotcha del `origen_hash`).

---

## 12. Referencias

- `AGENTS.md`, `README.md`
- `.env.example` (fuente de verdad de las variables)
- `docs/2026-10-13-despliegue-backfill-idiomas-produccion.md`
- `docs/2026-10-11-ci-cd-github-actions.md`
- `docs/2026-10-11-trusted-proxies-fase-b.md`
- `docs/2026-10-13-configuracion-libretranslate-memoria-y-backfill.md`
- `docs/2026-10-12-migracion-imagenes-r2-implementacion.md`
