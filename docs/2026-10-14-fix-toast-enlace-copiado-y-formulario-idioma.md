# Fix: flash del toast "Enlace copiado" y duplicado de parámetros en selector de idioma

- **Fecha:** 2026-10-14
- **Rama:** `feature/i18n-traduccion-contenido-bd`
- **HEAD previo:** `f57f20f` (sin push)
- **Plan origen:** Diagnóstico por repro con Puppeteer + Edge headless
- **Archivos afectados:** `public/js/rv-lang.js`, `src/views/layout/header.php`, `src/views/home/show.php`, `src/views/home/play.php`, `tests/Integration/Server.php`, `src/config/database.php`

---

## 1. Problema

Al cambiar de idioma (especialmente en `/juego/power-rangers-ninja-storm`), se observaba que al pulsar "Compartir" el texto del toast "Enlace copiado" parpadeaba durante el cambio de idioma, y en la ficha el enlace del selector de idioma se "copiaba" en la query (añadía `controller=home&action=showBySlug&id=...` aunque la URL era limpia).

### Causa 1 — Flash del toast (parpadeo)

`rv-lang.js` añadía la clase `.rv-lang-fade` a todos los elementos a los que aplicaba el reescritura de texto/atributos (`flashFadeTargets()`). El toast (`.share-toast`) está oculto por defecto con `opacity: 0` (`public/css/modules/components.css:692`). La animación de `.rv-lang-fade` (`rv-lang-pop`) sobrescribía ese estado oculto mientras se aplicaba el cambio de idioma, haciendo que el elemento apareciera brevemente (flash) aunque el usuario no había pulsado Compartir.

### Causa 2 — Duplicado de parámetros en el formulario de idioma

El formulario `.lang-switch` en el header construía los campos ocultos recorriendo `$_GET`. En URLs limpias generadas por el router (`/juego/power-rangers-ninja-storm`), el router inyecta en `$_GET` los parámetros de ruta (`controller`, `action`, `id`). Como resultado, el formulario incluía esos campos ocultos y, al hacer submit GET (o al conservarlos), se añadían a la query aunque ya estaban en la ruta limpia.

### Hallazgo extra — Suite no hermética

`AdminSlugTest` crea un juego vía HTTP (`POST /admin/add`). El servidor hijo de integración heredaba el `.env` real (con `TRANSLATE_PROVIDER=deepl` + clave), por lo que la auto-traducción al guardar disparaba 11 llamadas reales a DeepL. Eso hacía la suite dependiente de red/cuota (lento, con riesgo de timeout >10 s en `tests/Integration/Server.php::request()`), volviendo los tests no deterministas ("no herméticos").

---

## 2. Solución

### 2.1 `public/js/rv-lang.js` — No animar elementos invisibles

- Añadida función `isInvisible(el)`: detecta elementos sin rectángulos de layout (`getClientRects().length === 0`), con `visibility: hidden` o `opacity: 0` (usando `getComputedStyle`).
- Modificada `safeToFade(el)`: devuelve `false` si `isInvisible(el)` es verdadero. Así, el toast oculto (a la espera del click en Compartir) **no recibe** `.rv-lang-fade` al resincronizar el DOM tras el cambio de idioma.
- Comentario explicativo añadido para clarificar por qué se descartan elementos invisibles.

El resto de elementos visibles (texto, botones, enlaces, etc.) siguen recibiendo la animación sutil existente.

### 2.2 `src/views/layout/header.php` — Campos ocultos desde QUERY_STRING

En lugar de iterar `$_GET` (contaminado por el routing de URLs limpias), se lee `$_SERVER['QUERY_STRING']` directamente, se parsea con `parse_str()` y se excluye `lang`. Esto conserva:
- Filtros y parámetros reales de la query (`/?q=mario&categoria=accion`)
- Parámetros legacy (`/index.php?controller=home&action=showBySlug&id=...`)
- No añade parámetros de ruta en URLs limpias (`/juego/power-rangers-ninja-storm`)

### 2.3 Accesibilidad (opcional aplicado) — Evitar anuncio prematuro del toast

Se añade `data-lang-skip` a los toasts para que `rv-lang.js` **no sincronice su `innerHTML`/texto** durante el cambio de idioma sin recarga:
- `src/views/home/show.php:304` — `<div class="share-toast" ... data-lang-skip>`
- `src/views/home/play.php:179` — `<div id="share-toast" class="share-toast" ... data-lang-skip>`

Con esto, el texto del toast permanece en el idioma con el que se cargó la página hasta que el usuario pulse "Compartir" (momento en el que se rellena el mensaje). Esto evita que un lector de pantalla anuncie "Enlace copiado" sin que el usuario haya accionado el botón. El trade-off aceptado: el aviso no cambia de idioma en caliente si el usuario cambia de idioma justo antes de compartir (se mostrará en el idioma anterior hasta recargar/navegar).

### 2.4 `tests/Integration/Server.php` — Hermetismo de la suite

En el entorno del servidor hijo de integración se fuerzan:
```php
'TRANSLATE_PROVIDER' => 'none',
'TRANSLATE_API_KEY'  => '',
```
junto a las variables ya existentes (`DB_*`, `JWT_SECRET`, etc.). Esto evita llamadas reales a DeepL durante los tests HTTP (p.ej. `AdminSlugTest::crearJuego`), haciendo la suite determinista, rápida y sin consumo de cuota/cuello de botella de red. El comportamiento funcional del guardado sigue verificándose (slug, validaciones, redirects). No afecta a Unit ni a los tests que no hacen POST al servidor de integración.

### 2.5 `src/config/database.php` — Endurecimiento frente a sesiones contaminadas (pooler)

