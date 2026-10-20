# Lote B — i18n visual (B1, B3, B4) · Bloque 3 UI/UX

**Fecha:** 2026-10-17 · **Estado:** aplicado y verificado (pendiente de commit)
**Rama:** `mejora/uiux-lote-b-i18n-visual` (apilada sobre `mejora/uiux-lote-a-accesibilidad`, aún sin fusionar)
**Plan origen:** `docs/planificado/2026-10-17-plan-uiux-lotes-pendientes-bloque3.md` (Lote B restante)
**Auditoría:** `docs/2026-10-17-auditoria-uiux-bloque3-lote-a-d.md`

---

## Cambios aplicados

### B1 — Menú de idiomas con `max-height` + scroll

`public/css/modules/layout.css` · `.lang-menu`:
- `max-height: 60vh; overflow-y: auto;`
- Con 12 idiomas (Tier 2) el menú crecía sin límite: en viewports bajos las
  últimas opciones quedaban fuera de pantalla y eran inalcanzables.
- **Desviación menor del plan:** el plan sugería `min(60vh, 22rem)`; se usó
  `60vh` sin el tope de 22rem porque los criterios de aceptación pedían que "en
  pantallas altas el menú luce idéntico al actual" — un tope de 22rem (352px)
  forzaría scrollbar en escritorios altos donde hoy el menú entra entero.
- Sin overrides responsive del menú existentes; `overflow-y` es neutral en RTL.

### B3 — Botón "Volver atrás" del 404 sin `javascript:`

**Ampliación dentro del propio criterio de aceptación del plan**
(`grep -r "javascript:" src/views/` → 0 resultados): el grep descubrió dos
instancias más del mismo anti-patrón, corregidas en este mismo lote:

- `src/views/errors/404.php`: `href="javascript:history.back()"` →
  `href="/" data-back`.
- `src/views/errors/generic.php` (pantalla de error genérico, mismo patrón):
  `href="javascript:history.back()"` → `href="/" data-back` + carga
  `<script src="public/js/views/errors/404.js" defer></script>` (el archivo ya
  existía por el Bloque 4; su IIFE del contador sale en esta vista y no hace
  nada — el handler `.btn-404-back[data-back]` es común a ambas pantallas de
  error).
- `src/views/home/play.php` (botón "Reintentar ahora" en error de quota):
  `href="javascript:location.reload()"` → `href="<URL actual>" data-reload`
  (fallback sin JS = navegar a la misma URL) + nuevo
  `public/js/views/home/play-error.js` (defer, referenciado dentro de la rama
  de error — `play-emulator.js` solo se carga en la rama éxito).
- `public/js/views/errors/404.js` (se extendió con una IIFE propia, sin tocar
  la del contador de créditos):
  - Intercepta el clic: `history.length > 1` → `history.back()`, si no → `location.href = '/'`.
  - **Mejora progresiva sobre el plan:** en vez de `href="#"` se usó `href="/"`
    como base, de modo que sin JS (o con CSP que bloquee el script) el enlace
    sigue llevando al catálogo en vez de quedar muerto.
- Criterio del plan: `grep -r "javascript:" src/views/` → 0 resultados ✓.

### B4 — Capitular de sinopsis condicional al alfabeto latino

- `public/css/modules/game-details.css`: selector
  `.detail-synopsis-content::first-letter` → `.detail-synopsis-content.synopsis-dropcap::first-letter`.
- `src/views/home/show.php`: la vista imprime `synopsis-dropcap` solo si
  `$juego['descripcion']` arranca (tras whitespace) por un carácter `\p{Latin}`.
- **Decisión de alcance:** el plan nombraba `ja/zh/ko/ar` (exclusión por idioma
  de UI); se implementó por **primer carácter del contenido** (más robusto: una
  sinopsis en japonés en UI española quedaba fuera del alcance del plan) usando
  la alternativa `\p{Latin}` que el propio plan ya contemplaba.
- Efecto colateral aceptado y documentado: `ru` (cirílico) también pierde el
  capitular — Press Start 2P no cubre cirílico, así que el fallback feo que
  motivaba este ítem aplicaba igualmente.
- Verificado con script de 10 casos (latín, accents, turco, CJK, árabe,
  cirílico, números, puntuación inicial): 10/10 OK.

## Verificación

- `php -l` en `404.php`, `generic.php`, `show.php`, `play.php` → sin errores.
- `grep -rn "javascript:" src/views/` → **0 resultados**.
- Tests Unit completos: **192/192 OK** (incluye `VistasSinScriptInline`,
  `NoEmoji` — un U+2705 introducido en un heading se corrigió por `✓` —, `I18n`…).
- Smoke dev server:
  - `/` → 200, `lang-menu` presente (B1).
  - `/pagina-inexistente-xyz` → **404**, pantalla renderizada, `data-back`
    presente, sin `javascript:`, carga `errors/404.js` (B3).
  - `/home/show/1` → 200, `synopsis-dropcap` presente en sinopsis en latín (B4).
  - `/rom_proxy.php` sin firma → 400 (rechazo, sin regresión).
- No smoke-eado en vivo (no provocable sin forzar cuota de Drive): rama de
  error de `play.php` — cubierta por `VistasSinScriptInline` (script existe,
  defer, referenciado por la vista) y revisión de código.
- Borrado `scratch/test-dropcap.php` (throwaway, no commiteado).

## Pendiente tras este lote

- **Lote C** (C5, C3, C2, C1) y **D2** (contraste de fondos amber) — planificados
  en `docs/planificado/2026-10-17-plan-uiux-lotes-pendientes-bloque3.md`.
