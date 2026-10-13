# Plan de Mejoras a Nivel Industria — ROMs Vault

**Fecha:** 2026-08-05
**Objetivo:** Llevar el proyecto de "portafolio de desarrollador junior" a "proyecto hecho por alguien que trabaja en la industria".
**Stack actual:** PHP 8.2 vanilla (sin framework), PostgreSQL (Neon, SSL) vía PDO, CSS/JS vanilla, Vercel, servidor local `php -S localhost:8000 router.php`.
**Criterio rector:** cada fase debe resolver una pregunta que un entrevistador técnico senior haría y que hoy el proyecto no puede responder bien.

> **Planes activos (fechados):** [`_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md`](./_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md) (seguridad OWASP + paginación DB + tests `Security` + i18n) · [`2026-10-07-migrar-imagenes-a-cloudflare-r2.md`](./2026-10-07-migrar-imagenes-a-cloudflare-r2.md) (imágenes a R2 — **implementado 2026-10-12**, ver [`2026-10-12-migracion-imagenes-r2-implementacion.md`](./2026-10-12-migracion-imagenes-r2-implementacion.md)) · [`2026-10-07-reorganizacion-estructura-archivos.md`](./2026-10-07-reorganizacion-estructura-archivos.md) (reorganización a `src/` + `endpoints/`).

---

## Registro de progreso

> Cada vez que se complete una mejora de este documento, se marca aquí (o en su checkbox) con el commit que la hizo. Mantenido por regla de `AGENTS.md`.

