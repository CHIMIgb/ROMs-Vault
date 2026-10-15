# Guardado asíncrono de traducciones: cola en BD + worker CLI PHP

- **Fecha:** 2026-10-14
- **Rama:** `feature/i18n-traduccion-contenido-bd`
- **HEAD previo:** `9975337`
- **Plan origen:** `docs/planificado/2026-10-14-guardado-asincrono-cola-bd-worker.md`
- **Estado:** implementado (2026-10-14). Modo por defecto **`async`** (decisión
  del 2026-10-14): el admin encola y responde al instante; `TRANSLATE_MODE=sync`
  recupera el guardado síncrono. Migración **ya aplicada en Neon**. Pendiente en
  producción: **ejecutar el worker** (cron/VM).

---

## 1. Resumen

Hasta ahora, al guardar un juego o una categoría en el admin la traducción a los
**11 idiomas destino** ocurría **dentro de la misma petición**: el registro ya
estaba persistido, pero el `header('Location: ...')` se emitía **después** de
traducir. El admin esperaba a que terminasen las 11 llamadas al proveedor.

Se implementa una **cola en PostgreSQL + worker CLI en PHP nativo** (sin broker,
sin Redis, sin Python). Al guardar se **encola** y el admin responde al
instante; el worker consume la cola con **reintentos y backoff exponencial**.

## 2. Arquitectura

```
[Admin guarda] --INSERT--> traduccion_cola --reclamo SKIP LOCKED--> [worker CLI]
                                                                        |
                                                        TraduccionService::traducirEntidad()
                                                                        |
                                                        upsert en *_traducciones
```

El worker **reutiliza** `TraduccionService::traducirEntidad()`, así que conserva
las garantías ya existentes: incremental por `origen_hash`, nunca pisa
correcciones manuales (`origen='manual'`) y es *fail-open*.

## 3. Archivos

| Acción | Archivo | Cambio |
|---|---|---|
| CREAR | `data/migrations/2026-10-14-traduccion-cola.sql` | Tabla `traduccion_cola` + índices (idempotente) + trigger `actualizado_en` |
| CREAR | `src/models/TraduccionCola.php` | `encolar()`, `reclamar()`, `marcarOk()`, `marcarError()`, `marcarFallido()`, `backoffSegundos()` (PDO inyectable) |
| MODIFICAR | `src/config/TraduccionService.php` | `modo()` (`TRANSLATE_MODE`) y `encolar()` fail-open |
| MODIFICAR | `src/controllers/AdminController.php` | `autoTraducirJuego()` encola si el modo es `async` |
| MODIFICAR | `src/controllers/CategoriaController.php` | `autoTraducirCategoria()` encola si el modo es `async` |
| CREAR | `scripts/worker-traducciones.php` | Worker CLI: bucle, reclamo, traducción, backoff, dead-letter |
| MODIFICAR | `.env.example` | Documenta `TRANSLATE_MODE` |
| MODIFICAR | `docker-entrypoint.sh` | Propaga `TRANSLATE_MODE` y `TRANSLATE_TARGETS` al `.env` del contenedor |
| MODIFICAR | `tests/Integration/Server.php` | El servidor de test usa `TRANSLATE_MODE=async` |
| CREAR | `tests/Unit/TraduccionColaTest.php` | 7 tests (validación, backoff, modo, no-BD) |
| CREAR | `tests/Integration/WorkerTraduccionesTest.php` | 12 tests (encolado por HTTP, dedupe, reclamo, estados, worker CLI) |

## 4. Decisiones de diseño

1. **`TRANSLATE_MODE` con default `async`.** Decisión del 2026-10-14: también en
   local se prueba siempre la vía asíncrona, sin tener que configurar nada. Solo
   el valor exacto `sync` recupera el guardado síncrono; cualquier otro valor
   (incluido ausente) va a `async`.

   > **Consecuencia operativa importante:** con `async` el contenido guardado
   > **no se traduce hasta que un worker drene la cola**. En un entorno sin
   > proceso persistente (Vercel) hay que poner `TRANSLATE_MODE=sync` en sus
   > Environment Variables, o ejecutar el worker por cron en la VM. Si no, el
   > contenido nuevo/actualizado se queda en `es` (el sitio público hace
   > fallback y no se rompe, pero se ve sin traducir).
