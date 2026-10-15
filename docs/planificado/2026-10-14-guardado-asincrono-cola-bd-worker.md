# Guardado asíncrono de traducciones: cola en BD + worker CLI PHP

**Fecha:** 2026-10-14
**Estado:** implementado (2026-10-14) — migración aplicada en Neon (endpoint directo);
default `TRANSLATE_MODE=async` por decisión del 2026-10-14 (el plan preveía `sync`).
**Ejecutor del worker: resuelto con GitHub Actions** (cron cada 5 min contra Neon) en
lugar de una VM/servidor: ver `docs/2026-10-14-worker-traducciones-github-actions.md`.
Pendiente en producción: crear los 10 secretos y hacer merge a `main`.
Detalle y evidencia: `docs/2026-10-14-cola-traducciones-asincronas.md`.
**Ámbito:** i18n (traducción de contenido, bloque 2)
**Documentos relacionados:**
`src/config/TraduccionService.php`,
`src/controllers/AdminController.php`,
`src/controllers/CategoriaController.php`,
`docs/2026-10-13-idiomas-tabla-determinista.md`,
`docs/planificado/2026-10-14-proveedor-deepl-traduccion.md`

> Nota: con el proveedor **DeepL** (ver el plan enlazado) la traducción funciona
> desde Vercel sin worker, por lo que este plan (cola + worker) pasa a ser una
> mejora **opcional**.

---

## 1. Resumen del cambio

Hoy, al guardar un juego o una categoría en el admin, la traducción a los **11
idiomas destino** se hace **de forma síncrona dentro de la misma petición**: el
registro ya está persistido, pero el `header('Location: ...')` ocurre **después**
de traducir. Es decir, el admin espera a que terminen las 11 llamadas al
proveedor (~11 × 0.2–0.5 s con modelos cargados).

Este plan desacopla ese trabajo: al guardar se **encola** y el admin responde al
instante; un **worker CLI en PHP** consume la cola y traduce con **reintentos y
backoff**. **Sin broker y sin Python** (nada de Celery/Redis).

---

## 2. Situación actual (código)

Flujo síncrono actual:

- `AdminController::update()` → tras `Juego::update()` llama a
  `autoTraducirJuego($id, $descripcion, $idiomas)` (`AdminController.php:312`,
  `:337`).
- `CategoriaController::update()` → `autoTraducirCategoria($id, $nombre,
  $descripcion)` (`CategoriaController.php:121`, `:117`).
- Ambos llaman a `TraduccionService::traducirEntidad($entidad, $id, $campos)`
  (`TraduccionService.php:134`) → `generar()` recorre los targets y llama al
  proveedor **dentro de la request**.

Propiedades ya existentes y que se conservan:
- **Incremental** por `origen_hash` (no re-traduce si no cambió el texto).
- **Respeta `origen='manual'`** (no pisa correcciones).
- **Fail-open**: un fallo/timeout se registra en `auditoria`
  (`traduccion_error`) y no rompe el guardado.
- `idiomas` se resuelve con la **tabla determinista** (offline); solo
  `descripcion`/`nombre` van al proveedor.

Qué se traduce: juego → `descripcion` + `idiomas` (no el `titulo`); categoría →
`nombre` + `descripcion`.

---

## 3. Diseño propuesto

```
[Guardar en admin] --INSERT--> traduccion_cola --SELECT ... SKIP LOCKED--> [worker CLI]
                                                                              |
                                                              TraduccionService::traducirEntidad
                                                                              |
                                                              upsert en *_traducciones (BD)
```

1. **Al guardar**: en vez de traducir inline, se **encola** un job (INSERT en
   `traduccion_cola`). Si ya hay un job pendiente/en proceso para la misma
   entidad, se **actualiza** (dedupe) en lugar de duplicar. El admin responde al
   instante.
2. **Worker** (`scripts/worker-traducciones.php`): en bucle, reclama el siguiente
   trabajo con `SELECT ... FOR UPDATE SKIP LOCKED`, llama a
   `TraduccionService::traducirEntidad(...)` y marca el resultado.
3. **Reintentos con backoff exponencial** y estado `fallido` (dead-letter) tras
   `max_intentos`.
4. **UI**: las entidades con job pendiente pueden mostrar un estado
   "traducción pendiente". Mientras tanto, el **sitio público ya hace fallback a
   `es`** si falta la traducción (seguro).

