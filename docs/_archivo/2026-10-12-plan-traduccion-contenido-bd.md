# Plan de implementación — Traducción del contenido de BD (i18n, bloque 2)

- **Fecha:** 2026-10-12
- **Estado:** Aprobado en enfoque — **modo automático** con Google desde el inicio.
  Arranque de implementación: 2026-10-13.
- **Rama sugerida:** `feature/i18n-traduccion-contenido-bd`
- **Origen:** bloque 2 de `docs/_archivo/2026-10-12-plan-idiomas-db-uiux-scripts.md`
- **Antecedente:** Tier 2 (zh/ko/ar + RTL) ya completado y commiteado (`e288816`, `cc5c99e`)

> ### Nota de cambio (2026-10-13) — proveedor por defecto: LibreTranslate (gratis)
> El plan se redactó asumiendo **Google Cloud Translation** desde el inicio. Al intentar crear
> la cuenta de facturación, **Google bloqueó el registro** (bloqueo antifraude `OR_BACR2_59` +
> correo "Google Cloud signup was denied"), por lo que el modo automático de Google **no es viable
> ahora**. Decisión del proyecto: usar la **opción gratuita, LibreTranslate** (auto-hospedado con
> Docker o instancia pública), **sin facturación ni tarjeta**.
>
> Clave arquitectónica que lo hace sencillo: **las traducciones se guardan en la base de datos**,
> así que el traductor solo se necesita **al traducir** (crear/editar/backfill), **no en
> producción**. En producción la app solo **lee** lo ya traducido. Por tanto basta con levantar
> LibreTranslate **localmente cuando se traduce**; nada de infraestructura extra en Vercel.
>
> - `TRANSLATE_PROVIDER` por defecto pasa a **`none`** (seguro) y se activa con
>   **`libretranslate`** cuando el servicio local está levantado.
> - El proveedor de **Google se conserva implementado** detrás de la misma interfaz
>   (`TranslationProvider`), listo para activarlo si en el futuro se resuelve el bloqueo.
> - El resto del plan (esquema, resolución, fallback, `IdiomasMap`, fases, tests) **no cambia**.

---

## 1. Resumen del requerimiento

La interfaz del sitio ya está traducida a 12 idiomas (Tier 1 + Tier 2) con fallback a `es`.
Sin embargo, **el contenido editorial que vive en la base de datos sigue siempre en español**,
incluso cuando el usuario navega con la interfaz en inglés, japonés o árabe:

- `categorias.nombre` y `categorias.descripcion` (etiquetas de filtros y fichas).
- `juegos.descripcion` (sinopsis corta en las tarjetas y en el detalle).
- `juegos.idiomas` (lista de idiomas disponibles del juego, hoy mostrada como texto crudo en español).

El objetivo de este bloque es **traducir ese contenido** para que un usuario con la interfaz en
otro idioma vea también el contenido en su idioma, manteniendo **el español como camino canónico y
fallback universal**, y sin romper la lógica interna que hoy depende de los valores en español.

Incluye, además, un mecanismo de **auto-traducción** (borrador revisable) mediante la API de
Google Cloud Translation, configurable y desactivable, para no tener que escribir a mano las
traducciones de todo el catálogo.

---

## 2. Objetivo y alcance

### Dentro de alcance
1. Modelo de datos de traducciones (tablas por entidad) con migración idempotente.
2. Resolución de contenido traducido en la lectura pública, con **fallback en cascada**
   (`idioma activo → es → vacío`) y **consulta batch** (sin N+1).
3. **Auto-traducción con Google Cloud Translation activada por defecto** (modo automático):
   al guardar contenido nuevo o editado se generan las traducciones de los idiomas destino
   sin intervención manual. Es el **mecanismo central** del bloque.
4. Backfill inicial del catálogo existente (traducción automática de una pasada).
5. Mapa determinista para `juegos.idiomas` (sin coste de API).
6. Revisión/override manual en el admin (recomendado, diferible): corregir una traducción
   concreta sin que la siguiente pasada automática la pise.
7. Tests (unit + integración) y documentación (doc de tarea + roadmap).

### Fuera de alcance (explícito)
- Traducción de **nombres propios**: `juegos.titulo`, `consolas.nombre`, `consolas.descripcion`,
  `fabricante`, `region`, `emuladores.nombre`.
- Traducir los datos en el panel de administración (el admin sigue trabajando en `es`).
- Búsqueda u ordenamiento sobre textos traducidos (se mantiene sobre el canónico `es`).
- Traducción de contenido generado por usuarios (no existe tal contenido hoy).
- Migración a v3 "Advanced" de Google (la abstracción del proveedor lo permitirá después).

### 2.1 Prerrequisitos del modo automático (a tener listos antes de empezar)
> **Camino crítico (revisado 2026-10-13):** el proveedor por defecto pasa a **LibreTranslate**,
> **gratis y sin facturación**. Google queda como proveedor alternativo detrás de la misma
> interfaz, pero **no es necesario** para arrancar.

