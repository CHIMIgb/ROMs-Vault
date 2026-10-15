# Worker de traducciones en GitHub Actions

- **Fecha:** 2026-10-14
- **Rama:** `feature/i18n-traduccion-contenido-bd`
- **HEAD previo:** `1283f64`
- **Plan origen:** `docs/planificado/2026-10-14-guardado-asincrono-cola-bd-worker.md`
- **Estado:** implementado (2026-10-14). Pendiente en producción: crear los 10
  secretos y hacer merge a `main` (el `schedule` solo existe en la rama por
  defecto).

---

## 1. Resumen

La cola `traduccion_cola` (commit `1283f64`) ya tenía worker CLI pero **ningún
ejecutor en producción**: Vercel es serverless, no hay VM ni proceso de larga
vida, así que los jobs se quedaban en `pendiente` indefinidamente.

Este cambio **no toca ni una línea de código de la aplicación**: sube el worker
CLI existente (`scripts/worker-traducciones.php`) a **GitHub Actions**, que actúa
como cron gratuito contra Neon cada 5 minutos. `TRANSLATE_MODE=async` sigue
siendo el default.

Incluye además un arreglo del **schema base** (Fase 0) sin el cual el job `tests`
del CI fallaba en cualquier runner limpio.

## 2. Por qué GitHub Actions

- **El repositorio es público** → los minutos de Actions son **gratuitos e
  ilimitados** (el cupo de 2000 min/mes aplica solo a repos privados).
- **No hay que aprovisionar nada**: ni VM, ni contenedor persistente, ni tarjeta
  de crédito, ni un servicio externo con su propio ciclo de vida.
- **El runner es efímero y aislado**: no queda nada corriendo entre ejecuciones;
  ideal para un worker que solo drena y termina.
- **Secretos cifrados** y enmascarados en los logs por GitHub. Contrapartida: al
  ser un repo público, **los logs son públicos**; por eso el workflow no imprime
  ninguna credencial (GitHub las enmascara igualmente).

## 3. Alternativas descartadas

| Opción | Por qué no |
|---|---|
| Vercel Cron | Hobby = 1 vez al día (una expresión más frecuente **falla el despliegue**); invoca URLs, no comandos; Pro (por minuto) cuesta ~20 $/mes |
| Neon Function Trigger | Viable, pero introduce una función desplegada aparte con otro runtime y otro ciclo de despliegue |
| `pg_cron` en Neon | Solo puede ejecutar SQL: no hace HTTP ni lanza procesos |
| Trigger SQL para traducir | Sin extensiones HTTP (`http`, `pg_net`, `plpython3u` no existen en su Neon); correría **dentro de la transacción** del guardado, y un fallo de DeepL abortaría el guardado |
| VM gratuita (Oracle/GCP/alwaysdata) | Requiere tarjeta o tiene trampas (reclamo de VMs ociosas, planes sin uso comercial); más infraestructura que mantener sin necesidad |
| Toggle sync/async en el admin | Descartado por decisión del 2026-10-14: menos superficie, y `TRANSLATE_MODE` ya sirve de interruptor de emergencia |

## 4. Archivos

| Acción | Archivo | Cambio |
|---|---|---|
| CREAR | `.github/workflows/worker-traducciones.yml` | Workflow: cron cada 5 min + disparo manual; ejecuta el worker contra Neon |
| MODIFICAR | `data/roms-vaultDB-postgreSQL.sql` | Fase 0: añade `traduccion_cola` (tabla + 2 índices + trigger) al schema base |
| CREAR | `docs/2026-10-14-worker-traducciones-github-actions.md` | Este documento |

**No se modifica ningún fichero PHP.** El worker ya existía y no se toca.

## 5. El workflow

```yaml
on:
  schedule:
    - cron: '*/5 * * * *'
  workflow_dispatch:
    inputs:
      max: { description: '...', required: false, default: '10' }
permissions: { contents: read }
concurrency: { group: worker-traducciones, cancel-in-progress: false }
jobs:
  worker:
    timeout-minutes: 15
    env: (10 secretos)
    steps: checkout + setup-php (pdo_pgsql, curl) + cache composer + composer
           install + php scripts/worker-traducciones.php --una-vez --max=...
```

Decisiones destacadas:

1. **`--una-vez`**: una sola pasada y termina. El bucle infinito no tiene sentido
   en un runner efímero; el "bucle" lo hace el cron.
2. **`--max=10`** por defecto: cota de seguridad por ejecución. El disparo manual
   permite subirlo.
3. **`concurrency` sin cancelar**: nunca hay dos workers a la vez (aunque el
   reclamo con `SKIP LOCKED` ya sería seguro). Un run en curso termina; el
   siguiente espera.