| Fecha | Mejora | Estado | Commit |
|-------|--------|--------|--------|
| 2026-08-05 | **Fase 1.1** — Unit tests de seguridad (UrlSigner, JWTService, RateLimiter, CsrfService) + PHPUnit + `composer test` + testdox + README | Completada | `4610424` |
| 2026-08-05 | **Fase 3.7** — Higiene del repo: normalización CRLF→LF (20 archivos) + `.gitattributes` + `.editorconfig` | Completada | `2665a0f` |
| 2026-08-05 | **Fase 1.2** — Tests de integración (login→dashboard, CRUD consola/emulador, router) contra BD PostgreSQL local de prueba | Completada (pendiente menor: CRUD categoría y download con mock de Drive → ver §1.2) | `762066f` |
| 2026-10-07 | **Fase 3.1** — Reorganización de estructura: `src/` (config/controllers/models/views) + `endpoints/` (rom_proxy y ajax_*), requires con `__DIR__`, URLs públicas intactas; `implementation_plan.md` archivado | Completada | `d39ee37` |
| 2026-10-09 | **FASE 5 (5.1-5.2)** — i18n: cargador JSON es/en (582 claves paritarias), detección de idioma + cookie, y migración total de la UI pública, admin, controladores, router, servicios de seguridad y endpoints (`ajax_*`, `rom_proxy`) a `t()` en 5 bloques | Completada (pendiente 5.3 selector, RTL e `Intl`) | `2242c42` + `a3e120f` + `8a4ec97` + `76a2fc4` + `8c009ea` + `3f027f1` + `4f720b0` |
| 2026-10-09 | **FASE 5 (5.3, 5.4 y 5.2 Intl)** — selector de idioma (ES/EN) en header con banderas SVG; fechas/números con `IntlHelper` (fallback); criterios 5.4 cumplidos (Accept-Language + grep de literales) | Completada (RTL 5.2 pendiente) | `4102f63` + `44a9cb9` |
| 2026-10-10 | **FASE 5 — i18n ampliado** — 4 idiomas nuevos (`fr`, `pt` pt-BR, `de`, `ja`) con paridad 585 claves, banderas en selector, fallback Intl multi-idioma y tests actualizados (aceptación de `xx` para "no soportado") | Completada (RTL 5.2 y `EJS_language` 5.3 pendiente) | `d521eee` |
| 2026-10-10 | **FASE 5 (5.3) — cambio de idioma sin recarga** — `endpoints/ajax_lang.php` + `public/js/rv-lang.js` (sincronización in-place sin romper listeners/estado), transición suave CSS y fallback GET nativo | Completada (queda `EJS_language` y RTL 5.2) | `3561a05` |
| 2026-10-10 | **FASE 5 (5.3) — refinamientos** — `EJS_language` según idioma activo (`IntlHelper::emulatorLanguage()`), KPI "Total jugadas" en los 6 idiomas, fix de sincronización de texto con iconos pixelicons, transición localizada (solo lo que cambia, excluye media/emulador) y cabecera del panel admin consistente en todos los idiomas | Completada (RTL 5.2 pendiente) | `a9746e5` + `7eb9136` + `9f3a344` + `b24d585` + `48d2c5a` |
| 2026-10-10 | **FASE 5 (5.3) — detección automática del idioma** — en la primera visita (sin cookie) `rv-lang.js` detecta el idioma del navegador (`navigator.languages`) y cambia/persiste sin recargar; la detección server-side por `Accept-Language` se mantiene como base | Completada (RTL 5.2 pendiente) | `84ee20c` |
| 2026-10-10 | **Seguridad — credenciales hardcodeadas** — hashes del admin (test y producción) y secretos de test fuera del repo: `tests/.env.test` (gitignored) + `tests/.env.test.example`, placeholders `__ADMIN_PASSWORD_HASH__`/`__TFA_SECRET__` resueltos en runtime y `__CAMBIAR_PASSWORD_HASH__` (falla cerrado) en los dumps SQL | Completada | `f51eab5` |
| 2026-10-10 | **Seguridad/Rendimiento §9.1** — fix warning `setcookie` tras HTML: seed del catálogo calculada en `HomeController::show()`/`play()` antes de renderizar (afectaba a ficha y emulador) | Completada | `2a76ff0` |
| 2026-10-10 | **i18n (residuo 6.3)** — mensajes de error de `HomeController::play()` con `t()` (consola sin core / emulación off / BIOS faltante) respetando el idioma activo; limpieza de duplicado en `es.json` y traducción EN de `play.error_sin_juego` | Completada | `2b687b1` |
| 2026-10-10 | **Seguridad §9.4** — `Cache-Control: no-store` en todas las páginas HTML de admin (`index.php`, antes del controlador) | Completada | `420c2f2` |
| 2026-10-10 | **Seguridad §9.3** — `.dockerignore` para minimizar el contexto de `docker build` (tests/, docs/, vendor/, .env*, .vercel/, etc.) | Completada | `8a0aacd` |
| 2026-10-10 | **Seguridad §12 (Fase A)** — `TRUSTED_PROXIES` allowlist en `RateLimiter::clientIp()`: deny-by-default, se elimina el heurístico RFC1918 | Completada (Fase B: verificación en deploy + activación en Vercel) | `3b40655` |
| 2026-10-10 | **Seguridad §9.5** — `composer audit` verificado y documentado como paso pre-commit/deploy (comando nativo Composer 2.4+, 0 advisories; sin script por conflicto de nombre). Dependabot/CI → FASE 2 | Completada | `4a947da` |
| 2026-10-10 | **Seguridad §7.1 (ADR)** — routing por convención en `index.php`, sin tabla de rutas en BD (evita superficie RCE; slugs 7.2 como evolución futura) | Completada | `0fd030c` |
| 2026-10-11 | **Seguridad §12 (Fase B)** — verificación en producción: `TRUSTED_PROXIES=127.0.0.1` (proxy loopback del edge de Vercel); la auditoría registra la IP pública real del visitante (`153.67.119.76` == `api.ipify.org`); rate limit por IP individual | Completada | `d5bc923` |
| 2026-10-11 | **Seguridad §9.2** — `display_errors` en Docker: duplicado de §2.4, ya implementado (`docker/php-production.ini` + `Dockerfile`); cerrado sin cambios de código | Completada | `f188a79` |
| 2026-10-11 | **FASE 2 (2.1-2.4)** — CI/CD con GitHub Actions: `.github/workflows/ci.yml` (jobs `lint` `php -l`, `static` PHPStan nivel 5 + baseline y `phpcs` PSR-12 sobre ficheros cambiados, `tests` Unit+Integration+Security con servicio PostgreSQL 16 + esquema, `audit` `composer audit`, `build` `docker build`), `.github/dependabot.yml` y badge CI en README; `Server.php` portado a Linux (`/dev/null`) | Completada (configurar secrets `TEST_*` en GitHub; pendientes: PHPStan nivel 6, branch protection) | `2347580` |
| 2026-10-11 | **FASE 5 (5.1) — i18n Tier 1** — 3 idiomas nuevos (`it`, `ru`, `tr`) con paridad 592 claves, banderas SVG, fallback Intl (`it_IT`/`ru_RU`/`tr_TR`) y `EJS_language` (`it-IT`/`ru-RU`/`tr-TR`); tests actualizados (9 idiomas) | Completada (RTL 5.2 y Tier 2 `zh`/`ko`/`ar` pendientes) | `6c2990d` |
| 2026-10-12 | **Almacenamiento — imágenes a Cloudflare R2** (plan `2026-10-07-migrar-imagenes-a-cloudflare-r2.md`): cliente SigV4 mínimo (`R2Client`) + `StorageService` con fallback local, admin por prefijos, `Juego::existeImagen()`, CSP con host del CDN y variables de despliegue; script de migración idempotente con 157 archivos / 123 juegos migrados en local | Completada (migración de Neon pendiente tras el deploy) | `e679a0c` + `cdd1947` + `5495acb` + `1758fd6` |
| 2026-10-12 | **FASE 5 (5.1 y 5.2) — i18n Tier 2 + RTL** — 3 idiomas nuevos (`zh`, `ko`, `ar`) con paridad 592 claves y banderas SVG; `RTL_LANGS` + `I18n::isRtl()`, `dir` en `<html>`, sincronización en `rv-lang.js`; CSS migrado a logical properties en los 7 módulos; fallback Intl (`zh_CN`/`ko_KR`/`ar_AR`) y `EJS_language` (`zh-CN`/`ko-KO`/`ar-AR`); tests actualizados (12 idiomas) | Completada (pendiente: verificación visual manual en navegador) | `e288816` |