**LibreTranslate (por defecto, gratis):**
1. **Docker** disponible en la máquina del autor (ya se usa en el proyecto).
2. Levantar el servicio cuando se vaya a traducir:
   ```
   docker run -ti --rm -p 5000:5000 \
     -e LT_LOAD_ONLY=es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar \
     libretranslate/libretranslate
   ```
3. En `.env`: `TRANSLATE_PROVIDER=libretranslate` y `TRANSLATE_LIBRE_URL=http://localhost:5000`.
4. **No** se necesita key ni facturación. Si el servicio no está arriba, el guardado **no se
   bloquea**: se registra el error y el contenido queda en `es` hasta que se traduzca.

**Google Cloud Translation (alternativo, opcional):** requiere cuenta de facturación activa
(bloqueada para este proyecto el 2026-10-13 por el filtro antifraude de Google). El código
`GoogleTranslateProvider` queda listo para activarse con `TRANSLATE_PROVIDER=google` +
`TRANSLATE_API_KEY` cuando se resuelva el bloqueo.

---

## 3. Punto de partida (estado actual)

| Pieza | Detalle |
|---|---|
| i18n UI | `src/config/i18n.php` (`I18n::LANGUAGES`, `RTL_LANGS`, `DEFAULT_LANG`, `t()`), diccionarios `src/i18n/{es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar}.json` (592 claves paritarias) |
| Intl | `src/config/IntlHelper.php` (fechas/números por locale, `zh_CN`/`ko_KR`/`ar_AR`) |
| RTL | `[dir="rtl"]` desde `header.php`; CSS con logical properties |
| Contenido BD | Tablas `juegos`, `consolas`, `categorias`, `emuladores`; columnas de texto en `es` |
| Filtros | `HomeController::index()` carga `$consolas = $consolaModel->all()` y `$categorias = $categoriaModel->all()`; los nombres de consola/categoría de cada juego llegan por JOIN como `consola_nombre` / `categoria_nombre` |
| Ficha | `HomeController::show()` usa `Juego::findWithDetails($id)`; `show.php` parsea `juegos.idiomas` (split por `[,.;]`, mayúscula inicial, dedupe) |
| Modelo base | `src/models/Model.php` (PDO crudo, `create/update/delete` con `WHERE id = ?` y `sanitizeColumns()` que solo valida el nombre por regex) |
| Routing | `index.php` resuelve `/controller/action/id` → `src/controllers/{Ucfirst}Controller.php`; CSRF en **todo** POST; `endpoints/` para entrypoints sueltos |
| Migraciones | `data/migrations/*.sql` idempotentes, ya volcadas al esquema base |

> Nota: el encabezado de `src/config/i18n.php` (líneas 13-14) afirma hoy que "los datos de la BD
> NO se traducen". **Hay que actualizar ese comentario** en este bloque.

---

## 4. Decisiones de diseño

### 4.1 Camino canónico y fallback
- El **español (`es`) de las tablas base es canónico**: se sigue usando como fuente de verdad,
  para la lógica interna, y como fallback.
- Las tablas de traducción **solo almacenan idiomas distintos de `es`**.
- Cascada de resolución: `traducción del idioma activo → columna es de la tabla base → vacío`.

### 4.2 Qué se traduce y qué no

| Campo | ¿Traducir? | Motivo |
|---|---|---|
| `juegos.descripcion` | **Sí** | Sinopsis editorial |
| `categorias.nombre` | **Sí** | Etiqueta editorial ("Acción" → "Action") |
| `categorias.descripcion` | **Sí** | Texto editorial corto |
| `juegos.idiomas` | **Sí (por mapa)** | No por API; mapa determinista (ver 4.6) |
| `juegos.titulo` | No | Nombre propio |
| `consolas.nombre` | No | Nombre propio ("PSP", "Gamecube") |
| `consolas.descripcion` | **No** | Es el **nombre largo de la plataforma**, no una descripción: `PSP → "PlayStation Portable"`, `Gamecube → "Nintendo Gamecube"`. Es nombre propio |
| `consolas.fabricante` | No | Nombre propio ("Sony", "Nintendo") |
| `region` | No | Código (`PAL`, `NTSC`, `NTSC-J`, `NTSC-U`) |
| `emuladores.nombre` | No | Nombre propio del software |

> Consecuencia: **no se crea** tabla `consolas_traducciones`. Solo hay dos tablas de traducción.

### 4.3 Modelo de datos: Opción A (tablas por entidad)
Se elige **una tabla de traducciones por entidad traducible** (diseño tipado, indexable por PK compuesta).

Alternativas descartadas:
- **B) JSONB** (`traducciones JSONB` en cada tabla): sin integridad referencial por idioma,
  incómodo para consultar/validar y para un panel de edición por idioma.