4. **`timeout-minutes: 15`** (el plan aprobado decía 5): se amplía a propósito.
   Si se matara el proceso a mitad de un job, ese job quedaría en `en_proceso`
   **para siempre**, porque no hay rescate de jobs colgados. Un margen amplio
   evita stranded jobs; el timeout solo actúa como red de seguridad real.
5. **Sin `.env`**: `database.php` usa `Dotenv::createImmutable(...)->safeLoad()`,
   que no falla si el fichero no existe, y **no sobrescribe variables ya
   presentes** en el entorno. El workflow inyecta los secretos como variables de
   entorno, así que ganan sobre el `.env`. (Verificado: ver sección 8.3.)
6. **`extensions: pdo_pgsql, curl`**: `pdo_pgsql` para la BD y **`curl` porque
   `AbstractTranslateProvider` usa `curl_init()`** (`src/config/translation/`).
7. **No se define `TRANSLATE_MODE`**: es irrelevante para el worker, que drena la
   cola siempre. Solo lo consultan los controladores al guardar.

## 6. Secretos requeridos

Se crean en **Settings → Secrets and variables → Actions → New repository
secret**. Los valores están en el `.env.neon` local (gitignored).

| Secreto | Qué es | Valor esperado |
|---|---|---|
| `DB_HOST` | Host del endpoint **directo** de Neon (sin `-pooler`) | `ep-....neon.tech` |
| `DB_PORT` | Puerto | `5432` |
| `DB_NAME` | Base de datos | `neondb` |
| `DB_USER` | Usuario | `neondb_owner` |
| `DB_PASSWORD` | Contraseña | la de Neon |
| `DB_SSLMODE` | Modo SSL | `require` |
| `TRANSLATE_PROVIDER` | Proveedor | `deepl` |
| `TRANSLATE_API_KEY` | Clave del proveedor | la de DeepL |
| `TRANSLATE_TARGETS` | Idiomas destino | `en,fr,pt,de,ja,it,ru,tr,zh,ko,ar` |
| `TRANSLATE_TIMEOUT` | Timeout por llamada (s) | p. ej. `30` |

> Usar el **endpoint directo** de Neon (sin `-pooler`) es la regla del proyecto
> para cualquier proceso con `SET`/`set_config` de sesión, por el incidente del
> `search_path` que se filtra por el pooler. Ojo: el `DB_HOST` del `.env.neon`
> local apunta al **pooler** (`...-pooler....neon.tech`); para el secreto hay que
> quitar el sufijo `-pooler` (el worker mantiene la conexión y usa bloqueos de
> fila, así que el endpoint directo es lo correcto aquí).

## 7. Puesta en marcha

1. Crear los 10 secretos anteriores.
2. **Hacer merge a `main`**: el disparador `schedule` solo se evalúa en la rama por
   defecto. Antes de eso el workflow no corre solo.
3. Probar en **Actions → Worker traducciones → Run workflow** (`max` = 10 por
   defecto) y comprobar que termina en verde.
4. Confirmar en la app: guardar un juego/categoría en el admin y ver que, pasado
   un rato, la traducción aparece (y que la fila desaparece de la cola o pasa a
   `ok`).

## 8. Verificación (evidencia)

### 8.1 El schema base aplicado en solitario (como hace el CI)

BD scratch creada de cero y cargada **solo** con `data/roms-vaultDB-postgreSQL.sql`:

```
EXIT=0
--- columnas de traduccion_cola ---  (12 filas: id, entidad, entidad_id, campos,
    estado, intentos, max_intentos, disponible_en, bloqueado_en, error,
    creado_en, actualizado_en)
--- indices ---   idx_traduccion_cola_pendientes / traduccion_cola_pkey /
                  uq_traduccion_cola_activa
--- triggers ---  trg_traduccion_cola_updated_at   (exactamente 1)
--- el trigger mantiene actualizado_en ---  trigger_funciona = t
--- el indice unico parcial deduplica ---   DEDUPE_OK
```

### 8.2 Idempotencia y coexistencia con la migración

Con la tabla ya creada por el schema base, se aplicó encima la migración
`data/migrations/2026-10-14-traduccion-cola.sql`:

```
CREATE TABLE   NOTICE: la relación «traduccion_cola» ya existe, omitiendo
CREATE INDEX   NOTICE: la relación «idx_traduccion_cola_pendientes» ya existe
CREATE INDEX   NOTICE: la relación «uq_traduccion_cola_activa» ya existe
CREATE FUNCTION / DO                                            EXIT=0
triggers tras la migración: 1
```

Es decir: schema base y migración **se solapan sin errores** y sin duplicar el
trigger.