---

## Decisión de arquitectura (no negociable)

**El proyecto se queda en PHP vanilla con MVC propio. No se migra a Laravel, Symfony ni ningún framework.**

Esta no es una limitación: es la decisión deliberada que define el proyecto. El objetivo es demostrar conocimiento **profundo** del lenguaje — HTTP, sesiones, cookies, routing, PDO, seguridad (CSP, CSRF, JWT, HMAC, rate limiting), streaming y server-side rendering — sin que un framework lo resuelva por nosotros. Todo lo que el plan introduce (tests, CI, autoload, DTOs, i18n, refactor) es perfectamente compatible con PHP vanilla y **eleva la dificultad**: aquí no hay "magia" de framework que explique el resultado, hay código propio que un entrevistador puede leer línea por línea.

Las fases que siguen **mejoran y ordenan** este MVC, no lo sustituyen. Los puntos donde el plan menciona herramientas de terceros (`phpunit`, `phpstan`, `monolog`) son herramientas de **desarrollo y calidad**, no frameworks de aplicación: el producto final sigue siendo PHP vanilla.

---

## Resumen ejecutivo

El proyecto ya demuestra instinto de senior en seguridad (CSP razonada, CSRF, JWT httpOnly, firma HMAC, rate limiting), features difíciles (proxy de streaming con Range Requests, emulador en navegador) y detalle de producto (SEO, URLs limpias, design tokens).

Lo que **delata** al nivel junior y este plan corrige, en orden de importancia:

| # | Prioridad | Mejora | Pregunta de entrevista que resuelve |
|---|-----------|--------|-------------------------------------|
| 1 | **P0** | Tests automatizados (PHPUnit + integración + E2E) | "¿Cómo sabes que tu código no se rompe?" |
| 2 | **P0** | CI/CD con GitHub Actions (lint, tests, deploy) | "¿Cómo se despliega y valida en equipo?" |
| 3 | **P1** | Arquitectura: autoload PSR-4, types, DTOs | "¿Por qué requiere manual? ¿Dónde están los tipos?" |
| 4 | **P1** | Refactor de archivos monolíticos y HTML duplicado | "¿Cómo mantienes un código que crece?" |
| 5 | **P1** | Documentar decisiones (ADR + defensa de PHP vanilla) | "¿Por qué sin framework? ¿Lo decidiste o no lo sabes?" |
| 6 | **P1** | i18n multiidiomas | Diferenciador de producto que casi nadie hace |
| 7 | **P1** | Observabilidad (logging, health checks, request ID) | "¿Cómo sabes qué pasa en producción?" |
| 8 | **P2** | Performance y caching | "¿Cómo escala? ¿Mides Core Web Vitals?" |
| 9 | **P2** | Seguridad avanzada (SAST, dependabot, auditoría OWASP) | "¿Cómo previenes vulnerabilidades, no solo las corriges?" |
| 10 | **P2** | Frontend moderno (Vite + TypeScript) | "¿Estás al día con el tooling de la industria?" |
| 11 | **P2** | Documentación y demo de producto | "¿Puedo entender y probar tu proyecto en 5 minutos?" |

