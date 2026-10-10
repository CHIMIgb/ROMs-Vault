# i18n — Fase 3: migración de backend, endpoints y admin a `t()`

**Fecha:** 2026-10-09 · **Rama:** `optimizacion-arquitectura`
**Plan de referencia:** [`_archivo/2026-10-09-i18n-fase3-plan.md`](./_archivo/2026-10-09-i18n-fase3-plan.md)
**Roadmap:** [`2026-08-05-plan-mejoras-nivel-senior.md`](./2026-08-05-plan-mejoras-nivel-senior.md) (FASE 5 — i18n, ítems 5.1-5.2)

## Objetivo

Completar la migración a `t()` del **backend** (controladores, router), los
**servicios de seguridad** que devuelven texto a la UI, los **endpoints**
standalone (`ajax_*.php`, `rom_proxy.php`) y las **vistas** que quedaban
hardcodeadas (admin y residuos de `home`). Cierra la Fase 3 iniciada tras el
cargador de diccionarios (6.1) y la cobertura completa de claves es/en (6.2).

## Alcance por bloques (commits)

Ejecución **por bloques**, cada uno con su propio commit y verificación de
`php -l`. En ningún bloque se modificaron los tests.

| Bloque | Contenido | Commit |
|--------|-----------|--------|
| 1 | Repone 6 claves es/en faltantes; corrige claves rotas del cambio a JSON | `8a4ec97` |
| 2 | Servicios (`CsrfService`, `RateLimiter`, `AuthMiddleware`), router `index.php`, controladores y `src/config/i18n.php` de wiring | `76a2fc4` |
| 3 | Endpoints `rom_proxy.php` y todos los `ajax_*.php` | `8c009ea` |
| 4 | Vistas admin: juego `add/edit`, `dashboard`, `categorias/*`, `consolas/*`, `emuladores/*` | `3f027f1` |
| 5 | Residuos de `home/show.php` y `home/play.php` + este doc + roadmap | `4f720b0` (código) |

## Detalle

### Bloque 1 — claves y corrección
- Repuestas 6 claves que faltaban al migrar de PHP a JSON.
- Corregido `2FA`: `two_factor.php` → `two_factor.codigo` / `two_factor.instruccion`,
  `admin/tfa.php` → `admin.tfa.paso1_titulo` / `paso2_titulo`.
- Corregido `errors/404.php`: `clearInterval(timer)` (no pasar el timer a `t()`).

### Bloque 2 — servicios, router y controladores
- `CsrfService`, `RateLimiter`, `AuthMiddleware` (este último añade
  `require_once __DIR__ . '/i18n.php'`): mensajes de seguridad vía `t()`.
- `index.php`: `msg.controlador_no_encontrado`, `msg.accion_no_encontrada`.
- Controladores (`AuthController`, `AdminController`, `CategoriaController`,
  `ConsolaController`, `EmuladorController`, `ErrorsController`,
  `ExportController`, `HomeController`): mensajes flash/traducidos con `t()` y
  `typeMap` de `rom_proxy` → `msg.proxy_*`. Clave nueva `msg.tfa_pendiente`.

### Bloque 3 — endpoints standalone
- `endpoints/rom_proxy.php`: `require_once` de `i18n`; errores JSON vía
  `t('proxy.*')` y `t('msg.error_interno_servidor')`; en el closure de
  `headerfunction` los 4 tipos de error de Drive (no existe, bloqueado, cuota,
  inesperado) y 3 bloques `proxy.url_no_resuelta` / `proxy.destino_invalido`.
- `ajax_catalog.php` y `ajax_autocomplete.php` (públicos): `require_once` de
  `i18n` **— fix de bug latente**: `Pagination::render()` llama a `t()` y estos
  endpoints no cargaban i18n.
- `ajax_admin.php`, `ajax_categoria.php`, `ajax_consola.php`,
  `ajax_emulador.php`: cabeceras, alertas, botones, estados y paginación.

### Bloque 4 — vistas admin
- `admin/add.php` y `admin/edit.php`: labels, placeholders, avisos, sistema de
  capturas y JS (badges, quitar portada, límite) con patrón `{n}` + `.replace()`.
- `admin/dashboard.php`: cabecera, botones, filtros, tabla, paginación, modal de
  exportación (7 tablas) y el `RVAlerts.confirm` del toggle de estado.
  `<h2>Panel de Administracion</h2>` se traduce desde `admin.dashboard.panel_administracion`
  (valor conservado **a propósito**: lo verifica `RouterTest`).
- `categorias/*`, `consolas/*`, `emuladores/*`: stats, cabeceras, alertas,
  filtros, tablas, botones de estado (incl. emulación en consolas), popup de
  eliminar y `RVAlerts.confirm`/`toast`, todos con `textos` + `json_encode(t(...))`.

### Bloque 5 — residuos home
- `home/show.php`: `show.esta_rom_es_de`.
- `home/play.php`: aviso N64 (`play.n64_soporte` + `play.n64_aviso`), aviso de
  streaming (`play.aviso_streaming` con `{tamano}`), tabla de controles por
  teclado (`play.control_*`), `play.que_puedo_hacer` y los toasts de compartir
  (`play.enlace_copiado`, `play.no_se_pudo_copiar[_enlace]`).

## Decisiones

1. **Traducción en servidor**: los endpoints y controladores emiten texto ya
   traducido; el JSON conserva exactamente su forma (mismos índices). Las vistas
   no cambian su contrato.
2. **JS inline**: `json_encode(t('...'))` embebido en `<script>`, con
   reemplazo de marcadores `{n}`/`{i}`/`{total}`/`{valor}` en tiempo de ejecución.
3. **Reutilización**: los Bloques 3-5 no necesitaron claves nuevas (salvo las 7
   del Bloque 3: `msg.metodo_no_permitido`, `admin.juego.sin_resultados`,
   `admin.juego.tabla_jugadas_online`, `admin.juego.filtros_activos`,
   `component.categorias`, `component.consolas`, `component.consolas_con_emulador`).
   El par es/en quedó en **582 claves**.

## Fuera de alcance (justificado)

- Strings internos/debug de `database.php` (`testConnection()`), `i18n.php`,
  `JWTService`, `LoggerService`, `TfaService`, `UrlSigner` y `rom_proxy.php`
  (sentinelas de log/error tipo `host_no_permitido`): no se renderizan a usuario.
- Unidades (`MB`, `GB`) e idiomas de datos (`English`): no son UI traducible.

## Verificación

- `php -l`: limpio en todos los archivos tocados.
- Paridad de diccionarios: `es.json` == `en.json` (582/582).
- Escáner estricto de claves `t('...')` en `src/`, `endpoints/`, `index.php`,
  `router.php`: **0 claves ausentes** en ambos diccionarios.
- Escáner de cadenas hardcodeadas con acentos en vistas migradas: sin UI
  pendiente (solo valores de query-string y código de depuración).
- Suite de tests (`composer test`): ver Registro de progreso del roadmap.

## Referencias

- Fase 1: [`2026-10-09-i18n-migracion-ui-t-fase1.md`](./2026-10-09-i18n-migracion-ui-t-fase1.md)
- Cargador de diccionarios: [`2026-10-09-i18n-cargador-diccionarios.md`](./2026-10-09-i18n-cargador-diccionarios.md)
- Detección de idioma y diccionarios: [`2026-10-09-i18n-deteccion-idioma-diccionarios.md`](./2026-10-09-i18n-deteccion-idioma-diccionarios.md)