---

## 4. Esquema de la tabla (migración idempotente)

`data/migrations/2026-10-14-traduccion-cola.sql` (PostgreSQL):

```sql
CREATE TABLE IF NOT EXISTS traduccion_cola (
    id             BIGSERIAL PRIMARY KEY,
    entidad        VARCHAR(20)  NOT NULL CHECK (entidad IN ('categoria','juego')),
    entidad_id     INTEGER      NOT NULL,
    campos         JSONB        NOT NULL,
    estado         VARCHAR(20)  NOT NULL DEFAULT 'pendiente'
                   CHECK (estado IN ('pendiente','en_proceso','ok','fallido')),
    intentos       SMALLINT     NOT NULL DEFAULT 0,
    max_intentos   SMALLINT     NOT NULL DEFAULT 5,
    disponible_en  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    bloqueado_en   TIMESTAMPTZ,
    error          TEXT,
    creado_en      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_traduccion_cola_pendientes
    ON traduccion_cola (estado, disponible_en);

-- Dedupe: un solo job activo por entidad.
CREATE UNIQUE INDEX IF NOT EXISTS uq_traduccion_cola_activa
    ON traduccion_cola (entidad, entidad_id)
    WHERE estado IN ('pendiente','en_proceso');
```

Reclamo atómico por el worker (patrón cola sin broker):

```sql
UPDATE traduccion_cola
   SET estado = 'en_proceso',
       bloqueado_en = now(),
       intentos = intentos + 1,
       actualizado_en = now()
 WHERE id = (
   SELECT id FROM traduccion_cola
    WHERE estado = 'pendiente' AND disponible_en <= now()
    ORDER BY id
    FOR UPDATE SKIP LOCKED
    LIMIT 1
 )
 RETURNING id, entidad, entidad_id, campos, intentos, max_intentos;
```

- **Éxito** → `estado='ok'`.
- **Error** → si `intentos < max_intentos`: `estado='pendiente'` con
  `disponible_en = now() + (2^intentos)` segundos (backoff); si no,
  `estado='fallido'` y `error` con el mensaje (dead-letter).

---

## 5. Archivos afectados

```
[CREAR] data/migrations/2026-10-14-traduccion-cola.sql
  - Tabla traduccion_cola + índices (idempotente)

[CREAR] src/models/TraduccionCola.php
  - encolar(), reclamar(), marcarOk(), marcarError() (raw PDO)

[MODIFICAR] src/config/TraduccionService.php
  - Nuevo método encolar() (o mover el enqueue a un servicio dedicado);
    traducirEntidad() se mantiene para el backfill y el worker

[MODIFICAR] src/controllers/AdminController.php
  - autoTraducirJuego() pasa a encolar() en vez de traducir inline

[MODIFICAR] src/controllers/CategoriaController.php
  - autoTraducirCategoria() pasa a encolar()

[CREAR] scripts/worker-traducciones.php
  - CLI (php_sapi_name()==='cli'): bucle, reclamar, traducir, marcar, backoff;
    flags: --una-vez, --max=N, --entidad=juego|categoria, --verbose

[CREAR] tests/Unit/TraduccionColaTest.php
[CREAR] tests/Integration/WorkerTraduccionesTest.php

[MODIFICAR] src/views/admin/... (opcional)
  - Indicador "traducción pendiente/ok" por entidad

[MODIFICAR] infra del worker (docker-compose de la VM de deploy, doc de migración)
  - Servicio `worker` (misma imagen de la app, comando
    `php scripts/worker-traducciones.php`) para no traducir en el request web

[CREAR] docs/planificado/2026-10-14-guardado-asincrono-cola-bd-worker.md
  - Este documento
```

**Notas de diseño:**
- Se puede introducir un interruptor de modo, p. ej. `TRANSLATE_MODE=sync|async`
  (default `sync` para no cambiar comportamiento hasta activarlo).
- El worker **reutiliza** `TraduccionService::traducirEntidad`, por lo que la
  lógica de incrementalidad, `manual` y fail-open es idéntica al backfill.

---

## 6. Orden de ejecución