- **C) Columnas por idioma** (`descripcion_en`, `descripcion_ja`, ...): explosión de columnas,
  rígido ante altas/bajas de idiomas.
- **D) No traducir**: descartado, es el objetivo del bloque.

### 4.4 Resolución: dónde y cómo
- La resolución vive en un **servicio** (`TraduccionService`), **nunca en la vista**.
- **Batch obligatorio**: para un listado se recogen todos los ids y se hace **una** consulta
  `WHERE entidad_id IN (...) AND idioma = :lang`. Nada de consultar por fila (anti-N+1).
- **Caché en memoria por request** (`entidad + idioma` → filas).
- Se resuelve tras la consulta, en la capa de datos/servicio orquestada por el controlador
  público. El panel de admin **no** resuelve (trabaja en `es`).

### 4.5 Campos clave que NO cambian de semántica
- **Búsqueda y orden** siguen sobre el canónico `es` (los filtros de catálogo usan `id`/`código`,
  no el nombre). Documentado como comportamiento aceptado.
- **Slugs** (`/consolas/{slug}`, `/juegos/{slug}`) **quedan fijos en `es`** (estabilidad de URLs).
- **Lógica de familias** (`Juego::FAMILIAS_GENERO`, `categoriasRelacionadas()`): depende del
  `categorias.nombre` canónico. Se traduce solo en presentación; la lógica sigue comparando el `es`.

### 4.6 `juegos.idiomas`: mapa determinista (sin API)
El campo guarda una lista separada por comas (y en datos viejos por puntos) de **idiomas escritos
en español, con erratas**: p. ej. `"Español, Inglés, Frances, Alemán, italiano"`.
Se sustituye el render crudo por un **mapa de normalización** (`IdiomasMap`):
1. Parsear con el mismo split que hoy (`[,.;]`), recortar y deduplicar.
2. Normalizar el token (minúsculas + quitar acentos + transliterar).
3. Buscar en el mapa → código canónico → `t('data.language.{code}')`.
4. **Caso especial "(parche)"** (Opción B aprobada): si el token contiene `(parche)`, se renderiza
   localizado como `t('data.language.{code}') + ' (' + t('data.language.patch') + ')'`
   (p. ej. en inglés "Spanish (patch)").
5. **Token desconocido → se muestra tal cual** (mayúscula inicial), como hoy.

Tokens reales detectados en el dump (**21 distintos, 122 filas**):

| Token(s) en BD | Código | Nota |
|---|---|---|
| `Español` | `es` | |
| `Inglés`, `Ingles` | `en` | variantes |
| `Frances`, `Francés` | `fr` | variantes |
| `Alemán`, `Aleman` | `de` | variantes |
| `Italiano`, `italiano` | `it` | variantes |
| `Japones`, `Japonese`, `Japoneses` | `ja` | variantes |
| `Ruso` | `ru` | |
| `Holandés` | `nl` | |
| `Sueco` | `sv` | |
| `Danés` | `da` | |
| `Finlandés` | `fi` | |
| `Noruego` | `no` | |
| `Español (parche)` | `es` + marca | Opción B: "es (parche)" localizado con `data.language.patch` |
| `NULL`, `11` | — | basura: fallback a texto crudo |

Conjunto canónico del mapa: **es, en, fr, de, it, ja, ru, nl, sv, da, fi, no**.

### 4.7 Auto-traducción

> **Modo por defecto del bloque: AUTOMÁTICO.** Al crear o editar contenido, la traducción a los
> idiomas destino se genera sola. El proveedor por defecto es **Google**. La revisión manual
> existe como override, no como paso obligatorio.

**Proveedor por defecto:** **LibreTranslate** (gratis, sin facturación; auto-hospedado o instancia
pública). **Alternativo:** **Google Cloud Translation**, ya implementado pero inactivo.

- **Auth:** LibreTranslate no requiere key (opcional si el servicio la exige). Google usaría
  `TRANSLATE_API_KEY` (API Basic v2) — no necesario para arrancar.
- **Feature flag:** `TRANSLATE_PROVIDER=libretranslate|google|none`. Por defecto **`none`** (seguro:
  se activa cuando el servicio local está arriba); con `libretranslate` y URL válida, la
  auto-traducción queda activa. Si el servicio falla, se degrada con seguridad y todo cae a `es`.

**Arquitectura (interfaz + adaptadores):**
- `TranslationProvider` (interfaz): `translateBatch(array $textos, string $source, string $target): array`
  y `isConfigured(): bool` (una llamada por par origen→destino; el servicio itera los targets).
- `AbstractTranslateProvider` (POST JSON común), `LibreTranslateProvider` (defecto gratuito),
  `GoogleTranslateProvider` (alternativo), `NullTranslateProvider` (no-op, sin proveedor),
  `FakeTranslateProvider` (tests, determinista, sin red).
