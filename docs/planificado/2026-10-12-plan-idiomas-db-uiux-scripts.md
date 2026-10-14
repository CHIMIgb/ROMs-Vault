# Plan de trabajo — Idiomas faltantes, traducción de datos de BD, UI/UX, separación de scripts e identidad por dispositivo

**Fecha de ejecución prevista:** 2026-10-12 · **Estado:** planificado (no iniciado) · **Documento:** borrador de análisis.
**Rama sugerida:** nueva rama desde `main` tras fusionar el PR #16 (o continuidad de la actual).

> Documento de planificación archivado en `docs/_archivo/`. No implica trabajo en
> curso ni cambios aplicados. Cada bloque requiere su propio plan de
> implementación aprobado (flujo §10) antes de tocar código.

## Alcance

Cinco frentes de trabajo, en este orden sugerido:

1. **Idiomas faltantes** (Tier 2 ya documentados: `zh`, `ko`, `ar`).
2. **Traducción de la información de la BD** que se muestra en la aplicación.
3. **Correcciones y ajustes de UI/UX.**
4. **Separar en archivos independientes los `<script>` embebidos en `.php`.**
5. **Identidad por dispositivo / registro de sesiones en servidor** (evolución de seguridad discutida en `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md`, sección 13).

---

## Bloque 1 — Idiomas faltantes (Tier 2)

Origen: `docs/_archivo/2026-10-11-idiomas-candidatos-tier2.md`. Checklist de 5
puntos por idioma (paridad 592 claves con `es.json`):

1. `src/i18n/{code}.json` completo y paritario.
2. `I18n::LANGUAGES` (whitelist, única fuente de verdad).
3. `IntlHelper::LOCALES`.
4. `IntlHelper::EMULATOR_LOCALES`.
5. `$langFlags` SVG en `src/views/layout/header.php`.

| Idioma | Código app | Locale Intl | EmulatorJS | Nota |
|---|---|---|---|---|
| Chino simplificado | `zh` | `zh_CN` | `zh-CN` | CJK; `Courier Prime` no cubre CJK → fallback monoespaciado (igual que `ja`). |
| Coreano | `ko` | `ko_KR` | `ko-KO` | CJK; misma nota de fuente. |
| Árabe | `ar` | `ar_AR` | `ar-AR` | **RTL**; depende del pendiente **FASE 5.2** (`dir="rtl"`, CSS logical properties, revisión de layout). |

**Decisión pendiente:** ¿se implementa `ar` junto con RTL (5.2) o se pospone?
Propuesta: cerrar `zh` + `ko` primero (sin coste RTL) y abordar `ar` tras 5.2.

**Tareas**
- [ ] Traducir 592 claves por idioma (revisar longitud de cadenas: alemán/ruso ya son largas).
- [ ] Verificar paridad con el test `I18nTest::testDiccionariosJsonValidosYParitarios`.
- [ ] Añadir banderas SVG y comprobar que el selector no desborda con 12 idiomas.
- [ ] Si entra `ar`: `dir="rtl"` en `<html>`, auditoría de layout y CSS (logical properties).

---

## Bloque 2 — Traducción de la información de la BD mostrada en la app

**Problema:** hoy el contenido de la BD es monoidioma (español) y se sirve tal
cual. Los campos de texto visibles son:

| Tabla | Campos de texto visibles |
|---|---|
| `juegos` | `titulo`, `descripcion`, `idiomas`, `region` |
| `consolas` | `nombre`, `descripcion`, `fabricante` |
| `categorias` | `nombre`, `descripcion` |
| `emuladores` | `nombre` (nombres propios, no traducibles) |

**Distinción clave:** los datos se dividen en (a) **nombres propios / editorial**
que suelen ser internacionales (`juegos.titulo`, `emuladores.nombre`,
`fabricante`) y (b) **contenido editorial** que sí conviene traducir
(`descripcion` de juegos/consolas/categorias, `categorias.nombre`).

### Opciones de diseño (a decidir)

- **A. Tabla de traducciones por entidad** — `juegos_traducciones`, `consolas_traducciones`,
  `categorias_traducciones`, con claves `(entidad_id, idioma)` y columnas por
  campo. Normalizado; escalable; requiere JOIN y capa de resolución en modelos.
- **B. Columna `JSONB` de traducciones** por fila (p. ej. `juegos.i18n`). Menos
  tablas; flexible; consultas y orden por idioma más costosos; validación manual.
- **C. Columnas por idioma** (`titulo_es`, `titulo_en`, …). Simple de leer pero
  rígido y explota con cada idioma nuevo.
- **D. No traducir datos de BD** (solo UI) y dejar títulos/descripciones en su
  idioma original. Coste cero, pero la experiencia queda mixta.