---

## FASE 1 — Tests automatizados (P0) ← lo primero, no negociable

Hoy el proyecto tiene **cero tests**. Esta es la diferencia #1 entre un proyecto "funciona en mi máquina" y uno profesional.

### 1.1 Unit tests de la lógica de negocio

Instalar `phpunit/phpunit` como dependencia de desarrollo y crear `tests/Unit/`:

- [x] `config/UrlSigner.php` — firma/verificación HMAC, expiración (TTL), rechazo de firmas inválidas
- [x] `config/JWTService.php` — generación, validación, expiración, cookie httpOnly
- [x] `config/CsrfService.php` — token válido/inválido, regeneración
- [x] `config/RateLimiter.php` — ventana, límite, reset, IP
- [x] Validaciones de `controllers/CategoriaController.php`, `ConsolaController.php`, `EmuladorController.php` (reglas de negocio: nombre obligatorio, consola sin emulador, etc.)
- [x] `models/Emulador.php` — `getConsolasSinEmulador()`, `replaceForConsola()` (con PDO de prueba)

> **Hecho en `4610424`** — 35 tests, 61 assertions. Nota: los tests de validaciones y modelos quedaron dentro de los de integración (Fase 1.2) porque requieren BD; los unit tests cubren las 4 clases de seguridad.

**Estrategia de DB:** usar PostgreSQL real de pruebas (schema `data/roms-vaultDB-postgreSQL.sql`) o PDO mock. No tocar nunca la BD de producción.

### 1.2 Tests de integración

- [x] `tests/Integration/` — flujo login → dashboard → logout (con cookies de sesión)
- [x] CRUD completo: crear/editar/eliminar consola (vía HTTP + persistencia real), emulador (modelo contra BD de prueba)
- [ ] URL firmada de descarga → `HomeController::download()` (mock de Google Drive) — *pendiente, requiere mock del proxy/Drive*
- [x] Router: URLs limpias (`/`, `/auth/login`, `/consola/index`, `/home/show/999999`) y retrocompatibilidad (`index.php?controller=...`)
- [x] `router.php` + `index.php` como integración (servidor `php -S` real contra la BD de prueba `roms-vault-test`)

> **Hecho en `762066f`** — 26 tests de integración + 35 unit = **61 tests, 136 assertions** en verde. Se crea la BD PostgreSQL local `roms-vault-test` (schema importado de `data/roms-vaultDB-postgreSQL.sql` + seeds `data/test_seeds.sql` con el admin de test). Helper `tests/Integration/Server.php` levanta `php -S` con `router.php` y gestiona cookies/CSRF (Double Submit Cookie). `config/database.php` gana `DB_SSLMODE` (default `require`; tests `disable`). El servidor hijo corre con `variables_order=EGPCS` para que Dotenv no cargue el `.env` de producción (Neon) en los tests. Quedan como trabajo futuro: CRUD de categoría por HTTP y `HomeController::download()` con mock de Drive.
>
> **Cómo ejecutar los tests de integración** (requieren la BD local `roms-vault-test` y la contraseña vía entorno, nunca versionada):
> ```bash
> cmd.exe /c "set TEST_DB_PASSWORD=<password-postgres-local>&& C:\xampp\php\php.exe vendor\bin\phpunit"
> ```

### 1.3 E2E (opcional pero muy potente para portafolio)

- [ ] Playwright: flujo visitante (catálogo → ficha → jugar online), flujo admin (login → crear juego → aparece en catálogo)

### 1.4 Criterio de éxito

- [ ] `vendor/bin/phpunit` corre verde en local
- [ ] Cobertura **>70%** en lógica de negocio (`--coverage-html`)
- [ ] Tests deterministas (sin dependencia de orden ni estado global)

