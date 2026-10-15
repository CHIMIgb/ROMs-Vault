# Drenado oportunista de traducciones tras guardar (Opción A)

- **Fecha:** 2026-10-15
- **Rama:** `fix/formato-imagen-largo-validacion` (2.º commit, tras el fix del 500)
- **Estado:** implementado y verificado. Job pendiente de `juego/156` drenado en
  producción (Neon).

---

## 1. Resumen

En modo `async`, al guardar un juego el admin **encola** la traducción en
`traduccion_cola` y el worker CLI la procesa. El problema: el **cron de GitHub
Actions casi no se dispara** (2 corridas en 11 h frente a las ~132 esperadas con
`*/5`), así que la cola se quedaba sin drenar y el contenido permanecía en `es`.

Se añade un **drenado oportunista**: tras responder el guardado, el propio
proceso de la petición procesa unos pocos trabajos de la cola, ya sin bloquear al
usuario. El cron/worker se mantienen como red de seguridad.

## 2. Diseño

Flujo del guardado (`add()`/`edit()` de `AdminController`):

1. `autoTraducirJuego()` encola el trabajo (`TraduccionService::encolar()`).
2. `responderYDrenar()`:
   - envía el `302` + `Content-Length: 0` al navegador;
   - `ignore_user_abort(true)` y `session_write_close()` (libera el lock de sesión);
   - `fastcgi_finish_request()` si existe (FPM); si no (mod_php/Apache), `flush()`;
   - llama a `TraduccionWorker::drenarOportunista()`.
3. `exit`.

El navegador sigue el redirect en cuanto recibe la respuesta; el drenado ocurre
después, en el mismo proceso.

`drenarOportunista()` es **best-effort y fail-open**:

- **No hace nada si no hay proveedor configurado** (`TraduccionService::proveedorConfigurado()`).
  Sin este guard, `traducirEntidad()` devolvería error y **quemaría intentos**.
- Procesa como máximo `TRANSLATE_OPORTUNISTA_MAX` trabajos (por defecto **3**)
  dentro de `TRANSLATE_OPORTUNISTA_PRESUPUESTO` segundos (por defecto **5**).
- Cualquier excepción se registra y se ignora: nunca afecta al guardado.

## 3. Refactor (DRY)

La lógica de procesar un trabajo vivía dentro de `scripts/worker-traducciones.php`.
Se extrae a `src/config/TraduccionWorker.php` para reutilizarla en los dos sitios:

| Símbolo | Uso |
|---|---|
| `procesarUno(?string $entidad): ?array` | Reclama y procesa un trabajo |
| `drenar(int $max, float $presupuesto, ?string $entidad): array` | Bucle acotado |
| `drenarOportunista(?int $max, ?float $presupuesto): array` | Guard de proveedor + defaults de entorno |
| `MOTIVOS_PERMANENTES` | Motivos que van directos a dead-letter |

El worker CLI ahora solo gestiona argumentos, sondeo y salida; el procesamiento
de cada trabajo es el mismo código que usa el admin (mismas garantías de
`TraduccionService`: incremental por `origen_hash`, nunca pisa `origen='manual'`).

## 4. Archivos

| Acción | Archivo | Cambio |
|---|---|---|
| CREAR | `src/config/TraduccionWorker.php` | Núcleo de procesamiento reutilizable |
| MODIFICAR | `src/controllers/AdminController.php` | `require_once`, `responderYDrenar()` y dos llamadas en `add()`/`edit()` |
| MODIFICAR | `scripts/worker-traducciones.php` | Usa `TraduccionWorker` (salida y banderas idénticas) |
| MODIFICAR | `.env.example` | Documenta `TRANSLATE_OPORTUNISTA_MAX` y `TRANSLATE_OPORTUNISTA_PRESUPUESTO` |
| MODIFICAR | `tests/bootstrap.php` | Carga `TraduccionWorker` |
| CREAR | `tests/Unit/TraduccionWorkerTest.php` | 6 casos (dead-letter, máximo, fail-open) |
| CREAR | `tests/Unit/Support/ColaFalsa.php` | Cola en memoria para los tests |
| MODIFICAR | `tests/Integration/WorkerTraduccionesTest.php` | Caso: sin proveedor no toca la cola real |

## 5. Validación

- `phpunit`: **OK (265 tests, 14925 assertions)**.
- `phpstan analyse` (nivel 5): **No errors**.
- `phpcs` sobre el archivo nuevo: limpio.
- **Prueba en producción (Neon):** `worker-traducciones.php --una-vez --max=5`
  drenó el job `id=2` (`juego/156`) → `ok`; los 11 idiomas quedaron con su lista
  `idiomas` localizada (`en, fr, pt, de, ja, it, ru, tr, zh, ko, ar`). Cola vacía.

## 6. Riesgos y notas

- El drenado acota cuándo **empezar** un trabajo nuevo, no interrumpe el que ya
  corre: una entidad con 11 idiomas puede tardar más que el presupuesto. Se
  compensa con `TRANSLATE_OPORTUNISTA_MAX` bajo (3).
- Ocupa un worker de Apache unos segundos por guardado. Con el guard de proveedor
  y el techo de trabajos el impacto es pequeño y acotado.
- No depende del cron de GitHub; este sigue siendo útil como red de seguridad.