- `TranslateProviderFactory::fromEnv()` lee `.env` y construye el proveedor.

**Política de datos traducidos:**
- Cada fila de traducción lleva `origen` (`auto` | `manual`) y `revisado` (bool).
- **Generación automática al guardar:** crear/editar contenido dispara la traducción de los
  campos que falten o cuyo `es` haya cambiado. No se re-traduce de forma implícita en cada vista.
- La auto-traducción **nunca pisa** una corrección manual: solo rellena vacíos o refresca filas
  con `origen = auto` cuyo texto canónico `es` cambió (detectado por `origen_hash`).
- Botones explícitos complementarios: **"Re-traducir"** (por fila) y **"Traducir pendientes"**
  (backfill global del catálogo).
- **Incremental:** se traduce solo lo que cambia (comparando el hash del `es`), no todo el catálogo.
- **Degradación elegante:** si no hay proveedor/API key o la llamada falla, el guardado del
  contenido **no se bloquea**; se registra y el contenido queda en `es` hasta que haya traducción.
- **Timeout** y `try/catch` en la llamada; nunca romper la petición del admin.
- **Rate limit** del endpoint de re-traducción (evita abuso y gasto).

**Mapeo de códigos app → Google (BCP-47):** `es→es, en→en, fr→fr, pt→pt, de→de, ja→ja, it→it,
ru→ru, tr→tr, zh→zh-CN, ko→ko, ar→ar`.

**Seguridad:** la API key es **solo servidor** (`.env`, nunca al frontend); el endpoint de
re-traducción es **solo admin** y exige **CSRF**.

### 4.8 Idiomas: UI (12) vs contenido (configurable)
- **Nivel UI (12):** `es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar` — completo (ya lo está).
- **Nivel contenido (configurable):** `TRANSLATE_TARGETS` (lista separada por comas) fija a qué
  idiomas se auto-traduce el contenido. Por defecto, **los 11 distintos de `es`**.
- Si algún día el catálogo crece y el coste aprieta, se recorta `TRANSLATE_TARGETS` sin tocar
  código; los idiomas que salgan siguen cubiertos por el **fallback a `es`**.

---

## 5. Análisis de volumen y coste (datos reales medidos)

Medición sobre `data/import_data.sql` (1 INSERT por línea, 122 juegos / 16 consolas / 39 categorías):

| Tabla | Filas | Caracteres reales (es) | Mediana | Máx |
|---|---|---|---|---|
| `juegos.descripcion` | 122 | 3.116 | 25 | 65 |
| `categorias` (nombre + descripcion) | 39 | 1.334 | 13 | 49 |
| `consolas.descripcion` | 16 | 234 | 13 | 34 |
| **Origen traducible** | | **≈ 4.450** (sin consolas) | | |

Google Cloud Translation:
- **Free tier: 500.000 caracteres/mes** (aplicado como crédito equivalente a ~10 USD).
- **Requiere cuenta de facturación + tarjeta**; sin billing la API devuelve 403.
- Exceso: ~20 USD por millón de caracteres.
- **Se factura por carácter de origen por cada idioma destino** (se envía el texto `es`
  una vez por target). Fórmula: `caracteres_facturados = chars_origen × N_targets`,
  por lo que `N_targets ≤ 500.000 / chars_origen`.

| Idiomas destino | Caracteres (1 pasada completa) | % del tier gratis |
|---|---|---|
| 5 | 22.250 | 4,5% |
| 8 | 35.600 | 7,1% |
| **11 (todos)** | **≈ 48.950** | **9,8%** |

**Conclusión:** con el catálogo actual **no hace falta recortar idiomas**; los 11 caben en
menos del 10% del tier gratis. Margen de crecimiento: hasta ~45.454 caracteres de origen
(**~10x el catálogo actual**, ~1.600 fichas más) antes de rozar el límite mensual.

Referencias de otros proveedores (para contexto, no elegidos):
- **DeepL API Free** ya no se puede contratar para cuentas nuevas (2026) → descartado.
- **LibreTranslate** self-hosted: gratis pero exige un servicio Python aparte (incómodo en Vercel).
- **MyMemory**: gratis con límites diarios bajos.

---

## 6. Impacto Backend

### 6.1 Esquema / migración — `data/migrations/2026-10-12-traducciones-contenido.sql`
Idempotente, no destructiva. Se aplica también en `roms-vault-test`, `roms-vault` (dev) y **Neon**.