---

## FASE 2 — CI/CD con GitHub Actions (P0)

Un proyecto de industria se valida en cada push, no "cuando me acuerdo".

### 2.1 Workflow CI básico `.github/workflows/ci.yml`

- [x] Trigger: push a `main` + pull requests
- [x] Jobs: `php: 8.2` → `composer install` → **PHPStan** → **PHPUnit** (jobs separados: `lint`, `static`, `tests`, `audit`, `build`)
- [x] Caché de Composer (`actions/cache`)
- [x] Servicio de PostgreSQL de prueba en el job (para los tests de integración) — `postgres:16`, BD `roms-vault-test`, esquema cargado con `psql -f`

### 2.2 Quality gates

- [x] PHPStan nivel **6** (empezar con nivel 5 si hay demasiados errores, subir después) — hoy en **nivel 5 + baseline** (`phpstan.dist.neon`); la subida a 6 queda como mejora futura
- [x] Code style: `phpcs` (PSR-12) o al menos `php-cs-fixer` en modo diff — `phpcs` PSR-12 aplicado a los ficheros PHP **cambiados** en cada PR/push (`phpcs.xml.dist`, `src/views/` excluido)
- [x] Fracaso del build si hay tests rojos o nivel de PHPStan no alcanzado

### 2.3 Deploy (opcional, cuando el CI esté verde)

- [x] Auto-deploy a Vercel en push a `main` (Vercel ya está linkeado: `.vercel/project.json`) — cubierto por la **integración Git nativa de Vercel**; no se añade job de deploy en Actions (decisión del plan)
- [x] Preview deploys en cada PR (Vercel lo hace gratis)

### 2.4 Criterio de éxito

- [x] Badge de CI en el README ("build passing")
- [ ] Cualquier PR muestra tests verdes antes de mergearse — el workflow publica el estado verde; **falta activar *required status checks* / branch protection en GitHub (acción del usuario)**

---

## FASE 3 — Arquitectura y calidad de código (P1)

### 3.1 Composer autoload PSR-4

Hoy los `require_once` son manuales (`require_once 'config/CsrfService.php'`, etc.).

- [ ] Namespace raíz `App\` → `App\Config`, `App\Controllers`, `App\Models`, `App\Services`
- [ ] `composer.json` → `"autoload": { "psr-4": { "App\\": "src/" } }`
- [x] Mover `config/` y `models/` a `src/` (o crear `src/` con estructura lógica)
- [ ] Eliminar los `require_once` manuales excepto el autoload de Composer
- [ ] `vendor/` fuera del repo (verificar `.gitignore`)

### 3.2 Type declarations y DTOs

Hoy todo viaja como `array` crudo entre modelos, controladores y vistas.

- [ ] Parámetros y retornos tipados en todos los métodos públicos
- [ ] `readonly` DTOs para entidades de negocio: `Juego`, `Consola`, `Categoria`, `Emulador`, `Usuario`
- [ ] `enum` PHP 8 para valores cerrados (rol, tipo de ROM: `streaming`/`precarga`, idioma)
- [ ] Eliminar `mixed` y los `?? null` defensivos en exceso con tipos reales

### 3.3 Refactor de `rom_proxy.php` (monolítico, 600+ líneas)

- [ ] Extraer servicios: `GoogleDriveClient` (resolución de URL, cURL), `Streamer` (Range Requests, streaming), `BiosResolver`
- [ ] El controlador/punto de entrada queda delgado (validar firma → delegar al servicio)
- [ ] Tests unitarios de cada servicio extraído

### 3.4 Refactor de vistas grandes (`views/home/show.php` y `views/home/play.php`, 500+ líneas)

- [ ] Extraer partials reutilizables a `views/partials/` (header de ficha, stats bar, sección de capturas, botones de acción)
- [ ] Mover el JS grande de `play.php` a un archivo en `public/js/` (con datos inyectados via `data-*` o un objeto JSON global)

### 3.5 Eliminar el HTML duplicado en `ajax_*.php`

Hoy `ajax_catalog.php`, `ajax_admin.php`, etc. re-renderizan HTML que también está en las vistas.

- [ ] Cada lista reutilizable vive **una sola vez** en un partial (`views/partials/juego_card.php`, `views/partials/consola_row.php`, ...)
- [ ] El partial se usa tanto en el render server-side como en la respuesta AJAX
- [ ] Un cambio de markup se aplica en un solo lugar

### 3.6 Eliminar estilos inline repetidos en las vistas admin

`style="background:var(--cream-dark);color:var(--slate);..."` aparece pegado en varios formularios.

- [ ] Crear clases utilitarias o componentes CSS en `public/css/modules/` (p. ej. `.btn-secondary`, `.form-divider`, `.form-actions`)
- [ ] Sustituir los bloques inline por las clases

### 3.7 Higiene del repo

- [x] Commitear/normalizar los archivos con ruido CRLF pendientes (`views/layout/footer.php`, `public/css/modules/admin.css`) — **hecho en `2665a0f`**: se normalizaron a LF **20 archivos** que estaban con CRLF
- [x] Definir `.editorconfig` y `.gitattributes` (`* text=auto`) para que no vuelva el problema — **hecho en `2665a0f`**
- [ ] `.env` y `.vercel/` confirmar que están en `.gitignore`

### 3.8 Criterio de éxito

- [ ] `grep -r "require_once" --include="*.php" src/ | wc -l` ≈ 0
- [ ] Ninguna vista con más de ~200 líneas
- [ ] Ningún `style="` inline nuevo en vistas admin

