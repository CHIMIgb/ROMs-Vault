# Creación de `.dockerignore` (ítem 9.3)

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Commit de código:** `8a0aacd`
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§9.3)

---

## 1. Problema

El `Dockerfile` hace `COPY . .` (línea 19) y no existía `.dockerignore`, por lo
que **todo el contexto de build** entraba en la imagen: `tests/`, `docs/`,
`vendor/` local de Windows, `.env*` (potencialmente con secretos reales),
`.vercel/` (metadata de despliegue con tokens), backups de Excel, cachés de
PHPUnit, etc. Eso agranda la imagen, la hace más lenta de construir y expone
material que no debe distribuirse (A06 / hardening de la imagen).

## 2. Solución

Crear `.dockerignore` en la raíz con los patrones que el contexto de build
envía realmente a la imagen:

```
.git
.agents
.opencode
.vercel
.gitignore
.gitattributes
.editorconfig
.dockerignore
.phpunit.cache
.phpunit.result.cache

.env
.env.*

tests/
scratch/
docs/
data/backup registros DB/
vendor/

*.log
generate_imports.py
```

**Permanece en la imagen** (comprobado contra el árbol real): `src/`,
`endpoints/`, `public/` (incluidos `public/bios/` y `public/uploads/` — el
entrypoint los necesita), `index.php`, `router.php`, `.htaccess`,
`composer.json`, `composer.lock`, `data/*.sql` (inofensivos, documentan el
schema), `Dockerfile`, `docker-entrypoint.sh`, `docker/` (contiene
`php-production.ini`, necesario para el ítem 2.4) y `vercel.json`.

El `composer install --no-dev --optimize-autoloader` del `Dockerfile` sigue
instalando la dependencia de runtime **dentro** del contenedor; al excluir
`vendor/`, no se copia el vendor de desarrollo local.

## 3. Archivos modificados

| Acción | Archivo | Cambio |
|--------|---------|--------|
| CREAR | `.dockerignore` | Patrones de exclusión del contexto Docker |

## 4. Verificación (evidencia)

- `git status --short` → `.dockerignore` como untracked (se commitea).
- `git check-ignore .dockerignore` → **exit 1** (no lo esconde `.gitignore`).
- Cobertura manual de patrones contra `ls -A1` de la raíz: todos los excluidos
  tienen patrón (`tests/`, `docs/`, `scratch/`, `vendor/`, `.env*`,
  `.vercel`, `.agents`, `.opencode`, `generate_imports.py`, `data/backup
  registros DB/`, `*.log`) y todos los necesarios en runtime se conservan.
- Docker daemon no accesible en este entorno (`docker.exe version` →
  `failed to connect`), así que el **build real se valida en Docker Desktop
  local o en CI (FASE 2)**.
- `composer test` → **OK (166 tests, 7489 assertions)** (defensivo; sin
  cambios en la app).