# Backfill de `juegos_traducciones.idiomas` (híbrido con delimitador)

**Fecha:** 2026-10-13
**Ámbito:** i18n (bloque 2), contenido de BD — `juegos_traducciones.idiomas`
**Estado:** implementado, backfill ejecutado y verificado

## 1. Objetivo

Rellenar la columna `juegos_traducciones.idiomas` (listas de idiomas disponibles
por juego) en los 11 idiomas destino **sin re-traducir** la `descripcion` ya
guardada, y con **calidad correcta** de los nombres de idioma.

Datos de partida en la BD local: 123 juegos, `juegos_traducciones` con 1353 filas
(123 × 11) todas con `descripcion`, y `idiomas` a `NULL`. Se esperaban 121 juegos
con idiomas (2 sin idiomas) → **1331 filas** a rellenar.

## 2. Decisión de diseño: delimitador `" / "` (enfoque híbrido)

Se probaron tres estrategias contra el proveedor de traducción:

| Estrategia | Calidad en ja/zh/ar/ko | Chips separados |
|---|---|---|
| Traducir cada idioma **aislado** | Mala (`Inglés`→`日本語 English`, `联合国`, `ألمانيا`) | Sí |
| Traducir la **lista completa** (una cadena) | Perfecta | No: el separador propio rompe el split (`、` en ja/zh, `و` en ar) |
| **Híbrido:** lista unida por `" / "` | **Perfecta** | **Sí** |

El enfoque elegido envía **una sola cadena** con los idiomas unidos por `" / "`
(el proveedor traduce con **contexto** y, verificado, **conserva el `/`** en los
11 idiomas), y al volver divide por `/` y re-une con `", "` para que las vistas
pinten los chips separados en todos los idiomas.

Evidencia (5 idiomas de origen, `es → destino`, 5/5 partes y todas correctas):

- `ja`: スペイン語/英語/フランス語/ドイツ語/イタリア語
- `zh`: 西班牙语/英语/法语/德语/意大利语
- `ar`: الإسبانية/الإنكليزية/الفرنسية/الألمانية/الإيطالية
- `ko`: 스페인어/영어/프랑스어/독일어/이탈리아어

Descartados: separar por saltos de línea (el proveedor traduce cada línea sin
contexto: ja seguía mal, ko devolvía basura) y el delimitador `" - "` (perdía el
primer ítem en varios idiomas).

## 3. Cambios

### 3.1 Modelo

- **`src/models/Traduccion.php`** — nuevo `upsertJuegoIdiomas(int $juegoId, string
  $idioma, ?string $idiomas, string $origen='auto', bool $revisado=false,
  ?string $hash=null): bool`. UPSERT que en `ON CONFLICT` actualiza **solo**
  `idiomas`, `origen`, `revisado`, `origen_hash` y `updated_at` (la columna
  `descripcion` no aparece en el `SET`, por lo que se conserva). Respeta las
  correcciones manuales (`WHERE ... origen <> 'manual' OR EXCLUDED.origen = 'manual'`).

### 3.2 Servicio

- **`src/config/TraduccionService.php`**:
  - `traducirCampos(TranslationProvider, array, string): array` — nuevo (público,
    `@internal`, para test con proveedor falso). Envía `idiomas` como una cadena
    con `" / "`, recibe el lote, divide por `/` y re-une con `", "`.
  - `traducirIdiomasJuego(int $id, array $campos, ?array $targets=null,
    bool $dryRun=false): array` — nuevo. Re-genera **solo** `idiomas` de un juego
    (no toca `descripcion`). El `origen_hash` se calcula sobre el conjunto
    canónico completo (`descripcion` + `idiomas`) para que coincida con la vía
    unificada y las re-ejecuciones sean idempotentes.
  - `generar()` (privado, extraído de `traducirEntidad`) — núcleo compartido:
    salta lo `manual` y lo ya sincronizado (`origen='auto'` con el mismo hash),
    traduce e invoca el callback de escritura, con logs de error.
  - `limpiarCampos()` (privado) — descarta campos vacíos/no-string.
  - `normalizarListaIdiomas()` — se añade `/` a los separadores que se unifican a
    `, ` (además de `、，；،;`), como red de seguridad.

### 3.3 Script de backfill

- **`scripts/backfill-traducciones.php`**:
  - Nueva opción `--campo=idiomas` (solo aplicable a juegos; `--solo=categorias`
    produce error). Fuerza `$solo='juegos'`.
  - `$procesar` recibe un `callable $traducir`; con `--campo=idiomas` usa
    `TraduccionService::traducirIdiomasJuego()`, en caso contrario
    `traducirEntidad()` (comportamiento original intacto).

## 4. Verificación

- Sintaxis: `php -l` sobre todos los archivos nuevos/modificados — sin errores.
- **Unit:** `vendor/bin/phpunit --testsuite Unit` — **119 tests OK** (5 nuevos de
  `TraduccionServiceCamposTest`: envío en una cadena con `" / "`, re-separado por
  `, `, sin idiomas, descarte de basura, sufijo `(parche)` y normalización del
  separador ideográfico `、`). `NoEmojiTest` verde.
- **Integration:** `vendor/bin/phpunit --testsuite Integration` — **76 tests OK**
  (6 nuevos de `TraducirIdiomasJuegoTest`: preservación de `descripcion`,
  respeto a `manual`, dry-run de pendientes, salto por `origen_hash` y por
  `manual`, y `sin_texto`). Suite completa: **212 tests OK**.
- **Backfill real** (con el proveedor de traducción configurado en `.env`):
  `php scripts/backfill-traducciones.php --campo=idiomas` →
  **1301 escritas + 30 de una prueba acotada previa = 1331**, **0 errores**,
  ~7 min. Segunda ejecución (`--dry-run` y real): **0 pendientes** (idempotente).
- **Conteos BD:** `count(idiomas) = 1331`; `descripcion IS NULL/btrim='' = 0`
  (descripción intacta); 22 filas sin idiomas = 2 juegos sin idiomas (2 × 11).
- **Smoke** con `php -S localhost:8123 router.php`: `/home/show/1` con cookie
  `rv_lang` = 5 chips separados y correctos en `es`, `ja`, `zh`, `ar` y `ko`
  (el árabe queda como 5 chips pese a usar `و` como conjunción).

## 5. Activación en producción

1. Aplicar la migración `data/migrations/2026-10-13-idiomas-en-juegos-traducciones.sql`.
2. Normalizar el origen (idempotente): `php scripts/normalizar-idiomas.php`.
3. Con el proveedor configurado (`TRANSLATE_PROVIDER=deepl` + `TRANSLATE_API_KEY`),
   generar las traducciones de idiomas:
   `php scripts/backfill-traducciones.php --campo=idiomas`.
   También se mantienen al guardar cada juego desde el admin (`traducirEntidad`).

## 6. Notas

- **Gotcha del hash:** si se resetea `idiomas` a `NULL` en una fila ya sincronizada
  hay que resetear también `origen_hash`; de lo contrario el incremental la salta
  (`origen='auto'` con el mismo hash) y no se vuelve a escribir.
- Un cambio en el **método** de traducción no invalida el `origen_hash` (depende
  solo del texto de origen); para re-traducir tras un cambio de método hay que
  limpiar `origen_hash`/`idiomas` de las filas afectadas.
- `traducirCampos` se usa también en la vía unificada (`traducirEntidad`), por lo
  que la mejora de calidad aplica igualmente a las traducciones generadas desde
  el admin.