---

## FASE 4 — Documentar decisiones: defensa de PHP vanilla (P1)

Un entrevistador preguntará *"¿por qué no usaste Laravel?"*. La respuesta correcta no es "porque no sabía" — es "porque lo decidí, y esto es lo que aprendí".

### 4.1 ADRs (Architecture Decision Records) en `docs/adr/`

- [ ] `ADR-001-framework-propio.md` — **decisión fundacional**: PHP vanilla + front controller. Razones: (1) demostrar dominio real del lenguaje sin delegar en el framework, (2) aprendizaje de HTTP, sesiones, routing y seguridad desde el protocolo, (3) control total sobre rendimiento y comportamiento, (4) portafolio diferenciador — la mayoría usa frameworks porque no puede explicar qué pasa "bajo el capó". Trade-off aceptado: más código que mantener, **mitigado por este plan** (tests, CI, autoload, refactor). El ADR cierra el debate: no es un punto pendiente de migrar, es la tesis del proyecto
- [ ] `ADR-002-mvc-casero.md` — por qué MVC ligero con modelos PDO en vez de ORM
- [ ] `ADR-003-postgresql-neon.md` — por qué PostgreSQL en la nube (SSL, serverless) para el deploy
- [ ] `ADR-004-urllimpias.md` — por qué router.php + vercel.json + `<base href>` y cómo se garantizó retrocompatibilidad

### 4.2 README potenciado

- [ ] Sección "Decisiones de arquitectura" que enlaza los ADRs
- [ ] Sección "Qué demuestra este proyecto" (seguridad, streaming, emulación, SEO, i18n) — con bullets orientados a reclutadores
- [ ] Captura de pantalla o GIF del emulador en funcionamiento

### 4.3 Criterio de éxito

- [ ] Un senior puede leer el README + ADRs y entender por qué el proyecto se ve así

---

## FASE 5 — i18n multiidiomas (P1) ← diferenciador de producto

Pocos proyectos de portafolio lo tienen. Y en el mundo real (productos globales) es la norma.

### 5.1 Infraestructura de traducción

- [x] `src/config/i18n.php` — cargador de idiomas, función global `t('clave', params)`
- [x] `src/i18n/es.json`, `src/i18n/en.json` (implementado como JSON paritario en vez de `lang/*.php`; 585 claves)
- [x] `src/i18n/fr.json`, `src/i18n/pt.json`, `src/i18n/de.json`, `src/i18n/ja.json` (ampliación FASE 5, 2026-10-10)
- [x] `src/i18n/it.json`, `src/i18n/ru.json`, `src/i18n/tr.json` (Tier 1, 2026-10-11; 9 idiomas · candidatos Tier 2 en `docs/_archivo/2026-10-11-idiomas-candidatos-tier2.md`)
- [x] `src/i18n/zh.json`, `src/i18n/ko.json`, `src/i18n/ar.json` (Tier 2, 2026-10-12; 12 idiomas · doc `docs/2026-10-12-idiomas-tier2-zh-ko-ar.md`)
- [x] Detección de idioma: header `Accept-Language` → cookie (`?lang=` selector) → default `es`
- [x] Persistir preferencia en cookie `rv_lang` (selector `?lang=`)

