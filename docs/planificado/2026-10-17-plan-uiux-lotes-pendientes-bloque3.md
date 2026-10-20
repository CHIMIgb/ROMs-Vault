# Plan — Bloque 3 UI/UX: lotes pendientes (B restante, C design system, contraste amber)

**Fecha:** 2026-10-17 · **Estado:** Lote B y Lote C aplicados (2026-10-17); D2 pendiente (irá por separado)
**Rama sugerida:** nueva rama desde `main` tras fusionar `mejora/uiux-lote-a-accesibilidad`.
**Auditoría origen:** `docs/2026-10-17-auditoria-uiux-bloque3-lote-a-d.md` (hallazgos A1-A4, B1-B4, C1-C5, D1).

> Los Lotes A (accesibilidad base), D (estado vacío CRT) y **B restante (B1/B3/B4)** ya
> están aplicados (ramas `mejora/uiux-lote-a-accesibilidad` y `mejora/uiux-lote-b-i18n-visual`).
> Este documento planifica **solo lo que queda** (Lote C + D2). Cada ítem requiere
> su plan §10 aprobado antes de tocar código. Detalle del B cerrado:
> `docs/2026-10-17-lote-b-i18n-visual.md`.

> Los Lotes A (accesibilidad base) y D (estado vacío CRT) ya están aplicados en la
> rama `mejora/uiux-lote-a-accesibilidad`. Este documento planifica **solo lo que
> queda** del Bloque 3. Cada ítem requiere su plan §10 aprobado antes de tocar código.

---

## Resumen de lo ya cerrado (referencia)

| Ítem | Estado |
|---|---|
| A1 focus-visible global · A2 contraste grises · A3 ámbar como texto · A4 targets táctiles | Aplicados en rama `mejora/uiux-lote-a-accesibilidad` |
| B2 nowrap+ellipsis de tarjetas · C4 radios del carrusel | Decisiones deliberadas documentadas en CSS (sin cambio) |
| D1 estado vacío del catálogo | Componente `EmptyState` (CRT) aplicado |

---

## Lote B restante — i18n visual ✓ APLICADO 2026-10-17

> Cerrado en `mejora/uiux-lote-b-i18n-visual`: B1 (`60vh` sin tope de 22rem,
> para cumplir "idéntico en pantallas altas"), B3 (`href="/"` base en vez de
> `href="#"`, mejora progresiva) y B4 (alfabeto por primer carácter del
> contenido, alternativa `\p{Latin}` que este plan ya contemplaba — también
> excluye cirílico). Detalle: `docs/2026-10-17-lote-b-i18n-visual.md`.
> Se conservan las descripciones originales íntegras a continuación como
> registro del plan.

### B1 — Menú de idiomas sin `max-height` (P1)

**Problema:** con 12 idiomas (Tier 2), `.lang-menu` crece sin límite. En viewports
bajos (o con zoom alto) las últimas opciones quedan fuera de pantalla y son
inalcanzables — el menú se despliega hacia abajo desde el header.

**Archivos:**
```
[MODIFICAR] public/css/modules/layout.css   (.lang-menu: ~línea 290)
```

**Propuesta:**
- `max-height: min(60vh, 22rem); overflow-y: auto;` en `.lang-menu`.
- Scrollbar nativa (ya estilizada globalmente con la pista cream).
- Sin cambio en el comportamiento `:focus-within` actual.
- Revisar que en RTL (`ar`) el scroll no rompa con logical properties.

**Criterios de aceptación:**
- En un viewport de 500px de alto, las 12 opciones son alcanzables (scroll).
- En pantallas altas el menú luce idéntico al actual.

### B3 — `javascript:history.back()` en el 404 (P1)

**Problema:** `src/views/errors/404.php` usa `href="javascript:history.back()"` —
URI de JavaScript obsoleta y bloqueada por CSP estricta en otros contextos; además
no prograda nada si no hay historial.

**Archivos:**
```
[MODIFICAR] src/views/errors/404.php            (href del botón "Volver atrás")
[CREAR]     public/js/views/errors/404.js       (patrón Bloque 4: JS externo con defer)
[MODIFICAR] public/css/modules/components.css   (solo si se añade clase de fallback)
```

**Propuesta:**
- `<a href="#" class="btn-404-back" data-back>` + JS externo `defer` que hace
  `history.length > 1 ? history.back() : location.href = '/'` (fallback al catálogo).
- Eliminar la URI `javascript:`.
- Si `VistasSinScriptInline` exige isla JSON para traducciones, no hace falta:
  el fallback no necesita cadenas (va a `/`).

**Criterios de aceptación:**
- Con historial: vuelve a la página anterior. Sin historial: cae en `/`.
- `grep -r "javascript:" src/views/` → 0 resultados.
- Tests Unit en verde (incluido `VistasSinScriptInline`).

### B4 — Drop-cap `::first-letter` con CJK/RTL (P1)