### 8.3 El worker corre con SOLO variables de entorno (sin `.env`)

Como el `.env` local apunta a Neon, se probó la precedencia apuntando el entorno
a un host inalcanzable:

```
=== A) DB_HOST = 203.0.113.1 ===
Error de conexión PostgreSQL: SQLSTATE[08006] could not connect to server:
    Connection timed out ... host "203.0.113.1"
No se pudo conectar a la BD. Revisa .env (DB_*).
EXIT=1
```

Si el `.env` hubiera ganado, habría conectado a Neon. Ganó el entorno.

Y apuntando a la BD local `roms-vault-test` (que **no existe** en Neon), el worker
conectó, reclamó un job real y terminó:

```
== Worker de traducciones (cola en BD) ==
Proveedor : deepl
Modo web  : async
[job 1] juego#12 error (2 idioma(s) con error); reintento en 4 intento(s) más
Resumen: 1 trabajo(s) procesado(s) | 0 ok | 1 con error | 1 ciclo(s)
```

(Los errores son esperados: la clave era dummy. Lo relevante es que **reclamó y
procesó** un job usando solo variables de entorno.)

### 8.4 La Fase 0 arregla de verdad el CI (antes/después)

Se replicó el escenario exacto del runner: recrear `roms-vault-test` desde el
schema base y correr los tests.

**Antes** (schema de `HEAD`, sin `traduccion_cola`):

```
traduccion_cola | tablas_public
 AUSENTE        |            11
WorkerTraduccionesTest::setUpBeforeClass
PDOException: SQLSTATE[42P01]: no existe la relación «public.traduccion_cola»
  .../WorkerTraduccionesTest.php:31
ERRORS! Tests: 1, Errors: 1.        EXIT=2
```

**Después** (schema corregido):

```
traduccion_cola | tablas_public
 traduccion_cola |            12
... suite Integration completa ...
OK (90 tests, 306 assertions)      EXIT=0
```

Y la **suite completa** (`composer test`, lo que corre el CI) contra esa BD
recreada:

```
OK (254 tests, 15014 assertions)   EXIT=0
```

## 9. Fase 0: por qué el schema base no tenía `traduccion_cola`

`data/roms-vaultDB-postgreSQL.sql` es lo único que carga el job `tests` del CI
(`ci.yml`, línea 124) en un runner limpio. La tabla `traduccion_cola` vivía **solo
en su migración**, así que:

- `WorkerTraduccionesTest` hace `TRUNCATE TABLE public.traduccion_cola` (línea 31)
  → fallo en cuanto la rama llegase a un runner.

El arreglo añade al schema base la tabla, sus dos índices y su trigger,
**equivalentes a la migración** (mismos nombres). Detalle importante: la cola usa
la columna **`actualizado_en`**, no `updated_at`, así que **no puede** reutilizar
`update_updated_at_column()` (que escribe `NEW.updated_at`); se copia su función
propia `fn_traduccion_cola_updated_at()`, igual que en la migración.

Es la **única** tabla que faltaba: el resto de objetos de las migraciones
(`auditoria`, `usuarios.locked_until`, las tablas de traducciones) ya estaban en el
schema base.

## 10. Límites del cron de GitHub Actions

- **Mínimo 5 minutos** por expresión; `*/5` es el más frecuente posible.
- `schedule` **solo existe en la rama por defecto**.
- Puede **retrasarse varios minutos o saltarse** bajo carga: es "en unos minutos",
  no un cronómetro.
- GitHub **desactiva el schedule tras 60 días sin actividad** en el repositorio
  (basta con volver a activarlo desde la pestaña Actions).
- `timeout-minutes: 15` por ejecución.

## 11. Impacto y riesgos

- **URLs públicas, CSP y `vercel.json`: intactos.** No se añade ninguna ruta ni
  endpoint; el worker solo corre por CLI.
- **Sin cambios de código PHP**: el riesgo de regresión funcional es nulo.
- **El schema base cambia**: solo **añade** objetos con `IF NOT EXISTS` /
  `CREATE OR REPLACE`. No altera ni borra nada existente.
- **Riesgo de jobs colgados**: si un run se matara a mitad (timeout real), el job
  quedaría en `en_proceso`. Mitigado con `timeout-minutes` amplio; el rescate de
  jobs colgados sigue **fuera de alcance** (se descartó).
- **Coste**: 0. El repo es público → minutos ilimitados.

## 12. Pendiente / siguiente paso

- Crear los 10 secretos y hacer merge a `main`.
- Rotar los secretos históricos pendientes (Google Drive API key, `DB_PASSWORD`).
- (Opcional, sin aprobar) rescate de jobs `en_proceso` colgados.