2. **Reclamo atómico con `FOR UPDATE SKIP LOCKED`.** Varios workers pueden
   correr en paralelo sin pisarse (cola sin broker).
3. **Dedupe por entidad.** Índice único parcial `WHERE estado IN
   ('pendiente','en_proceso')`: si se guarda dos veces la misma entidad, el job
   activo se **reescribe** (campos nuevos, `intentos=0`) en vez de duplicarse.
4. **Backoff exponencial con tope**: `2^intentos` s, máximo 300 s. Se calcula en
   una función pura (`backoffSegundos()`) para poder testearla sin BD.
   > Nota: la primera versión hacía `(int) pow(2, 100)`, que desborda el cast a
   > `int` y devolvía 0 (luego se recortaba a 1 s). El test de Unit lo detectó;
   > ahora hay un corte explícito a partir de 9 intentos.
5. **Fallos permanentes vs. reintentables.** `entidad_invalida`, `sin_texto` y
   `sin_idiomas` van directos a *dead-letter* (`marcarFallido`): reintentarlos no
   cambiaría el resultado. Los demás (proveedor no configurado, timeout,
   `errores>0`) vuelven a `pendiente` con backoff hasta `max_intentos`.
6. **Fail-open en el encolado.** Si la cola falla (p. ej. migración no aplicada)
   el guardado **no se rompe**: se registra `traduccion_cola_error` en el log y
   el contenido se queda en `es` (el sitio público ya hace fallback).
7. **El sitio público no cambia.** Mientras un job está pendiente, el contenido
   se sirve en `es` (fallback existente). No hay lecturas nuevas en caliente.

## 5. Puesta en marcha (estado actual)

1. **Migración en producción (Neon): aplicada** (2026-10-14, ejecutada a mano por
   el usuario contra el **endpoint directo**). Verificado: tabla, 12 columnas con
   sus defaults, índices `traduccion_cola_pkey` / `idx_traduccion_cola_pendientes`
   / `uq_traduccion_cola_activa`, trigger `trg_traduccion_cola_updated_at` con su
   función `fn_traduccion_cola_updated_at()`, `search_path` limpio y cola vacía.
   Se ejecuta siempre contra el **endpoint directo** (sin `-pooler`), porque un
   `SET`/`set_config` de sesión se filtra por el pooler (ver el incidente
   documentado en `docs/2026-10-14-fix-toast-enlace-copiado-y-formulario-idioma.md`).
2. **Worker: pendiente en producción.** Hay que ejecutarlo donde haya un proceso
   persistente o un cron:
   - proceso: `php scripts/worker-traducciones.php`
   - cron (cada minuto): `php scripts/worker-traducciones.php --una-vez`
   Vercel **no** sirve para esto (serverless, sin proceso de larga vida); el
   worker debe correr en la VM/servidor con acceso a la BD.
3. **Modo:** ya no hace falta activar nada (`async` es el default). En Vercel,
   mientras no haya worker, **poner `TRANSLATE_MODE=sync`** para seguir
   traduciendo dentro de la petición.

## 6. Verificación (evidencia)

### 6.1 Suite completa

```
OK (254 tests, 15014 assertions)
```

(+19 tests respecto a los 235 previos: 7 de Unit + 12 de Integration.)

### 6.2 Tests nuevos

```
Traduccion Cola (Tests\Unit\TraduccionCola)
 OK (7 tests, 16 assertions)

Worker Traducciones (Tests\Integration\WorkerTraducciones)
 OK (12 tests, 62 assertions)
 - Guardar juego encola trabajo pendiente
 - Guardar categoria encola trabajo
 - Encolar dos veces la misma entidad no duplica
 - Encolar tras completar crea un job nuevo
 - Reclamar devuelve el job y lo marca en proceso
 - Reclamar filtra por entidad
 - Marcar ok cierra el trabajo
 - Marcar error con intentos restantes vuelve a pendiente con backoff
 - Marcar error sin intentos va a dead letter
 - Marcar fallido es definitivo
 - Worker cli sin proveedor no procesa ni pierde trabajos
 - Worker no hace nada por http
```

### 6.3 Smoke real de extremo a extremo (BD local, DeepL real)

Categoría desechable encolada como lo haría el admin y procesada por el worker:

```
== Worker de traducciones (cola en BD) ==
Proveedor : deepl
Modo web  : sync
Destino   : en, fr, pt, de, ja, it, ru, tr, zh, ko, ar
[job 1] categoria#42 (intento 1/5)
         ok -> en, fr, pt, de, ja, it, ru, tr, zh, ko, ar
Resumen: 1 trabajo(s) procesado(s) | 1 ok | 0 con error | 2 ciclo(s)

job          : ok (intentos=1)
idiomas trad : 11
  ja: ZZ ワーカー・スモーク [auto]
  ko: ZZ 워커 스모크 [auto]
  tr: ZZ İşçi Dumanı [auto]
  ... (11 idiomas, todos origen=auto)
```

La categoría de prueba y sus traducciones se eliminaron después del smoke
(la BD local queda como estaba).

### 6.4 Migración aplicada

Local y BD de test:

```
--- roms-vault ---       CREATE TABLE / CREATE INDEX / CREATE INDEX / CREATE FUNCTION / DO   EXIT=0
--- roms-vault-test ---  CREATE TABLE / CREATE INDEX / CREATE INDEX / CREATE FUNCTION / DO   EXIT=0
```

Producción (Neon, endpoint directo, ejecutada por el usuario) — verificado
después: tabla + 12 columnas + 3 índices + trigger + función, `search_path`
`"$user", public`, 0 filas en la cola.

### 6.5 E2E del modo por defecto (admin real contra la BD de producción)

Con el `.env` del repo (que apunta a Neon) y **sin** `TRANSLATE_MODE`, se guardó
una categoría desde el admin con `curl` (login real + CSRF):

```
GET  /auth/login     -> 200 (token ok)
POST /auth/login     -> 302  Location: /admin/dashboard
GET  /categoria/add  -> 200 (token ok)
POST /categoria/add  -> 302  Location: /categoria/index?created=1
```

Y el estado en la BD inmediatamente después del guardado:

```
 categorias: id 42, "ZZ Async Default 053848"
 traducciones inline: 0
 traduccion_cola: job 1 | categoria | 42 | pendiente | intentos 0
   campos: {"nombre": "ZZ Async Default 053848", "descripcion": "..."}
```

Es decir: **el guardado encoló (default `async`) y respondió sin traducir**, que
es justo el comportamiento buscado. También confirma el riesgo documentado: en
un entorno sin worker el job se queda en `pendiente` indefinidamente.

La categoría de prueba, el job y sus (inexistentes) traducciones se eliminaron
acto seguido: `DELETE 1` (job) + `DELETE 1` (categoría); cola final 0 filas.

> Aviso operativo: el `.env` local apunta a Neon, así que **probar el admin en
> local escribe en producción** y, con el default `async`, ese contenido queda en
> `es` hasta que se ejecute el worker. En local, lanzar
> `php scripts/worker-traducciones.php` mientras se prueba el admin.

## 7. Impacto y riesgos

- **Cambio de comportamiento:** el default pasa de síncrono a `async`. El admin
  responde antes, pero la traducción ya no ocurre en la petición de guardado.
- **URLs públicas, CSP y `vercel.json`: intactos.** No se añaden rutas.
- **Seguridad:** el worker solo corre por CLI (`PHP_SAPI !== 'cli'` → `exit(1)`)
  y no expone ninguna ruta HTTP; hay un test que lo verifica (`/scripts/...` no
  procesa nada por HTTP). Los `campos` se guardan como JSONB, no se interpolan
  en SQL (siempre `PDO` preparado).
- **Riesgo principal:** en `async` sin worker, los trabajos se acumulan en
  `pendiente` y el contenido queda en `es`. Mitigación: `TRANSLATE_MODE=sync`
  donde no haya worker, o ejecutar el worker (cron). Es recuperable: al arrancar
  el worker, drena toda la cola acumulada.
- **Pendiente opcional:** indicador de estado de traducción en las vistas del
  admin (`pendiente`/`ok`), que el plan marcaba como opcional.

## 8. Pendiente / siguiente paso

- Ejecutar el worker en producción (cron en la VM) y/o poner `TRANSLATE_MODE=sync`
  en Vercel mientras no haya worker.
- Rotar los secretos históricos pendientes (Google Drive API key, `DB_PASSWORD`).