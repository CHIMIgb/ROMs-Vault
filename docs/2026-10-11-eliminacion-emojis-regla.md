# Eliminación de emojis y regla "No emojis"

**Fecha:** 2026-10-11 · **Rama:** `pendientes-7.2-slugs-y-4.4`

## 1. Objetivo

Eliminar todos los emojis de la aplicación y del repositorio, y fijar la regla
"No emojis" en `AGENTS.md` con un guard automatizado que evite regresiones.

Origen: un emoji pictográfico (`U+1F3E0`, "house") aparecía como prefijo del
texto `errors.ir_catalogo_icono` en los 9 diccionarios i18n.

## 2. Decisiones (aprobadas por el usuario)

- **Se eliminan** los emojis pictográficos y con presentación de emoji
  (p. ej. `U+1F3E0`, `U+1F4C1`, `U+2B07`, `U+2705`, `U+274C`, `U+26A0 U+FE0F`).
- **Se conservan** (no son emojis): flechas tipográficas (flecha derecha,
  izquierda, arriba y abajo), símbolos de UI/matemáticos (aspa `U+2715`/`U+2716`
  y check `U+2713`/`U+2714`) y glifos del design retro (triángulos y círculos
  `U+25B6 U+25BC U+25C0 U+25BA U+25CF`). Son iconos funcionales del proyecto
  (cerrar alerta, marcadores de acordeón, "INSERT COIN", etc.).
- **Alcance:** código de la app (`src/`, `endpoints/`, `public/`), diccionarios
  i18n, `README.md` y `docs/` (incluido `docs/_archivo/`). Fuera de alcance:
  `vendor/`, `.agents/` y `.opencode/` (terceros/config gitignored).
- En `docs/`, los emojis de estado se sustituyen por texto con el mismo
  significado: `U+2705` -> "OK", `U+274C` -> "No", `U+26A0 U+FE0F` -> "Aviso:",
  `U+1F534` -> "ALTA", `U+1F7E1` -> "MEDIA", `U+2713` -> "OK".

## 3. Cambios

- **`src/i18n/es|en|fr|pt|de|ja|it|ru|tr.json`** — `errors.ir_catalogo_icono`
  pierde el prefijo emoji (queda "Ir al catálogo", "Go to catalog", …).
- **`endpoints/ajax_admin.php`** — la cabecera de columna con `U+2B07` pasa a
  texto traducido `t('admin.juego.tabla_descargas_abrev')` (mantiene el `title`);
  el placeholder `U+1F4C1` de portada ausente pasa a un guion (sin cambio de CSS).
- **`README.md`** — se quitan los emojis decorativos de los encabezados y el
  aviso de `vercel.json`. Se conserva el check `U+2714` en el ejemplo de salida
  de `testdox` (es la salida real de PHPUnit).
- **`docs/**` y `docs/_archivo/**`** — limpieza con el mapeo semántico anterior.
- **`AGENTS.md`** — nueva regla en *Conventions*: "No emojis", con la lista de
  rangos prohibidos y los símbolos permitidos.
- **`tests/Unit/NoEmojiTest.php`** (nuevo) — guard que recorre los archivos de
  texto versionados y falla si aparece un emoji prohibido.

## 4. Verificación

- `tests/Unit/NoEmojiTest.php` en verde; además, barrido de **todos** los
  archivos versionados (salvo `vendor/`, `.agents/`, `.opencode/`) con el patrón
  del guard: **0 emojis**.
- Sanity check del patrón: detecta los pictográficos/emoji-presentation y deja
  pasar flechas, aspas y triángulos.
- `composer test`: **184 tests / 11178 assertions OK**.
- `phpstan analyse`: **`[OK] No errors`**. `php -l` OK en los ficheros tocados.
- Smoke: `/` y una ruta 404 renderizan con **0 emojis**.

## 5. Estado

- [x] Emojis eliminados del repo y regla + guard añadidos.