```sql
-- =====================================================
-- Migración: Traducciones de contenido (2026-10-12)
-- PostgreSQL — idempotente (IF NOT EXISTS), no destructiva.
--
-- Tablas de traducción del contenido editorial que se muestra
-- en el catálogo público. El 'es' canónico vive en las tablas base;
-- aquí solo se guardan idiomas distintos de 'es'.
-- =====================================================

CREATE TABLE IF NOT EXISTS public.categorias_traducciones (
    categoria_id   INTEGER      NOT NULL REFERENCES public.categorias(id) ON DELETE CASCADE,
    idioma         VARCHAR(5)   NOT NULL,
    nombre         VARCHAR(120),
    descripcion    TEXT,
    origen         VARCHAR(10)  NOT NULL DEFAULT 'manual',
    revisado       BOOLEAN      NOT NULL DEFAULT FALSE,
    origen_hash    CHAR(64),                       -- sha256 del 'es' al traducir (detección de cambios)
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (categoria_id, idioma),
    CONSTRAINT chk_categorias_trad_idioma CHECK (idioma <> 'es'),
    CONSTRAINT chk_categorias_trad_origen CHECK (origen IN ('auto', 'manual'))
);

CREATE TABLE IF NOT EXISTS public.juegos_traducciones (
    juego_id       INTEGER      NOT NULL REFERENCES public.juegos(id) ON DELETE CASCADE,
    idioma         VARCHAR(5)   NOT NULL,
    descripcion    TEXT,
    origen         VARCHAR(10)  NOT NULL DEFAULT 'manual',
    revisado       BOOLEAN      NOT NULL DEFAULT FALSE,
    origen_hash    CHAR(64),
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (juego_id, idioma),
    CONSTRAINT chk_juegos_trad_idioma CHECK (idioma <> 'es'),
    CONSTRAINT chk_juegos_trad_origen CHECK (origen IN ('auto', 'manual'))
);

CREATE INDEX IF NOT EXISTS idx_categorias_trad_idioma ON public.categorias_traducciones(idioma);
CREATE INDEX IF NOT EXISTS idx_juegos_trad_idioma     ON public.juegos_traducciones(idioma);
```

> Sin triggers (idempotencia simple): el `updated_at` lo setea la app en el `UPSERT`
> (`ON CONFLICT ... DO UPDATE SET ..., updated_at = now()`). Convención de columnas
> `created_at`/`updated_at` como el resto del esquema.

### 6.2 Configuración — `.env` / `.env.example`
```
# Traducción automática de contenido (bloque 2 i18n)
TRANSLATE_PROVIDER=none          # none | libretranslate | google  (libretranslate = gratis, por defecto al traducir)
TRANSLATE_LIBRE_URL=http://localhost:5000   # URL del servicio LibreTranslate
TRANSLATE_API_KEY=               # opcional (LibreTranslate con auth) o Google (v2 Basic)
TRANSLATE_TARGETS=               # vacío = todos los idiomas de I18n::LANGUAGES menos 'es'
TRANSLATE_TIMEOUT=8              # segundos
```
- `Docker` entrypoint debe propagar estas variables (patrón existente de regeneración de `.env`).
- **Nunca** commitear keys; `.env*` ya está en `.gitignore`.

### 6.3 Modelos
- **Nuevo `src/models/Traduccion.php`** (PDO crudo):
  - `getCategorias(array $ids, string $idioma): array` (indexado por `categoria_id`).
  - `getJuegos(array $ids, string $idioma): array`.
  - `upsertCategoria(int $id, string $idioma, ?string $nombre, ?string $desc, string $origen, bool $revisado, ?string $hash): void`
    (`INSERT ... ON CONFLICT (categoria_id, idioma) DO UPDATE`).
  - `upsertJuego(...)` análogo.
  - `allDeIdioma(string $idioma)` y `faltantes()` (para el backfill / contadores).
  - **No** puede usar `Model::update()` (usa `WHERE id = ?`, incompatible con PK compuesta):
    el upsert se implementa con `ON CONFLICT`.
- **`src/models/Juego.php` / `Categoria.php` / `Consola.php`**: sin cambios de esquema; la
  traducción se aplica **después** de leer (ver 6.4), no dentro de las consultas.

### 6.4 Servicio de traducción y proveedores
- **Nuevo `src/config/TraduccionService.php`** (`class TraduccionService`, estático):
  - `resolverCategorias(array &$rows, ?string $idioma = null): void` — reemplaza `nombre`/`descripcion`.
  - `resolverJuegos(array &$rows, ?string $idioma = null): void` — reemplaza `descripcion`.
  - `resolverCategoriasVinculadas(array &$juegos, ?string $idioma = null): void` — reemplaza
    `categoria_nombre` (JOIN) usando `categoria_id`.
  - `traducirPendientes(string $entidad, ?array $ids = null, ?array $targets = null): array`
    — orquesta la auto-traducción (usa el proveedor configurado), incremental por `origen_hash`.
  - Caché en memoria por request; usa `I18n::getLang()` y `I18n::LANGUAGES`.
  - Sigue la convención de `require_once` explícito (no hay PSR-4 para el código de app).