**Recomendación preliminar:** enfoque **híbrido** sobre la opción **A**:
traducir solo campos editoriales (`descripcion`, `categorias.nombre`), con
**fallback a `es`** y resolución en el Modelo (no en la vista). Mantener
`titulo`/`emuladores.nombre` como nombres propios.

### Impactos a analizar

- **Modelos:** función de resolución `traducido($campo, $idioma)` con fallback.
- **Búsqueda/orden/autocompletado:** los `LIKE`/`ORDER BY` actuales usan `nombre`/`titulo`;
  habría que decidir si buscan en el idioma activo o en `es`.
- **Slugs y URLs:** los slugs actuales derivan de `titulo`/`nombre` en `es`; si se
  traduce el título, definir si el slug cambia por idioma (rompería enlaces).
- **Admin:** editor de traducciones por idioma (o columnas por idioma en los
  formularios de `add.php`/`edit.php`).
- **Migración + seeds:** estructura + datos de ejemplo; idempotencia en
  `data/migrations/`.
- **Caché:** evitar N+1 al resolver traducciones en listados (paginación 20 ítems).

**Decisiones pendientes:** ¿qué campos se traducen? ¿búsqueda/orden por idioma
activo o por `es`? ¿slug fijo? ¿alcance del admin?

---

## Bloque 3 — Correcciones y ajustes de UI/UX

Auditoría abierta (sin decisiones tomadas). Áreas a revisar:

- **Accesibilidad:** foco visible, navegación por teclado en modales/acordeones,
  `aria-*` en componentes (`Alert`, `Pagination`, `game_actions`, `related_games`),
  contraste de tokens.
- **Responsive:** rejilla de 4x5 (20 ítems) en móvil, `target` táctil, overflow
  del menú de idiomas con 12 idiomas.
- **i18n visual:** cadenas largas (de/ru) que desbordan botones/tarjetas; fechas y
  números con `IntlHelper`.
- **Estados:** vacíos, carga, error (404/500) coherentes y traducidos.
- **Design system:** usos de CSS suelto fuera de `public/css/modules/*`; tokens
  duplicados en `variables.css`.
- **Rendimiento percibido:** lazy-load de imágenes, skeletons, reconciliación de
  la paginación.

**Entregable:** lista priorizada de hallazgos (con captura/fichero/línea) y
propuesta de ajustes. Cada ajuste relevante pasa por su plan §10.

---

## Bloque 4 — Separar `<script>` embebidos en archivos `.php`

**Situación actual:** las vistas contienen bloques `<script>` inline. Inventario:

| Fichero | Bloques `<script>` |
|---|---|
| `src/views/layout/footer.php` | 3 (incluye arranque de EmulatorJS) |
| `src/views/home/play.php` | 2 |
| `src/views/home/show.php` | 2 |
| `src/views/layout/header.php` | 1 (JSON-LD, es dato, no lógica) |
| `src/views/home/index.php` | 1 |
| `src/views/errors/404.php` | 1 |
| `src/views/auth/login.php` | 1 |
| `src/views/admin/add.php` | 1 |
| `src/views/admin/edit.php` | 1 |
| `src/views/admin/dashboard.php` | 1 |
| `src/views/admin/emuladores/index.php` | 1 |
| `src/views/admin/consolas/index.php` | 1 |
| `src/views/admin/consolas/edit.php` | 1 |
| `src/views/admin/categorias/index.php` | 1 |
| `src/views/admin/categorias/edit.php` | 1 |

**Enfoque propuesto**
- Extraer la lógica a ficheros en `public/js/` (vanilla, sin build step),
  cargados con `<script src="..." defer>`.
- Pasar datos del servidor al JS mediante `data-*` en un contenedor o un bloque
  `<script type="application/json">` **dato** (no ejecutable), evitando
  `echo` de PHP dentro de la lógica.
- Respetar el CSP actual (`script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: ...`):
  al externalizar, se reduce la superficie de `'unsafe-inline'` (que hoy es
  necesaria por los inline). **`'unsafe-eval'`/`blob:` se mantienen** (EmulatorJS).
- Casos especiales: configuración de EmulatorJS en `footer.php`/`play.php` (debe
  ejecutarse en el orden correcto) y el JSON-LD de `header.php` (es dato; puede
  quedarse o moverse a un `<script type="application/ld+json">` generado).

**Decisión pendiente:** ¿se externaliza también el arranque de EmulatorJS (riesgo
de orden de carga) o solo los scripts de UI/admin? Propuesta: empezar por
UI/admin y dejar EmulatorJS para el final, con prueba en el navegador.

---

## Bloque 5 — Identidad por dispositivo y registro de sesiones en servidor

**Origen:** evolución futura ya anotada en
`docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md`, **sección 13**
("Nota sobre identidad por dispositivo"). Hoy **no está implementado**.