**Problema:** el capitular editorial de la sinopsis (`.detail-synopsis-content::first-letter`,
Press Start 2P 2.3rem rojo) aplica también a sinopsis en `ja/zh/ko/ar`. Press Start 2P
no cubre esos scripts → fallback feo y, en RTL, el `float: inline-start` con un
carácter árabe unido rompe la forma de la palabra.

**Archivos:**
```
[MODIFICAR] public/css/modules/game-details.css  (.detail-synopsis-content::first-letter)
[MODIFICAR] src/views/game/detail.php            (lang attribute en la sinopsis, si procede)
```

**Propuesta:**
- Acotar el capitular al contenido latino. Opción más limpia con el stack actual:
  clase condicional desde la vista — `$lang` activo en `ja/zh/ko/ar` → no imprimir
  la clase `synopsis-dropcap` (o imprimirla solo si la sinopsis empieza por latín).
- Alternativa CSS-only (menos robusta): `:lang(es)`, `:lang(en)`… — no escala con
  sinopsis mixtas; se descarta.
- Mantener el aspecto idéntico para sinopsis en scripts latinos (es/en/fr/it/pt/de/tr).

**Criterios de aceptación:**
- Sinopsis en `ja` o `ar`: sin capitular, texto fluye normal.
- Sinopsis en `es`: capitular idéntico al actual.

---

## Lote C — Design system / mantenimiento ✓ APLICADO 2026-10-17

> Cerrado en `mejora/uiux-lote-c-design-system` (4 commits de código + doc):
> C5+C3 (`5b55cd1`) → C2 (`982398d`) → C1 ola admin-1 (`d72a28c`) →
> C1 olas admin-2 + front (`66b863d`). D2 quedó **excluido** de este lote a
> petición del usuario (irá por separado). C1 terminó con **3** `style=` en
> `src/views` (2 dinámicos + la API de `Alert::render`), lejos del objetivo
> de ≤40. Detalle: `docs/2026-10-17-lote-c-design-system.md`.
> Se conservan las descripciones originales íntegras a continuación como
> registro del plan.

> Orden sugerido original: C5 → C3 → C2 → C1 (de menor a mayor blast radius).
> Ejecutado exactamente en ese orden, en 5 commits aprobados.

### C5 — Limpieza menor (P2)

**Problemas:**
1. Cabecera de `public/css/modules/variables.css:1` dice `/* public/css/style.css */` (copy-paste).
2. `style="position:relative"` redundante en `src/views/home/index.php:5` (el contenedor ya lo es por CSS).

**Archivos:**
```
[MODIFICAR] public/css/modules/variables.css  (comentario de cabecera)
[MODIFICAR] src/views/home/index.php          (quitar style redundante)
```

### C3 — Sombras hardcoded → tokens (P2)

**Problema:** ~30 sitios repiten `3px 3px 0 #8a7a58`, `5px 5px 0 #8a7a58`,
`4px 4px 0 #8a7a58`, `2px 2px 0 #8a7a58`… siendo valores idénticos a los tokens
`--raise-shadow`, `--raise-shadow-lg`, `--card-raise` ya existentes.

**Archivos:**
```
[MODIFICAR] public/css/modules/*.css  (components, game-grid, game-details, responsive, forms, admin…)
```

**Propuesta:**
- Mapeo: `3px 3px 0 #8a7a58` → `var(--raise-shadow)`; `5px 5px 0 #8a7a58` →
  `var(--raise-shadow-lg)`; `4px 4px 0 #8a7a58, 5px 5px 0 #6a5a38` → `var(--card-raise)`.
- Las variantes de hover (`1px 1px 0`, `2px 2px 0`) y las sombras oscuras de botones
  sobre fondo slate (`#2a2a2a`, `#1a3a20`…) **no** tienen token: o se añaden tokens
  nuevos (`--press-shadow`, `--btn-dark-shadow`) o se dejan hardcoded — decisión §10.
- Cero cambio visual: mismos valores, solo referencia al token.

**Criterios de aceptación:**
- `grep -rn "#8a7a58" public/css/modules/` → solo tokens y hover/variantes justificados.
- Diff visual: ninguno (valores idénticos).

### C2 — Unificación de tokens (P2)

**Problema:** doble sistema de nombres — paleta canónica (`--slate`, `--cream`, `--red`…)
y alias legacy (`--primary`, `--secondary`, `--accent`, `--background`, `--card-bg`,
`--text`, `--text-light`, `--border`, `--success`, `--warning`, `--danger`, `--shadow`,
`--shadow-hover`) creados para "compatibilidad con código PHP existente".

**Archivos:**
```
[MODIFICAR] public/css/modules/variables.css  (eliminar alias o convertirlos en referencia a la paleta)
[MODIFICAR] src/views/**/*.php                (donde usen var(--primary) etc. inline — ~ver C1)
[MODIFICAR] public/css/modules/*.css          (usos de alias en módulos)
```