Tras establecer la conexión PDO (`SET NAMES 'UTF8'`) se añade:
```php
$this->pdo->exec('SET search_path TO "$user", public');
```

Motivo: el pooler de Neon reutiliza conexiones entre clientes. Un `SET search_path` a cadena vacía dejado por otra herramienta (p.ej. `set_config('search_path','',false)` en un volcado `pg_dump` ejecutado sobre ese endpoint pooled) persiste a nivel de **sesión** y puede romper consultas sin esquema con `relation ... does not exist`. Fijar explícitamente `search_path` en **cada nueva conexión** hace que la aplicación sea inmune a ese estado heredado. Comprobado con simulación local (reproduce el error `42P01` y lo repara).

---

## 3. Verificación (evidencia)

### 3.1 Sintaxis

- `php -l src/config/database.php` → OK
- `php -l tests/Integration/Server.php` → OK
- `php -l src/views/home/show.php` → OK
- `php -l src/views/home/play.php` → OK
- `php -l src/views/layout/header.php` → OK
- `node --check public/js/rv-lang.js` → OK

### 3.2 Suite de tests

- **Completa (sin filtro):** `Tests: 235, Assertions: 14936, OK` (verde). El error previo de `AdminSlugTest` desapareció al forzar `TRANSLATE_PROVIDER=none` en el servidor hijo.
- **Unit (aislados):** verdes (140/14643). No se detectaron regresiones.

### 3.3 Smoke HTTP (php -S localhost:8010 router.php)

| Ruta | Código | Observación |
|---|---|---|
| `/` | 200 | Home carga correctamente |
| `/juego/power-rangers-ninja-storm` | 200 | Ficha con selector de idioma funcional |
| `/home/play/1` | 200 | Vista de emulación |
| `/ajax_catalog.php?page=1` | 200 | Catálogo vía AJAX |
| `/rom_proxy.php` (sin firma) | 400 | Rechazo correcto (firma obligatoria) |
| `/src/config/database.php` | 403 | Bloqueado por router/.htaccess (no servido) |
| `/index.php?controller=home&action=showBySlug&id=power-rangers-ninja-storm` | 200 | Legacy preservado |

### 3.4 Repro con Puppeteer-core + Edge headless

Ejecución de `repro.js` (ficha `/juego/power-rangers-ninja-storm`, ES→EN):

```json
{
  "url": "http://localhost:8010/juego/power-rangers-ninja-storm",
  "htmlLang": "en",
  "title": "Power Rangers: Ninja Storm — ROMs Vault",
  "toastClass": "share-toast",
  "toastVisible": "0/visible",
  "bodyH1": "Power Rangers: Ninja Storm"
}
```

Evidencias clave:
- `toastClass: "share-toast"` (ya **no** recibe `rv-lang-fade` cuando está oculto). Antes del fix aparecía `share-toast rv-lang-fade` con `opacity ~0.45`.
- `toastVisible: "0/visible"` (permanece oculto, `opacity: 0`). No hubo flash al cambiar de idioma.
- El formulario no genera campos ocultos con parámetros de ruta limpia (verificado en la traza: la navegación tras cambiar idioma queda limpia).
- Z-index del menú `.lang-menu` = `50` vs `.stat-chip--share` estático: sin solape de clics (comprobado en cajas).

### 3.5 Endurecimiento search_path

- App (Neon): `current_setting('search_path') = "$user", public`, `pg_settings.source = session` (fijado por la app). Consultas sin esquema funcionan (`juegos:123`, `juegos_traducciones:1353`, `categorias_traducciones:429`).
- Simulación local (sesión contaminada con `set_config(...,'',false)`): consulta sin esquema **falla** (`42P01 relation "juegos" does not exist`), tras `SET search_path TO "$user", public` **recupera** (`OK, juegos=123`). Demuestra que el parche inmuniza frente al estado heredado del pooler.

---

## 4. Impacto y riesgos

- **Compatibilidad:** Sin cambios de comportamiento funcional. URLs limpias y legacy siguen funcionando. `vercel.json` sin tocar (`rewrites: []`).
- **CSP/EmulatorJS:** Sin afectación (no toca cabeceras ni `blob:`/`unsafe-eval`).
- **Rendimiento:** Mínimo (una sentencia `SET search_path` por conexión exitosa). Hermetismo de tests reduce tiempo de ejecución y elimina dependencia de red.
- **Seguridad:** Mejora leve (tests no envían tráfico externo innecesario; conexión más robusta frente a estado de sesión compartido). No hay cambios en lógica de seguridad.
- **Accesibilidad:** Mejorada con `data-lang-skip` en toasts (evita anuncio prematuro a lectores de pantalla). Trade-off aceptado (texto del toast puede quedar en idioma anterior hasta recargar).

---

## 5. Notas

- El utilitario de repro (`puppeteer-core`) permanece fuera del repo (`%TEMP%\rv-pw\`), throwaway. `scratch/` contiene scripts de diagnóstico (`db-searchpath*.php`) ignorados por git.
- El backfill de traducciones en Neon (1353+429 filas) se ejecutó previamente desde la BD local con integridad verificada (md5 de PK idénticos). Este commit **no modifica datos**, solo código y tests.
- El plan de Drive privado con service account permanece en espera (según indicación del usuario).

---

## 6. Conclusión

Los dos bugs reportados quedan corregidos con evidencia verificable:
1. El toast no parpadea al cambiar de idioma (elementos invisibles excluidos de la animación).
2. El selector de idioma no duplica parámetros en URLs limpias (lee `QUERY_STRING` en lugar de `$_GET`).

Además se añade hermetismo a la suite de integración y un endurecimiento defensivo de la conexión a PostgreSQL para mitigar efectos del pooler. Todos los tests pasan (235/14936). No hay cambios en `.env` ni en comportamiento de producción.