1. Migración `2026-10-14-traduccion-cola.sql` (idempotente).
2. Modelo `TraduccionCola` (encolar/reclamar/marcar).
3. `TraduccionService::encolar()` + cambios en los dos controladores (tras el
   flag `TRANSLATE_MODE`).
4. `scripts/worker-traducciones.php`.
5. Servicio `worker` en el compose de la VM (ver doc de migración de LT).
6. Tests Unit + Integration.
7. Indicador de estado en la UI (opcional).
8. Activar `TRANSLATE_MODE=async` en producción.
9. Docs fechados + roadmap.

---

## 7. Verificación

- Unit (`vendor/bin/phpunit --testsuite Unit`): enqueue/dedupe, backoff, estados.
- Integration (requiere `TEST_DB_PASSWORD` y `roms-vault-test`): reclamo atómico
  con dos workers simultáneos (sin doble procesado), reintento y dead-letter.
- Guardar un juego en admin → responde **de inmediato** y aparece un job
  `pendiente`; el worker lo pasa a `ok` y las traducciones quedan en BD.
- Idempotencia: guardar dos veces el mismo texto → un solo job activo; no
  re-traduce (hash).
- `origen='manual'` intacto tras pasar por la cola.
- Reinicio del worker a mitad de un job → el reclamo no pierde ni duplica.

---

## 8. Impacto y compatibilidad

- **No** cambia URLs públicas, CSP ni `vercel.json`.
- Requiere **una migración** nueva (aditiva, idempotente) en Neon.
- Con `TRANSLATE_MODE=sync` (default) el comportamiento actual no cambia.
- El **worker** necesita: acceso a la BD (Neon) y al proveedor de traducción
  (DeepL, salida HTTPS); al no traducir en el request web, la latencia de
  guardado desaparece.

---

## 9. Gotchas

- **No duplicar jobs**: el índice único parcial por entidad activa lo evita;
  al encolar de nuevo se hace UPSERT/UPDATE del job existente.
- **Reintentos**: usar backoff exponencial y un máximo; no reintentar
  indefinidamente (evitar bucles y coste).
- **Coherencia con `manual`**: la comprobación sigue en `generar()`; el worker no
  debe saltársela.
- **Observabilidad**: registrar en `auditoria` (`traduccion_error`) igual que
  hoy, más un log del worker.
- **Un solo worker** basta a esta escala; `FOR UPDATE SKIP LOCKED` ya soporta
  varios sin colisión.
- **Cron alternativo**: si no se quiere un servicio siempre encendido, un cron
  `* * * * *` ejecutando el worker con `--una-vez` cumple la misma función.

---

## 10. Por qué NO Celery (y descartes)

Celery es **Python** y exige, además de la imagen del worker: un **broker**
(Redis/RabbitMQ), un **backend de resultados**, un **puente PHP→Celery**
(endpoint HTTP o cliente Redis en PHP), **monitorización** (Flower) y orquestar
varios servicios. Para "11 traducciones por guardado" es sobredimensionado y
**rompe el stack vanilla** del proyecto.

Alternativas evaluadas:
- **A) Cola en BD + worker PHP** → **elegida** (sin broker, sin Python).
- **B) Cron + backfill incremental** → mínima, pero con latencia hasta el
  intervalo del cron.
- **C) Redis + worker PHP** → añade broker; solo si el volumen lo justifica.
- **D) Celery** → descartada por coste/complejidad/stack.

---

## 11. Estado

- [x] Migración `traduccion_cola` — `1283f64` (aplicada en local, test y Neon); además
  incorporada al schema base `data/roms-vaultDB-postgreSQL.sql` para que el CI no falle.
- [x] Modelo `TraduccionCola` — `1283f64`.
- [x] `encolar()` + controladores (flag `TRANSLATE_MODE`) — `1283f64`.
- [x] `scripts/worker-traducciones.php` — `1283f64`.
- [x] Ejecutor del worker — **GitHub Actions** (`.github/workflows/worker-traducciones.yml`),
  en lugar del "servicio `worker` en la VM" que preveía el plan (no hay VM).
- [x] Tests Unit + Integration — `1283f64`.
- [x] Docs + roadmap — `docs/2026-10-14-cola-traducciones-asincronas.md` y
  `docs/2026-10-14-worker-traducciones-github-actions.md`.
- [ ] Activación en producción — pendiente: crear los 10 secretos, merge a `main` y
  comprobar un run verde.
