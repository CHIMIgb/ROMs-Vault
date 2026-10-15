# Drenado de traducciones no bloqueante (Opción B): disparo interno fire-and-forget

**Fecha:** 2026-10-15
**Estado:** implementado (2026-10-15) en `feature/drenado-no-bloqueante-worker`.
Detalle y evidencia: `docs/2026-10-15-drenado-oportunista-traducciones.md`.
**Pendiente en producción:** definir `INTERNAL_DRAIN_SECRET` en las variables de
entorno de Vercel antes/durante el despliegue (sin él, el drenado se omite y cae
al cron/worker, sin romper nada).
**Ámbito:** i18n (traducción de contenido, bloque 2) + operación del worker
**Documentos relacionados:**
`docs/2026-10-14-cola-traducciones-asincronas.md`,
`docs/2026-10-14-worker-traducciones-github-actions.md`,
`docs/2026-10-15-drenado-oportunista-traducciones.md`,
`src/config/TraduccionWorker.php`,
`src/controllers/AdminController.php`

---

## 1. Resumen del requerimiento (contexto de negocio)

En modo `async`, el admin **encola** la traducción en `traduccion_cola` y debe
responder al instante; el worker la procesa en segundo plano. El cron de GitHub
Actions se dispara de forma irregular (2 corridas en 11 h frente a las ~132
esperadas), así que se añadió un **drenado oportunista** (Opción A) que traduce
**dentro de la misma petición** del guardado.

**Problema detectado (reportado por el usuario):** con Opción A el formulario
**no se cierra de inmediato**: el navegador espera ~3-5 s (las ~11 llamadas a
DeepL). Es decir, la UI percibe un guardado **síncrono**, justo lo que la cola
quería evitar.

**Causa raíz técnica:** la Opción A intenta "responder y seguir trabajando"
(`header()` + `flush()` + `ignore_user_abort`), pero el contenedor es
`php:8.2-apache` (**mod_php**) y **no tiene `fastcgi_finish_request()`**; además
Vercel proxya la petición. El resultado es que **Apache no entrega la respuesta
al navegador hasta que el script termina**. El truco de `flush()` no es fiable en
este stack.

**Objetivo:** que el guardado vuelva a responder **al instante** (cierre
inmediato del formulario) **y** que las traducciones aparezcan solas en segundos,
sin depender del cron.

---

## 2. Situación actual (código)

`AdminController::responderYDrenar()` (Opción A):

```php
header('Location: /admin/dashboard');
header('Content-Length: 0');
ignore_user_abort(true);
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
else { while (ob_get_level() > 0) { @ob_end_flush(); } flush(); }
TraduccionWorker::drenarOportunista();   // <-- aquí se van 3-5 s que ve el usuario
```

Se llama desde `add()` y `edit()` justo antes de `exit`.

Piezas ya existentes que se reutilizan:
- `src/config/TraduccionWorker.php` — `procesarUno()`, `drenar()`, `drenarOportunista()`.
- `endpoints/` — entrypoints autónomos que **omiten `index.php`** (tienen su propia
  autenticación/CSRF). Patrón sancionado en `AGENTS.md`.
- `TRANSLATE_OPORTUNISTA_MAX` / `TRANSLATE_OPORTUNISTA_PRESUPUESTO`.

---

## 3. Arquitectura propuesta

El trabajo de traducción sale de la petición del guardado y pasa a una **segunda
petición interna** que no se espera.

```
[Admin guarda]
   |  (1) INSERT en traduccion_cola        (ms)
   |  (2) dispara peticion interna fire-and-forget (ms)   <- no lee respuesta
   v
302 /admin/dashboard            (el navegador cierra el formulario YA)
                                        \
                                         \ (conexion aparte, mismo contenedor)
                                          v
                          [endpoints/drain_traducciones.php]  (worker HTTP)
                             - valida X-Internal-Drain
                             - ignore_user_abort(true)
                             - TraduccionWorker::drenarOportunista()
                                          |
                                          v
                          upsert en juegos_traducciones / categorias_traducciones
```