### 5.2 Aplicación

- [x] UI estática del catálogo, ficha, play, auth, footer/header
- [x] Mensajes de error de validación y alertas (controladores + AJAX)
- [x] Mensajes de éxito del admin (`created`, `updated`, `deleted`)
- [x] Fechas (y números) con `Intl` según locale (sin moneda)
- [x] Soporte RTL en CSS (logical properties: `margin-inline-start` en vez de `margin-left`), `dir` en `<html>` (`I18n::isRtl()`) y sincronización en `rv-lang.js` para `ar` (2026-10-12)

### 5.3 Selector de idioma en la UI

- [x] Selector de idioma visible en header (es/en/fr/pt/de/ja/it/ru/tr/zh/ko/ar) con banderas.
- [x] Los textos de EmulatorJS soportan el idioma activo vía `EJS_language` (`IntlHelper::emulatorLanguage()`, whitelist de códigos del CDN) (`b24d585`)
- [x] El idioma cambia **sin recargar la página**: POST a `endpoints/ajax_lang.php` + sincronización in-place del DOM (`public/js/rv-lang.js`), con fallback GET nativo sin JS (`3561a05`)
- [x] El idioma se **detecta automáticamente del navegador** en la primera visita (`navigator.languages`) y se recuerda; la elección explícita del usuario siempre manda (`84ee20c`)

### 5.4 Criterio de éxito

- [x] Con `Accept-Language: en` la app carga en inglés; el selector cambia sin recargar la sesión
- [x] `grep -rn "texto literal"` en las vistas ≈ solo texto de datos (títulos de juegos, etc.)

---

## FASE 6 — Observabilidad y operación (P1)

Un proyecto de industria sabe qué pasa en producción sin mirar la consola.

- [ ] **Logging estructurado**: `monolog/monolog` (o logger propio) con niveles, JSON, rotación; nada de `error_log()` sueltos
- [ ] **Request ID**: se genera por request, se loguea y se devuelve en header `X-Request-Id` (correlación)
- [ ] **Manejo centralizado de errores**: `set_exception_handler` + `set_error_handler` → log + respuesta JSON/HTML amigable (nunca el stack trace en producción)
- [ ] **Health checks**: `GET /healthz` (liveness) y `GET /ready` (readiness: conexión a BD, reach Google Drive) — vía `HealthController` que responde JSON
- [ ] **Métricas básicas**: contador de descargas (ya existe), tiempo de respuesta, errores por endpoint en un log agregado
- [ ] Criterio: ante un incidente, los logs del momento cuentan la historia completa

---

## FASE 7 — Performance y caching (P2)

- [ ] **Cache** del catálogo y de datos casi estáticos (consolas, emuladores) — Redis (Upstash) o file cache con TTL; invalidación al crear/editar
- [ ] **Queries**: `EXPLAIN` de las queries de catálogo/ficha; índices que faltan (ya hay `google_drive_file_id`; revisar `categoria_id`, `consola_id`, `fecha_lanzamiento`)
- [ ] **Eliminar N+1**: revisar `getRelated()`, `getCatalogStats()` (auditoría previa detectó 5-8 queries por ficha)
- [ ] **Headers de caché** para assets (`.htaccess` ya tiene Expires; verificar en Vercel)
- [ ] **Core Web Vitals**: medir LCP/CLS/INP (Lighthouse), `loading="lazy"` en portadas, dimensiones explícitas en imágenes
- [ ] Criterio: Lighthouse ≥ 90 en mobile; ficha del juego en < 2.5s LCP

---

## FASE 8 — Seguridad avanzada (P2)

Ya hay buena base (CSRF, JWT, HMAC, rate limit). El siguiente nivel es **proceso**:

- [ ] **SAST**: GitHub CodeQL o semgrep en el CI (análisis estático automático en cada push)
- [ ] **Dependabot**: alertas y PRs automáticos de dependencias (`composer`)
- [ ] **Auditoría OWASP Top 10** documentada en `docs/`: checklist con el estado de cada ítem (inyección, XSS, CSRF, SSRF, etc.)
- [ ] **Revisión de uploads**: validar tipo MIME real (no solo extensión), tamaño máximo, nombre saneado
- [ ] **CORS/ALLOWED_ORIGINS** verificado en el deploy de Vercel (whitelist real, no wildcard)
- [ ] Criterio: "¿Qué haría un atacante?" — cada vector documentado y respondido en la auditoría

---

## FASE 9 — Frontend moderno (P2, alto impacto visual)

El sitio se ve bien, pero técnicamente es CSS/JS vanilla sin tooling. Para un portafolio de frontend esto se nota.

- [ ] **Vite** como bundler + **TypeScript** para `public/js/` (los scripts actuales pasan a `.ts` con tipos)
- [ ] **Web Components nativos** (sin framework) para encapsular componentes UI — coherente con la tesis de "vanilla por decisión" del proyecto; si algún día se considera un framework de frontend, se evalúa con un ADR aparte, pero **no es el plan actual
- [ ] **Design tokens** documentados: `public/css/tokens.css` con paleta, tipografía, espaciado (ya existen variables CSS — convertirlas en el sistema canónico)
- [ ] **Accesibilidad**: auditoría con axe-core; foco visible, contraste AA, `aria-*` correctos en modales/dropdowns
- [ ] Criterio: `npm run build` produce assets minificados con hash; Lighthouse a11y ≥ 95

---

## FASE 10 — Documentación y demo de producto (P2)

- [ ] README **bilingüe** (es/en)
- [ ] **Diagrama de arquitectura** en Mermaid (dentro del README): navegador → Vercel → index.php → controladores → PostgreSQL/Drive/CDN
- [ ] **Runbook** de operaciones: cómo se despliega, cómo se restauran datos, cómo se rota el `JWT_SECRET`
- [ ] **Demo en video** (30-60s) del flujo completo — enlace en el README
- [ ] Criterio: alguien ajeno entiende el proyecto y lo prueba en menos de 5 minutos

---

## Mapa JR → Senior (cómo te van a evaluar)

| Pregunta del entrevistador | Respuesta de JR (hoy) | Respuesta de Senior (con este plan) |
|---|---|---|
| "¿Cómo sabes que no se rompe?" | "Lo probé a mano" | "PHPUnit + integración + Playwright, en CI" |
| "¿Cómo despliegas?" | "Push a Vercel" | "CI verde → auto-deploy; previews por PR" |
| "¿Por qué sin framework?" | "..." | "ADR-001: decisión fundacional y deliberada — domino HTTP, sesiones, routing y seguridad desde el protocolo, y lo demuestro con código propio" |
| "¿Cómo mantienes 5k líneas?" | "Con cuidado" | "Autoload PSR-4, DTOs, partials únicos, sin HTML duplicado" |
| "¿Soporta más idiomas?" | "No" | "i18n con 12 idiomas (es/en/fr/pt/de/ja/it/ru/tr/zh/ko/ar), selector en UI y soporte RTL (árabe)" |
| "¿Qué pasa en producción si falla?" | "Lo veo en el navegador" | "Logs estructurados, request ID, health checks" |
| "¿Y las dependencias vulnerables?" | "..." | "Dependabot + CodeQL en cada push" |

---

## Orden de ejecución recomendado (2 semanas)

1. **Día 1-2:** Instalar PHPUnit, unit tests de `UrlSigner`/`JWTService`/`RateLimiter`/`CsrfService` (mayor valor, menor esfuerzo).
2. **Día 3-4:** Tests de integración del router y un CRUD; arrancar GitHub Actions (CI con tests).
3. **Día 5-6:** Composer autoload PSR-4 + types/DTOs en 2-3 clases piloto.
4. **Día 7-8:** Refactor de `rom_proxy.php` en servicios + `show.php` en partials.
5. **Día 9-10:** ADRs + README potenciado + higiene del repo (CRLF, gitattributes).
6. **Día 11-14:** i18n (infraestructura + catálogo + ficha), que es el diferenciador visible.
7. Después (P2): observabilidad, caching, frontend moderno, demo.

> **Regla de oro:** cada fase termina con el repo en verde (tests + CI). Si no hay test que cubra el cambio, el cambio no está terminado.