- **Nuevas `src/config/translation/`**:
  - `TranslationProvider.php` (interfaz).
  - `AbstractTranslateProvider.php` (POST JSON común con cURL; separa vacíos y reasigna por orden).
  - `LibreTranslateProvider.php` (**proveedor por defecto, gratis**; `POST {url}/translate`).
  - `GoogleTranslateProvider.php` (alternativo; `POST .../language/translate/v2`, mapea `zh→zh-CN`).
  - `NullTranslateProvider.php` (no-op, `isConfigured() = false`).
  - `TranslateProviderFactory.php` (`fromEnv()`; degrada a Null si falta config).
  - **Sin dependencia Composer**: se usa cURL directo (menos peso, sin `google/cloud-translate`).

### 6.5 Mapa de idiomas de juegos
- **Nuevo `src/config/IdiomasMap.php`** (`class IdiomasMap`):
  - `render(?string $raw): string` — parsea, normaliza (minúsculas + sin acentos), mapea a
    `t('data.language.{code}')`, deduplica y une; tokens desconocidos se muestran crudos.
  - `const MAP` con las variantes reales detectadas (sección 4.6).
- Sustituye el parseo actual en `src/views/home/show.php` (líneas 23-34) y el render crudo de
  `src/views/home/index.php` (línea ~111).

### 6.6 Controladores de admin (enganches)
- **`src/controllers/CategoriaController.php`**: en `add()` y `edit()` (tras `create/update`
  exitoso), guardar las traducciones enviadas por el formulario vía `Traduccion`; opcionalmente,
  disparar auto-traducción para los campos vacíos (no bloqueante).
- **`src/controllers/AdminController.php`**: en `add()` (~línea 137) y `edit()` (~línea 297),
  guardar `juegos_traducciones.descripcion` igual que arriba.
- **`src/controllers/ConsolaController.php`**: **sin cambios** (consolas no se traducen).
- **Nuevo `src/controllers/TraduccionController.php`** (`AuthMiddleware::requireAdmin()` en el
  constructor; CSRF global ya cubre todo POST):
  - `retraducir($id)` — AJAX: re-traduce una entidad/idioma concreto; devuelve JSON con el texto.
  - `pendientes()` — AJAX: traduce todos los pendientes de una entidad (respetando `TRANSLATE_TARGETS`).
  - Se enruta por `index.php` como `/traduccion/retraducir/{id}` → **no** requiere tocar
    `router.php` ni `.htaccess` ni añadir endpoints sueltos.

### 6.7 Carga de contenido público (lectura)
En los controladores públicos, tras obtener filas y **antes** de renderizar:
- `HomeController::index()`: `resolverCategorias($categorias)`, `resolverJuegos($juegos)`,
  `resolverCategoriasVinculadas($juegos)`.
- `HomeController::show()`: `resolverJuegos([$juego])`, `resolverCategoriasVinculadas([$juego])`.
- `HomeController::play()` / `catalogByConsola()` (reusa `index()`): igual que `index()`.

---

## 7. Impacto Frontend

### 7.1 Vistas públicas
- `src/views/home/index.php`: los nombres de categoría/consola de las tarjetas y los filtros
  (`$categorias`) llegan ya traducidos desde el controlador (no se toca la vista salvo el
  render de `idiomas`, que pasa a `IdiomasMap::render(...)`).
- `src/views/home/show.php`: reemplazar el bloque de parseo de `idiomas` (líneas 23-34) por
  `IdiomasMap::render($juego['idiomas'])`; la descripción y el nombre de categoría ya llegan traducidos.
- Todos los textos siguen escapados con `htmlspecialchars()` (las traducciones son entrada
  externa, aunque vengan de la API).

### 7.2 Vistas de admin (panel de traducciones)
- `src/views/admin/categorias/edit.php` y `src/views/admin/categorias/add.php`: sección
  **"Traducciones"** (plegable) con una fila por idioma destino: `nombre` y `descripcion`,
  badge de origen (`auto`/`manual`/`vacío`), y botón **Re-traducir** por fila.
- `src/views/admin/juegos/edit.php` (y `add.php`): sección análoga con `descripcion`.
- Reutilizar los estilos de `public/css/modules/*` (nuevo módulo `translations.css` importado por
  `public/css/style.css`). El JS del botón Re-traducir usa el patrón `fetch()` + `X-CSRF` ya
  existente (como los `toggleActiveAjax`).

### 7.3 Diccionarios i18n (nuevas claves, **en los 12 diccionarios**)
- `data.language.{es,en,fr,de,it,ja,ru,nl,sv,da,fi,no}` (12 claves) — nombre del idioma **en el
  idioma del diccionario** (p. ej. `en.json` → `data.language.en = "English"`). Nota: `nl/sv/da/fi/no`
  no son idiomas de UI, solo aparecen como chips (decisión 4 confirmada).
- `data.language.patch` (1 clave) — marca "parche" para el caso especial `Español (parche)`
  (decisión 5, Opción B).