Por qué una petición HTTP propia y no un proceso "detached":
- En Vercel el contenedor puede **suspenderse al quedar ocioso**; un proceso
  lanzado con `exec(... &)` podría morir. Una **petición HTTP en curso** mantiene
  el contenedor vivo hasta terminar. Es el mismo motivo por el que Opción A
  "funcionaba" (el trabajo ocurría dentro de una petición).
- Reutiliza exactamente el mismo código (`TraduccionWorker`), sin gestión de
  procesos ni rutas de PHP CLI en el contenedor.

---

## 4. Impacto Backend

### 4.1 Nueva clase `src/config/DrenadoTrigger.php`

Encapsula el disparo (SRP: el controlador no debería saber de sockets).

```php
class DrenadoTrigger {
    /** ¿Hay disparo configurado y con sentido? (secreto + proveedor). */
    public static function disponible(): bool;

    /**
     * Dispara el drenado en segundo plano (fire-and-forget).
     * No lee la respuesta y nunca lanza: devuelve true si envió la petición.
     */
    public static function disparar(): bool;

    /** Petición HTTP en crudo (extraída como función pura para testear). */
    public static function construirPeticion(string $secreto): string;

    /** URL interna derivada (127.0.0.1 + puerto local) o override por env. */
    public static function urlInterna(): string;
}
```

Lógica de `disparar()`:
1. Si `!disponible()` → `false` (sin secreto o sin proveedor configurado).
2. `urlInterna()`: `INTERNAL_DRAIN_URL` si está definido; si no,
   `http://127.0.0.1:{SERVER_PORT}/endpoints/drain_traducciones.php`
   (`SERVER_PORT` es fiable; las env vars del contenedor las oculta Apache y por
   eso el entrypoint regenera `.env`).
3. `fsockopen(host, puerto, errno, errstr, 0.2)` (timeout de conexión corto).
4. Enviar `construirPeticion($secreto)` y `fclose()` **sin leer la respuesta**.
5. Todo dentro de `try/catch (\Throwable)` con `@` en los llamados de red: el
   guardado **nunca** debe fallar por esto (fail-open).

`construirPeticion()`:

```
GET /endpoints/drain_traducciones.php HTTP/1.1\r\n
Host: 127.0.0.1\r\n
X-Internal-Drain: <secreto>\r\n
Connection: close\r\n
\r\n
```

### 4.2 Nuevo endpoint `endpoints/drain_traducciones.php`

Entrypoint autónomo (fuera de `index.php`), por lo que no pasa por el CSRF global
ni por la sesión. Autenticación propia:

```php
$secreto = $_ENV['INTERNAL_DRAIN_SECRET'] ?? getenv('INTERNAL_DRAIN_SECRET');
$dado    = $_SERVER['HTTP_X_INTERNAL_DRAIN'] ?? '';
if (!is_string($secreto) || $secreto === '' || !hash_equals($secreto, (string) $dado)) {
    http_response_code(403);
    exit;
}

ignore_user_abort(true);      // el cliente cierra el socket: seguir igual
@set_time_limit(0);

try {
    $res = TraduccionWorker::drenarOportunista();   // reutiliza el guard de proveedor
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res);
} catch (\Throwable $e) {
    LoggerService::write('traduccion_drain_endpoint_error', ['error' => $e->getMessage()], 'warning');
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['procesados' => 0, 'ok' => 0, 'error' => 0]);
}
```

Notas:
- **No** se llama al `AdminController` ni a nada que vuelva a disparar el trigger:
  se llama directamente a `TraduccionWorker` ⇒ **sin recursión**.
- `drenarOportunista()` ya respeta `TRANSLATE_OPORTUNISTA_MAX`/`PRESUPUESTO` y no
  toca la cola sin proveedor.

### 4.3 Reemplazo en `AdminController`

