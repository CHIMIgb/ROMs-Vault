# Drenado oportunista de traducciones tras guardar

- **Fecha:** 2026-10-15
- **Ramas:** `fix/formato-imagen-largo-validacion` (Opción A, ya fusionada) y
  `feature/drenado-no-bloqueante-worker` (Opción B, esta rama)
- **Estado:** implementado y verificado. Job pendiente de `juego/156` drenado en
  producción (Neon) durante la puesta a punto.

---

## 1. Resumen

En modo `async`, al guardar un juego el admin **encola** la traducción en
`traduccion_cola` y el worker CLI la procesa. El problema: el **cron de GitHub
Actions casi no se dispara** (2 corridas en 11 h frente a las ~132 esperadas con
`*/5`), así que la cola se quedaba sin drenar y el contenido permanecía en `es`.

Se añade un **drenado oportunista**: al guardar, el admin dispara el procesamiento
de unos pocos trabajos de la cola, sin depender del cron. El cron/worker se
mantienen como red de seguridad.

## 2. Dos intentos: Opción A y Opción B

### Opción A (descartada): «responder y seguir trabajando» en la misma petición

La primera versión (`6e059ed`, fusionada) intentaba, dentro del mismo proceso de
la petición, enviar el `302` al navegador y **después** drenar la cola:

- enviar el `302` + `Content-Length: 0`;
- `ignore_user_abort(true)` y `session_write_close()`;
- `fastcgi_finish_request()` si existe; si no, `flush()`;
- llamar a `TraduccionWorker::drenarOportunista()`.

**Por qué no funcionó:** el contenedor es `php:8.2-apache` (**mod_php**), que **no
tiene `fastcgi_finish_request()`**; el fallback `flush()` **no entrega la
respuesta antes de terminar el script** (Apache bufferiza y Vercel proxya la
petición). Resultado: el navegador esperaba a que acabase el drenado (~3-5 s) y el
formulario se percibía como **síncrono**, justo lo que la cola quería evitar.

### Opción B (implementada): segunda petición interna «fire-and-forget»

El drenado sale de la petición del guardado y se ejecuta en **otra petición
HTTP** que **no se espera**:

```
[Admin guarda]
   |  (1) INSERT en traduccion_cola                         (ms)
   |  (2) DrenadoTrigger::disparar()  -> socket local, fire-and-forget
   v
302 /admin/dashboard        (el navegador cierra el formulario YA)
                                        \
                                         \ (conexion aparte, mismo contenedor)
                                          v
                       [endpoints/drain_traducciones.php]
                          - valida X-Internal-Drain (hash_equals)
                          - ignore_user_abort(true)
                          - TraduccionWorker::drenarOportunista()
```

Se eligió una petición HTTP propia (y no un proceso `exec(... &)`) porque en Vercel
el contenedor **puede suspenderse al quedar ocioso** y matar un proceso lanzado en
segundo plano; una petición en curso, en cambio, lo mantiene vivo hasta terminar.

## 3. Piezas

| Símbolo | Archivo | Rol |
|---|---|---|
| `DrenadoTrigger` | `src/config/DrenadoTrigger.php` | `disponible()`, `disparar()`, `construirPeticion()`, `urlInterna()` |
| endpoint | `endpoints/drain_traducciones.php` | Entrypoint autónomo autenticado por `X-Internal-Drain` |
| `TraduccionWorker` | `src/config/TraduccionWorker.php` | Núcleo de la cola (compartido con el worker CLI) |

`responderYDrenar()` en `AdminController` queda reducido a:

```php
header('Location: /admin/dashboard');
DrenadoTrigger::disparar();
```

`DrenadoTrigger::disparar()` es **fail-open y no bloqueante**:

- Solo actúa si hay **secreto** (`INTERNAL_DRAIN_SECRET`) y **proveedor**
  configurado; si no, es no-op.
- Abre un socket local (`fsockopen`, timeout 0.2 s), escribe la petición con la
  cabecera `X-Internal-Drain` y **cierra sin leer la respuesta**.
- Cualquier excepción se registra y se ignora: nunca afecta al guardado.

`drenarOportunista()` (en el endpoint) es **best-effort y fail-open**:

- **No hace nada si no hay proveedor configurado** (`TraduccionService::proveedorConfigurado()`).
  Sin este guard, `traducirEntidad()` devolvería error y **quemaría intentos**.
- Procesa como máximo `TRANSLATE_OPORTUNISTA_MAX` trabajos (por defecto **3**)
  dentro de `TRANSLATE_OPORTUNISTA_PRESUPUESTO` segundos (por defecto **5**).

## 4. Seguridad del endpoint

- Autenticación por **secreto compartido** (`INTERNAL_DRAIN_SECRET`) en la cabecera
  `X-Internal-Drain`, comparado con `hash_equals` (tiempo constante).