- `admin.traduccion.*` (~12 claves): `titulo`, `subtitulo`, `origen_auto`, `origen_manual`,
  `origen_vacio`, `retraducir`, `retraducir_todo`, `confirmar_retraducir`, `no_configurado`,
  `error`, `guardado`, `pendiente`.
- **Paridad obligatoria** en los 12 diccionarios (lo verifica `I18nTest`). Total ≈ **25 claves × 12 = 300 cadenas**.

---

## 8. Archivos a crear / modificar

**Crear**
- `data/migrations/2026-10-12-traducciones-contenido.sql`
- `src/models/Traduccion.php`
- `src/config/TraduccionService.php`
- `src/config/translation/TranslationProvider.php`
- `src/config/translation/GoogleTranslateProvider.php`
- `src/config/translation/NullTranslateProvider.php`
- `src/config/translation/LibreTranslateProvider.php` (opcional)
- `src/config/translation/TranslateProviderFactory.php`
- `src/config/IdiomasMap.php`
- `src/controllers/TraduccionController.php`
- `public/css/modules/translations.css`
- `tests/Unit/TraduccionServiceTest.php`
- `tests/Unit/IdiomasMapTest.php`
- `tests/Integration/TraduccionFlowTest.php`
- `docs/2026-10-12-traduccion-contenido-bd.md` (doc de tarea, al cerrar)

**Modificar**
- `data/roms-vaultDB-postgreSQL.sql` (consolidar las 2 tablas nuevas)
- `data/test_seeds.sql` (filas de traducción de muestra para tests)
- `.env.example` (vars `TRANSLATE_*`)
- `composer.json` / `composer.lock` (dep `google/cloud-translate` — Fase 5)
- `src/config/i18n.php` (actualizar el comentario "los datos de la BD no se traducen")
- `src/controllers/HomeController.php` (resolución en `index`/`show`/`play`)
- `src/controllers/CategoriaController.php`, `src/controllers/AdminController.php` (guardar traducciones)
- `src/views/home/index.php`, `src/views/home/show.php`
- `src/views/admin/categorias/{add,edit}.php`, `src/views/admin/juegos/{add,edit}.php`
- 12 × `src/i18n/*.json` (claves nuevas)
- `public/css/style.css` (importar el módulo nuevo)
- `docs/2026-08-05-plan-mejoras-nivel-senior.md` (registro de progreso, en el mismo commit)
- `docs/_archivo/2026-10-12-plan-idiomas-db-uiux-scripts.md` (marcar bloque 2)

---

## 9. Fases de ejecución

| Fase | Entregable | Verificación |
|---|---|---|
| **0. Aprobación + prerrequisitos** | Plan aprobado + **LibreTranslate** levanta en local (Docker) y responde `/translate` (sección 2.1) | Una llamada de prueba a LibreTranslate responde OK |
| **1. Infra + LibreTranslate (auto por defecto)** | Migración + `Traduccion` + `TraduccionService` + `LibreTranslateProvider`/`GoogleProvider`/`NullProvider`/`Factory` + `.env` + resolución en `HomeController` + **auto-traducción al crear/editar categorías y juegos** | Test con `FakeTranslateProvider`; con LibreTranslate arriba, guardar una categoría/juego genera sus traducciones; degradación sin servicio |
| **2. Idiomas de juegos** | `IdiomasMap` + claves `data.language.*` + render en index/show | Test unit del mapa (variantes, desconocidos); chips correctos por idioma |
| **3. Backfill inicial** | `TraduccionController::pendientes` + acción/botón global → traduce todo el catálogo de una pasada | Catálogo completo en los targets; coste dentro del tier (~10%) |
| **4. Revisión/override (recomendado, diferible)** | Panel admin de traducciones + botón "Re-traducir" + `origen=manual` (no lo pisa el auto) | Test integración: corrección manual sobrevive a una re-traducción |
| **5. Cierre** | Doc de tarea + roadmap + bloque 2 en `_archivo` | Todo verde; registro actualizado |

---

## 10. Tests

**Unit**
- `TraduccionServiceTest`: cascada `idioma → es → vacío`; resolución batch por ids;
  no reemplaza cuando no hay traducción; no toca `consolas`.
- `IdiomasMapTest`: variantes (`Ingles/Inglés`, `Frances/Francés`, `Aleman/Alemán`), dedupe,
  token desconocido (`NULL`, `11`, `Español (parche)`) → texto crudo; resultado por idioma de UI.
- Rate limit del endpoint de re-traducción.

**Integración** (con `data/test_seeds.sql` + `IntegrationTestCase::resetDatabase()`)
- `?lang=en` (cookie `rv_lang`) → categoría y descripción de juego traducidas en Home y Show.
- Fallback: idioma sin traducción → muestra el `es`.
- Admin (`edit`) guarda traducción (`origen=manual`) y `origen_hash`.
- CSRF: POST sin token → denegado; con token → OK.
- `NoEmoji` sigue verde con las claves nuevas.