Eliminar el truco de `flush()`/`ignore_user_abort` de `responderYDrenar()` y
sustituirlo por un disparo no bloqueante:

```php
private function responderYDrenar(): void {
    DrenadoTrigger::disparar();          // ms: no lee respuesta
    header('Location: /admin/dashboard');
    exit;
}
```

(mismo nombre y mismas 2 llamadas para minimizar el diff; el comentario del
método se actualiza; se añade `require_once` de `DrenadoTrigger.php`).

`autoTraducirJuego()` sigue igual: encola en `async`.

### 4.4 Variables de entorno

| Variable | Obligatoria | Descripción |
|---|---|---|
| `INTERNAL_DRAIN_SECRET` | No | Secreto compartido del endpoint. Sin él, no hay drenado oportunista (cae al cron). |
| `INTERNAL_DRAIN_URL` | No | Override de la URL interna (por defecto se deriva de `SERVER_PORT`). Útil si el puerto no es fiable. |
| `TRANSLATE_OPORTUNISTA_MAX` | No | Ya existe (def. 3). |
| `TRANSLATE_OPORTUNISTA_PRESUPUESTO` | No | Ya existe (def. 5). |

`docker-entrypoint.sh`: añadir `INTERNAL_DRAIN_SECRET` y `INTERNAL_DRAIN_URL` al
heredoc que regenera `.env` (si no, el contenedor no las ve).

`.env.example`: documentarlas.

### 4.5 Archivos tocados (resumen)

| Acción | Archivo |
|---|---|
| CREAR | `src/config/DrenadoTrigger.php` |
| CREAR | `endpoints/drain_traducciones.php` |
| MODIFICAR | `src/controllers/AdminController.php` (require + `responderYDrenar()`) |
| MODIFICAR | `docker-entrypoint.sh` (propaga el secreto) |
| MODIFICAR | `.env.example` |
| MODIFICAR | `README.md` (sección de traducción) |
| CREAR | `tests/Unit/DrenadoTriggerTest.php` |
| CREAR | `tests/Integration/DrainEndpointTest.php` |
| MODIFICAR | `tests/Integration/Server.php` (define `INTERNAL_DRAIN_SECRET` de prueba) |
| REESCRIBIR | `docs/2026-10-15-drenado-oportunista-traducciones.md` (corregir "sin bloquear" y describir B) |

---

## 5. Impacto Frontend

- **Sin cambios de UI.** El guardado vuelve a responder al instante (comportamiento
  original de `async`): el navegador recibe el `302` y cierra el formulario ya.
- Se elimina también el `Content-Length: 0` de la respuesta (ya no hace falta).
- La traducción aparece unos segundos después, sin que el usuario espere.

---

## 6. Seguridad del endpoint

- **Autenticación por secreto** (`X-Internal-Drain`) con `hash_equals` (tiempo
  constante). Sin secreto configurado ⇒ 403 para todos.
- Se envía por **header**, no por query (no queda en logs de acceso).
- **No** se confía en `REMOTE_ADDR`: detrás del proxy de Vercel podría ser
  `127.0.0.1` también para tráfico externo. El secreto es la única barrera.
- Abuso si el secreto se filtra: acotado (3 trabajos / 5 s por llamada) y
  mitigable rotando el secreto. Se documenta como riesgo residual.
- No expone datos: solo devuelve contadores `{procesados, ok, error}`.

---

## 7. Tests

**Unit — `tests/Unit/DrenadoTriggerTest.php`** (sin red, sin BD):
- `construirPeticion()` incluye el método/ruta, `X-Internal-Drain: <secreto>` y
  `Connection: close`.
- `disparar()` devuelve `false` sin secreto.
- `disparar()` devuelve `false` sin proveedor configurado (aunque haya secreto).