- Sin secreto configurado, responde **403** a todo.
- **No** se confía en `REMOTE_ADDR`: detrás del proxy de Vercel podría ser
  `127.0.0.1` también para tráfico externo. El secreto es la única barrera.
- El endpoint llama **directo** a `TraduccionWorker` (nunca al controlador), por lo
  que **no hay recursión** de drenados.
- Abuso si el secreto se filtra: acotado (3 trabajos / 5 s por llamada) y
  mitigable rotando el secreto.

## 5. Refactor (DRY)

El procesamiento de cada trabajo vive en `src/config/TraduccionWorker.php` y lo
comparten **el endpoint de drenado y el worker CLI**:

| Símbolo | Uso |
|---|---|
| `procesarUno(?string $entidad): ?array` | Reclama y procesa un trabajo |
| `drenar(int $max, float $presupuesto, ?string $entidad): array` | Bucle acotado |
| `drenarOportunista(?int $max, ?float $presupuesto): array` | Guard de proveedor + defaults de entorno |
| `MOTIVOS_PERMANENTES` | Motivos que van directos a dead-letter |

El worker CLI (`scripts/worker-traducciones.php`) solo gestiona argumentos, sondeo
y salida; el endpoint solo autentica y drena. El procesamiento es el mismo código
(mismas garantías de `TraduccionService`: incremental por `origen_hash`, nunca pisa
`origen='manual'`).

## 6. Archivos

| Acción | Archivo | Cambio |
|---|---|---|
| CREAR | `src/config/TraduccionWorker.php` | Núcleo de procesamiento reutilizable (Opción A) |
| CREAR | `src/config/DrenadoTrigger.php` | Disparo fire-and-forget (Opción B) |
| CREAR | `endpoints/drain_traducciones.php` | Endpoint interno que drena (Opción B) |
| MODIFICAR | `src/controllers/AdminController.php` | `require_once` + `responderYDrenar()` (Opción B) |
| MODIFICAR | `scripts/worker-traducciones.php` | Usa `TraduccionWorker` (salida y banderas idénticas) |
| MODIFICAR | `.env.example` | `TRANSLATE_OPORTUNISTA_*` e `INTERNAL_DRAIN_SECRET`/`INTERNAL_DRAIN_URL` |
| MODIFICAR | `docker-entrypoint.sh` | Propaga `INTERNAL_DRAIN_SECRET` / `INTERNAL_DRAIN_URL` al `.env` |
| MODIFICAR | `README.md` | Sección de traducción al guardar |
| MODIFICAR | `tests/bootstrap.php` | Carga `TraduccionWorker` y `DrenadoTrigger` |
| MODIFICAR | `tests/Integration/Server.php` | Define `INTERNAL_DRAIN_SECRET` de prueba |
| CREAR | `tests/Unit/TraduccionWorkerTest.php` | 6 casos (dead-letter, máximo, fail-open) |
| CREAR | `tests/Unit/DrenadoTriggerTest.php` | 7 casos (petición, guards, URL interna) |
| CREAR | `tests/Unit/Support/ColaFalsa.php` | Cola en memoria para los tests |
| CREAR | `tests/Integration/DrainEndpointTest.php` | 4 casos (403/200, no toca la cola) |
| MODIFICAR | `tests/Integration/WorkerTraduccionesTest.php` | Caso: sin proveedor no toca la cola real |

## 7. Validación

- `phpunit`: **OK (276 tests, 14946 assertions)**.
- `phpstan analyse` (nivel 5): **No errors**.
- `phpcs` (config del proyecto) sobre los archivos nuevos: limpio.
- **Prueba en producción (Neon):** `worker-traducciones.php --una-vez --max=5`
  drenó el job `id=2` (`juego/156`) → `ok`; los 11 idiomas quedaron con su lista
  `idiomas` localizada (`en, fr, pt, de, ja, it, ru, tr, zh, ko, ar`). Cola vacía.

## 8. Riesgos y notas

- El drenado acota cuándo **empezar** un trabajo nuevo, no interrumpe el que ya
  corre: una entidad con 11 idiomas puede tardar más que el presupuesto. Se
  compensa con `TRANSLATE_OPORTUNISTA_MAX` bajo (3).
- Ocupa un worker de Apache unos segundos por guardado. Con el guard de proveedor
  y el techo de trabajos el impacto es pequeño y acotado.
- Si el auto-disparo no llega (o falta el secreto), el guardado sigue instantáneo
  y el cron/worker retoma la cola: no hay pérdida de datos.
- Rollback trivial: borrar `INTERNAL_DRAIN_SECRET` en Vercel desactiva el drenado
  oportunista sin tocar el código.