**Problema:** la **IP pública no identifica dispositivos** (NAT/CGNAT, IP dinámica):
varios dispositivos tras un mismo router comparten IP. La sesión se distingue hoy
solo por la cookie JWT (`__Host-rv_token`), una por navegador, sin registro en
servidor. No se sabe **qué dispositivos** han iniciado sesión ni se pueden
**revocar** individualmente.

**Objetivo:** poder identificar cada dispositivo que inicia sesión (más allá de su
IP) para listar y revocar sesiones por dispositivo, y auditar accesos.

### Enfoque propuesto

- **Registro de sesiones en servidor:** al hacer login, guardar en BD una fila por
  sesión con el `jti` (ID del JWT) y metadatos: `usuario_id`, IP, User-Agent,
  etiqueta de dispositivo derivada del UA (navegador/SO, parseo ligero), `created_at`
  y `last_seen_at`. Esto permite **ver y revocar sesiones por dispositivo**
  (borrar la fila e invalidar el `jti`; el JWT deja de aceptarse).
- **Fingerprinting invasivo: descartado.** El User-Agent (u otras señales) como
  huella única es invasivo en privacidad y poco fiable; solo se usa como **etiqueta
  descriptiva** del dispositivo, nunca como identidad fuerte.
- **Ciclo de vida del JWT:** validar el `jti` contra la tabla de sesiones en cada
  request (o en `AuthMiddleware`) para permitir revocación inmediata; considerar
  `last_seen_at` con escritura throttled para no golpear la BD en cada petición.

### Impactos a analizar

- **`JWTService`** (`src/config/`): introducir `jti`, emitir/registrar la sesión y
  validar revocación.
- **`AuthMiddleware`**: comprobar que la sesión sigue viva; logout que borre la fila.
- **BD:** nueva tabla (p. ej. `sesiones_usuario`) + migración idempotente en
  `data/migrations/` (dev/test/Neon). Relación con `usuarios` (y `roles`).
- **Admin:** panel "Sesiones / Dispositivos" para listar y revocar; botón de
  "cerrar el resto de sesiones". Aplicar `Cache-Control: no-store` (coherente con
  el pendiente de seguridad en admin).
- **PII / logging:** IP y User-Agent son datos personales; definir retención,
  finalidad y no exponerlos de más. Nota: ya existe la tabla muerta `descargas`
  con `ip_address`/`user_agent`/`cookie_id` (ver
  `docs/2026-08-02-auditoria-backend.md`) — **no reutilizarla**; crear una tabla
  con finalidad clara.
- **Límite de sesiones por usuario** (opcional) y limpieza de sesiones expiradas.
- **Convivencia** con rate limiting (file-based) y con `TRUSTED_PROXIES`
  (`clientIp()`) para registrar la IP real y no la del proxy.

**Decisiones pendientes:** ¿tabla `sesiones_usuario` nueva? ¿validación de `jti`
en cada request o solo con lista de revocación? ¿retención de metadatos? ¿límite de
dispositivos por usuario? ¿se muestra IP/UA al propio usuario en su perfil?

---

## Orden de ejecución sugerido

1. Cerrar PR #16 / partir de `main` limpio.
2. UI/UX: auditoría (solo hallazgos) → priorizar → aplicar por lotes.
3. Separar scripts inline (UI/admin primero; EmulatorJS al final).
4. Idiomas `zh` + `ko` (sin RTL).
5. Traducción de datos de BD (mayor impacto: requiere decisiones de diseño).
6. RTL (5.2) → `ar`.
7. Identidad por dispositivo / registro de sesiones en servidor (seguridad; requiere decisión de diseño de BD y `JWTService`).

## Preguntas abiertas (para el usuario)

1. ¿`ar` se hace ahora con RTL o se pospone a la FASE 5.2?
2. Traducción de BD: ¿qué campos exactamente y con qué enfoque (A/B/C/híbrido)?
3. ¿La búsqueda/orden debe usar el idioma activo o siempre `es`?
4. ¿El slug puede cambiar por idioma o se mantiene estable?
5. Scripts: ¿se externaliza también el arranque de EmulatorJS?
6. Identidad por dispositivo: ¿tabla nueva `sesiones_usuario` con `jti` y revocación por sesión? ¿validación del `jti` en cada request? ¿límite de dispositivos y retención de IP/User-Agent?
7. ¿Prioridad entre los 5 bloques si hay que recortar alcance?

## Riesgos y compatibilidad

- **No romper** URLs públicas (`/rom_proxy.php`, `/ajax_*.php`), `vercel.json`
  (`"rewrites": []`) ni el CSP (EmulatorJS). Paginación sigue en 20 (4x5).
- Cambios de esquema: migraciones **idempotentes** en `data/migrations/` y
  verificación en `roms-vault` (dev) y `roms-vault-test` (test) y Neon.
- Guard `tests/Unit/NoEmojiTest.php` sigue activo: **sin emojis** en docs/código.