**Propuesta:**
1. Inventario: `grep -rn -- "--primary\|--secondary\|--accent\|--background\|--card-bg\|--text:\|--text-light\|--border:\|--success\|--warning\|--danger\|--shadow:" public/ src/`.
2. Sustituir cada alias por su token canónico equivalente en CSS y PHP.
3. Eliminar el bloque de alias de `variables.css:31-44`.
4. `--text-light` (usado en vistas admin inline) → sustituir por `--slate-light` y
   dejarlo para el lote C1 (esos estilos inline van a clases).

**Riesgo:** alto olvido en PHP inline → checklist del inventario y grep final = 0.
**Criterios de aceptación:**
- `grep -rn -- "var(--primary)\|var(--secondary)\|..." public/ src/` → 0 resultados.
- Tests Unit en verde; smoke home + admin.

### C1 — Estilos inline → clases CSS (P2)

**Problema:** ~145 estilos `style="…"` en 19 vistas, concentrados en admin
(`tfa.php` ~25, `dashboard.php` ~24, `emuladores/*` ~36). Rompe la separación de
capas (la vista debe ser markup + clases; el estilo vive en `modules/`).

**Archivos (mayores concentraciones):**
```
[MODIFICAR] src/views/admin/tfa.php
[MODIFICAR] src/views/admin/dashboard.php
[MODIFICAR] src/views/admin/emuladores/*.php
[MODIFICAR] src/views/admin/{consolas,categorias,juegos}/*.php
[MODIFICAR] public/css/modules/admin.css      (clases nuevas, una por patrón inline)
```

**Propuesta (por olas para revisión granular):**
1. **Ola admin-1:** `tfa.php` + `dashboard.php` (mayor volumen, superficie acotada).
2. **Ola admin-2:** `emuladores/*` + `consolas` + `categorias` + `juegos`.
3. **Ola front:** resto de vistas (home, play, errors, auth) — volumen bajo.
4. Cada patrón inline recurrente → una clase semántica en el módulo correspondiente
   (`admin.css` para admin, etc.). Estilos únicos y triviales (p. ej. `margin:0`)
   pueden quedarse inline si el coste de la clase supera el beneficio — criterio §10.
5. Los inline que consumen tokens (`style="color:var(--text-light)"`) se resuelven
   primero con C2 (token canónico) y luego la clase los absorbe.

**Criterios de aceptación:**
- Recuento post-lote: `grep -rn 'style="' src/views/ | wc -l` con objetivo acordado
  en §10 de cada ola (no hace falta llegar a 0 si 3-4 inline justificados quedan).
- Smoke visual admin completo (dashboard, tfa, emuladores add/edit, listados).
- Tests Unit en verde.

---

## Contraste pendiente — fondos amber (D2, decisión requerida)

**Problema:** componentes con **fondo** `--amber` y texto claro:
- `.tag-hack` (12px bold uppercase, `cream-light` sobre ámbar) = **3.25:1** — falla AA (4.5).
- `.detail-hack-ribbon` (0.42rem Press Start, `cream-light` sobre ámbar) = 3.25:1.
- `.rv-btn--warning` (`#fff` sobre ámbar) = 3.25:1.

**Opciones (requiere decisión del usuario):**
1. **Oscurecer el fondo** de esos componentes a `--amber-dark` (#664d0a): el texto
   cream-light pasaría a ~11:1. Cambio visual real (los badges se ven más oscuros).
2. **Mantener y aceptar** — argumento: son badges decorativos/etiquetas, no texto
   corrido; algunos son "texto grande" por peso/uppercase (no lo son por tamaño real:
   12px no cumple la exención de texto grande). No es defendible estrictamente.
3. **Híbrido:** fondo ámbar solo en desktop hover/estados, texto slate normal.

Recomendación: **opción 1** para `.tag-hack` y `.rv-btn--warning` (son accionables
o informativos); el ribbon puede quedarse si se documenta como decorativo.

---

## Orden de ejecución sugerido

1. **B1** (5 min, CSS puro) + **B3** ( pequeño, JS externo) + **B4** — cerrar lote B en una rama.
2. **C5** + **C3** — limpieza barata, cero riesgo visual.
3. **D2** — decidir con el usuario, aplicar.
4. **C2** — unificación de tokens (inventario grande).
5. **C1** — olas admin → front (el más largo; abrir PR dedicado por ola si se prefiere).

## Fuera de alcance de este plan

- Roadmap FASE 2 (PHPStan/phpcs/GitHub Actions) — se planifica aparte.
- Lote B1 del plan original de seguridad (allowlist de hosts del proxy) — pertenece a `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md`.
- Cualquier rediseño de paleta, tipografía o layout — este Bloque 3 es reparación,
  no restyling (principio acordado con el usuario 2026-10-17).