**Suites**: `composer test` (Unit + Integration + Security). Requiere `TEST_DB_PASSWORD` para integración.

---

## 11. Verificación manual (checklist)

- [ ] Migración idempotente: aplicarla **dos veces** sin error en dev, test y Neon.
- [ ] `docker build` + `docker run` levanta con las nuevas vars de `.env`.
- [ ] Smoke con `php -S localhost:8000 router.php`: `?lang=en`, `?lang=ja`, `?lang=ar` muestran
      contenido traducido (con datos sembrados) y `dir="rtl"` en árabe.
- [ ] `/src/*` sigue devolviendo 403; `vercel.json` mantiene `"rewrites": []`.
- [ ] URLs limpias y legacy (`index.php?controller=...`) siguen funcionando.
- [ ] Sin key de Google: guardar/editar contenido funciona y cae a `es` (no bloquea).
- [ ] Con key: "Re-traducir" y "Traducir pendientes" rellenan y respetan `origen=manual`.
- [ ] Paginación sigue en 20 (4x5).
- [ ] Sin `git push`; commits en español; LF; sin emojis.

---

## 12. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Gasto inesperado en un proveedor de pago | LibreTranslate es gratis; solo se traduce al guardar/re-traducir (nunca en vista); `TRANSLATE_TARGETS` recortable; incremental por hash |
| Google bloqueó el alta (bloqueo antifraude) | Proveedor por defecto **LibreTranslate** (gratis, sin tarjeta); Google queda implementado pero inactivo |
| Deprecación/cambio de proveedor | Interfaz `TranslationProvider` abstrae el proveedor (LibreTranslate/Google/otro) |
| N+1 en listados | Resolución **batch** por `IN (...)` + caché por request |
| Romper la lógica de familias | Se traduce solo en presentación; la lógica usa el `es` canónico |
| Búsqueda incoherente con la UI | Documentado: búsqueda/orden sobre `es` (aceptado en este bloque) |
| Datos sucios (`psp 3` sin descripción, `idiomas='NULL'/'11'`) | Fallback a texto crudo; no se traducen tokens basura |
| Traducción pisa una corrección humana | `origen=manual` nunca se sobrescribe; re-traducción solo si `auto` y cambió el `es` |
| RTL en contenido traducido | `dir` global por `<html>` ya aplica; chips se renderizan en el idioma de UI |
| Fuga de la API key | Key solo en `.env` (servidor); endpoint solo admin + CSRF |
| Paridad de diccionarios | `I18nTest` la verifica en las 12 lenguas |
| Migración no aplicada en Neon | Checklist de verificación en los 3 entornos |

---

## 13. Rollback

- **Sin datos:** `TRANSLATE_PROVIDER=none` desactiva la auto-traducción sin tocar el esquema.
- **Con esquema:** `DROP TABLE juegos_traducciones, categorias_traducciones;` (previa copia).
  La app **cae automáticamente a `es`** (degradación segura), por lo que el rollback no rompe nada.
- Revertir las modificaciones de controladores/vistas/diccionarios deja el estado previo intacto.

---

## 14. Preguntas abiertas / pendientes de decisión

1. **RESUELTO — Auth del proveedor:** LibreTranslate no requiere key (opcional si su instancia la
   exige); Google usaría API key v2 Basic. La abstracción permite cambiar de proveedor sin tocar el resto.
2. **RESUELTO (revisado 2026-10-13) — Modo de operación:** automático al traducir, con
   **LibreTranslate** (gratis) como proveedor por defecto. Google quedó bloqueado por su filtro
   antifraude. El backfill inicial (Fase 3) traduce todo el catálogo de una pasada.
3. **RESUELTO — `revisado`:** se publica la traducción `auto` directamente y se puede revisar
   después (paso opcional, Fase 4). El override `manual` nunca se sobrescribe.
4. **RESUELTO — Idiomas del contenido no-UI** (`nl/sv/da/fi/no`): confirmados **solo como chips**
   de idioma del juego. NO se crean diccionarios de interfaz para ellos; solo se agregan sus
   claves `data.language.*` en los 12 diccionarios existentes.
5. **RESUELTO — `Español (parche)` (Opción B):** caso especial. `IdiomasMap` detecta el sufijo
   "(parche)" y renderiza localizado `data.language.es` + `data.language.patch`
   (p. ej. "Spanish (patch)").

---

## 15. Registro / follow-ups

- Al cerrar cada fase: tickear la casilla correspondiente y anotar el hash del commit en
  `docs/2026-08-05-plan-mejoras-nivel-senior.md` (misma confirmación/commit).
- Doc de tarea final: `docs/2026-10-12-traduccion-contenido-bd.md`.
- Marcar el bloque 2 como completado en `docs/_archivo/2026-10-12-plan-idiomas-db-uiux-scripts.md`.
