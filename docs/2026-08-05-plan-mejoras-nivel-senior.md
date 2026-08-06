# Plan de Mejoras a Nivel Industria — ROMs Vault

**Fecha:** 2026-08-05
**Objetivo:** Llevar el proyecto de "portafolio de desarrollador junior" a "proyecto hecho por alguien que trabaja en la industria".
**Stack actual:** PHP 8.2 vanilla (sin framework), PostgreSQL (Neon, SSL) vía PDO, CSS/JS vanilla, Vercel, servidor local `php -S localhost:8000 router.php`.
**Criterio rector:** cada fase debe resolver una pregunta que un entrevistador técnico senior haría y que hoy el proyecto no puede responder bien.

---

## Registro de progreso

> Cada vez que se complete una mejora de este documento, se marca aquí (o en su checkbox) con el commit que la hizo. Mantenido por regla de `.agents/AGENTS.md`.

| Fecha | Mejora | Estado | Commit |
|-------|--------|--------|--------|
| 2026-08-05 | **Fase 1.1** — Unit tests de seguridad (UrlSigner, JWTService, RateLimiter, CsrfService) + PHPUnit + `composer test` + testdox + README | ✅ Completada | `4610424` |
| 2026-08-05 | **Fase 3.7** — Higiene del repo: normalización CRLF→LF (20 archivos) + `.gitattributes` + `.editorconfig` | ✅ Completada | `2665a0f` |
| 2026-08-05 | **Fase 1.2** — Tests de integración (login→dashboard, CRUD consola/emulador, router) contra BD PostgreSQL local de prueba | ✅ Completada (pendiente menor: CRUD categoría y download con mock de Drive → ver §1.2) | `PENDIENTE-COMMIT` |

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

> **Hecho en `PENDIENTE-COMMIT`** — 26 tests de integración + 35 unit = **61 tests, 136 assertions** en verde. Se crea la BD PostgreSQL local `roms-vault-test` (schema importado de `data/roms-vaultDB-postgreSQL.sql` + seeds `data/test_seeds.sql` con admin `admin123`). Helper `tests/Integration/Server.php` levanta `php -S` con `router.php` y gestiona cookies/CSRF (Double Submit Cookie). `config/database.php` gana `DB_SSLMODE` (default `require`; tests `disable`). El servidor hijo corre con `variables_order=EGPCS` para que Dotenv no cargue el `.env` de producción (Neon) en los tests. Quedan como trabajo futuro: CRUD de categoría por HTTP y `HomeController::download()` con mock de Drive.
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

- [ ] Trigger: push a `main` + pull requests
- [ ] Jobs: `php: 8.2` → `composer install` → **PHPStan** → **PHPUnit**
- [ ] Caché de Composer (`actions/cache`)
- [ ] Servicio de PostgreSQL de prueba en el job (para los tests de integración)

### 2.2 Quality gates

- [ ] PHPStan nivel **6** (empezar con nivel 5 si hay demasiados errores, subir después)
- [ ] Code style: `phpcs` (PSR-12) o al menos `php-cs-fixer` en modo diff
- [ ] Fracaso del build si hay tests rojos o nivel de PHPStan no alcanzado

### 2.3 Deploy (opcional, cuando el CI esté verde)

- [ ] Auto-deploy a Vercel en push a `main` (Vercel ya está linkeado: `.vercel/project.json`)
- [ ] Preview deploys en cada PR (Vercel lo hace gratis)

### 2.4 Criterio de éxito

- [ ] Badge de CI en el README ("build passing")
- [ ] Cualquier PR muestra tests verdes antes de mergearse

---

## FASE 3 — Arquitectura y calidad de código (P1)

### 3.1 Composer autoload PSR-4

Hoy los `require_once` son manuales (`require_once 'config/CsrfService.php'`, etc.).

- [ ] Namespace raíz `App\` → `App\Config`, `App\Controllers`, `App\Models`, `App\Services`
- [ ] `composer.json` → `"autoload": { "psr-4": { "App\\": "src/" } }`
- [ ] Mover `config/` y `models/` a `src/` (o crear `src/` con estructura lógica)
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

- [ ] `config/i18n.php` — cargador de idiomas, función global `t('clave', params)`
- [ ] `lang/es.php`, `lang/en.php` (y opcional `lang/pt.php`) — arrays `clave → texto`
- [ ] Detección de idioma: header `Accept-Language` → cookie (`?lang=` selector) → default `es`
- [ ] Persistir preferencia en cookie (y en el perfil del usuario si existe)

### 5.2 Aplicación

- [ ] UI estática del catálogo, ficha, play, auth, footer/header
- [ ] Mensajes de error de validación y alertas (controladores + AJAX)
- [ ] Mensajes de éxito del admin (`created`, `updated`, `deleted`)
- [ ] Fechas y moneda con `Intl` según locale
- [ ] Soporte RTL en CSS (usar logical properties: `margin-inline-start` en vez de `margin-left`) para futuros idiomas

### 5.3 Selector de idioma en la UI

- [ ] Dropdown ES/EN en el header (icono de idioma)
- [ ] Los textos de EmulatorJS ya soportan locales (`EJS_language`) — parametrizarlo según idioma

### 5.4 Criterio de éxito

- [ ] Con `Accept-Language: en` la app carga en inglés; el selector cambia sin recargar la sesión
- [ ] `grep -rn "texto literal"` en las vistas ≈ solo texto de datos (títulos de juegos, etc.)

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
| "¿Soporta más idiomas?" | "No" | "i18n con es/en/pt, RTL listo, selector en UI" |
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