**Integration — `tests/Integration/DrainEndpointTest.php`** (HTTP real):
- Sin header ⇒ **403** (y la cola no se toca).
- Con header incorrecto ⇒ **403**.
- Con header correcto + `TRANSLATE_PROVIDER=none` ⇒ **200** con
  `{procesados:0,ok:0,error:0}` y el job sigue `pendiente` con `intentos=0`.
- La URL `endpoints/drain_traducciones.php` no filtra código fuente (`<?php`).

**Regresión:**
- `WorkerTraduccionesTest::testGuardarJuegoEncolaTrabajoPendiente` sigue verde
  (302, job pendiente, 0 traducciones): los tests corren con proveedor `none`,
  así que `disparar()` es no-op y el guardado no espera.
- Suite completa + `phpstan` nivel 5 + `phpcs` del archivo nuevo.

---

## 8. Validación y riesgos

| Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|
| El auto-disparo no llega (firewall/proxy local) | Baja | Medio | Fallback automático al cron/worker; no rompe el guardado |
| Secreto no configurado en Vercel al desplegar | Media | Bajo | Sin secreto no hay trigger; el cron sigue. Documentar en el rollout |
| Recursión de drenados | Muy baja | Alto | El endpoint llama a `TraduccionWorker` directo, nunca al controlador |
| Consumo de un worker de Apache durante el drenado | Media | Bajo | Techo 3 trabajos / 5 s; prefork tiene varios hijos |
| `php -S` (dev/test) de un solo hilo | Baja | Bajo | No se ejercita: en tests el proveedor es `none` (no dispara). No hay espera de respuesta |
| Abuso si el secreto se filtra | Baja | Medio | Trabajo acotado + rotación de secreto |
| Tests dejan de ser herméticos | Baja | Medio | El guard `proveedorConfigurado()` evita cualquier red en tests |

**Compatibilidad:** no rompe el cron/worker de GitHub Actions (sigue siendo red de
seguridad); no cambia el esquema ni las rutas públicas existentes.

---

## 9. Despliegue (rollout)

1. Generar el secreto: `php -r "echo bin2hex(random_bytes(32));"`.
2. En Vercel → Environment Variables: `INTERNAL_DRAIN_SECRET=<secreto>`.
3. Merge del PR (dispara el deploy del contenedor).
4. Verificar en producción:
   - Guardar un juego: el formulario cierra **de inmediato**.
   - En segundos, el juego muestra traducciones (`idiomas` localizado).
   - `GET /endpoints/drain_traducciones.php` sin header ⇒ **403**.
5. Si algo falla, basta con **borrar** `INTERNAL_DRAIN_SECRET` en Vercel: el
   guardado sigue instantáneo y el cron/worker retoma el drenado.

---

## 10. Decisiones tomadas

1. **Petición HTTP interna** en vez de proceso detached (el contenedor de Vercel
   puede suspenderse al quedar ocioso; una petición en curso lo mantiene vivo).
2. **Secreto en env** (`INTERNAL_DRAIN_SECRET`), enviado por header.
3. **El endpoint llama directo a `TraduccionWorker`** (sin recursión).
4. **`DrenadoTrigger` como clase aparte** (SRP; el controlador queda delgado).
5. **Se conserva el cron** de GitHub Actions como red de seguridad.
6. La Opción A (drenado in-request) **se retira**: no es viable en mod_php+Vercel.

---

## 11. Alternativas descartadas

- **`exec('php scripts/worker-traducciones.php ... &')`** (proceso detached):
  riesgo de que el contenedor se suspenda y mate el proceso; además exige shell y
  ruta a PHP dentro del contenedor.
- **`workflow_dispatch` a GitHub**: latencia de arranque (5-20 s), depende de un
  PAT y de GitHub; más piezas móviles.
- **Migrar a php-fpm** para tener `fastcgi_finish_request()`: cambio grande de
  infraestructura solo para esto.
- **Vercel Cron**: en plan Hobby es 1 vez al día (insuficiente).
- **Dejar solo el cron**: ya demostró ser poco fiable.
