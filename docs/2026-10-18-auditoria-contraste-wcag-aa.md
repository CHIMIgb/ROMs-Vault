# 2026-10-18 — Auditoría de contraste WCAG AA (catálogo, ficha, play)

Método: **análisis CSS estático** (aprobado por el usuario). Se resuelven los tokens de
`:root` (`variables.css` + `rv-*` de `components.css`), se extraen los pares
texto/fondo por selector de los módulos que sirven a las 3 vistas y se calcula el ratio
WCAG 2.1. Se verifican además las superficies de **herencia** (el `body` usa
`--cream-dark`, no `--cream`) resolviendo manualmente el contenedor real de cada texto.
Umbrales: 4.5:1 texto normal, 3:1 texto grande (>=24px, o >=18.66px bold) e iconos.

Script de evidencia (throwaway, no versionado): `scratch/a11y_contrast.py`.

## Superficies del sistema (tokens)

| Token | Hex | Ratio de referencia (contraste contra `--slate`) |
|---|---|---|
| `--cream` | `#e8e0c8` | 8.63 (slate) |
| `--cream-light` | `#f2ece0` | 9.67 (slate) |
| `--cream-dark` (body) | `#d4c9a8` | 6.89 (slate) |
| `--slate` / `--slate-mid` / `--slate-light` | `#3a3a3a` / `#4f4f4f` / `#666666` | — |
| `--red` / `--blue-btn` | `#c0392b` / `#2c5f8a` | — |

## Resultado: pares declarados en el mismo bloque

**0 fallos.** Todos los selectores que declaran `color` y `background` en la misma regla
cumplen AA (incluidos botones, chips, alertas, toasts, modales y avisos).
Esto cubre la mayoría del contraste "estructurado" de las 3 vistas (60/60 catálogo,
60/60 ficha, 67/67 play).

## Fallos reales detectados (herencia sobre el `body`/paneles)

El `body` es `--cream-dark` (`#d4c9a8`) con textura; el contenido que no vive dentro de un
panel hereda esa superficie. Verificado contra las superficies reales:

| # | Selector | Vista | Texto | Fondo real | Ratio | Umbral | Estado |
|---|---|---|---|---|---|---|---|
| 1 | `.detail-section-title` (título "Capturas") | ficha | `--red` `#c0392b` | body `#d4c9a8` | **3.29** | 4.5 | FALLA |
| 2 | `.clear-filters:hover` | catálogo | `--red` `#c0392b` | `.filters` `--cream` `#e8e0c8` | **4.12** | 4.5 | FALLA (estado hover) |

**Notas de encuadre:**
- El `.detail-section-title` de "Cómo jugar" **sí** cumple (4.62) porque vive dentro de
  `.detail-howto` (`--cream-light`). Solo el de la sección `.detail-screenshots` (que no
  tiene fondo propio) hereda el `body` y falla.
- `.subtitle svg.pxi` (icono decorativo, red sobre body) = 4.12 → **OK** para su umbral de
  icono (3:1).
- `.related-title` (slate sobre body) = 6.89 → OK. `.subtitle` (slate-mid) = 4.96 → OK.
- `.howto-nomap`, `.size-control-min/max` usan `--slate-light` sobre paneles claros → OK.

## Casos del límite (cumplen, pero con poco margen)

| Elemento | Texto | Fondo | Ratio | Comentario |
|---|---|---|---|---|
| `.detail-section-title` (howto) | red | cream-light | 4.62 | margen mínimo aceptable |
| `.howto-dl-link` / `.howto-alt a` | blue | cream-light | 5.74 | OK |
| `.howto-dl-link:hover` | red | cream-light | 4.62 | OK |
| `--border-mid` `#a09070` | — | cream-dark | 1.89 | **no apto como color de texto**; se usa como borde (correcto) |

## Conclusiones

1. El design system tiene una disciplina de contraste **sólida**: los tokens `slate-mid` y
   `slate-light` ya se ajustaron en su día (comentarios AA en `variables.css`) y el 100 %
   de los pares declarados cumple.
2. **1 defecto real de accesibilidad** en la ficha: el título de sección "Capturas"
   (`.detail-section-title` sobre el body). Rompe AA porque `.detail-screenshots` no tiene
   panel de fondo y el rojo `--red` sobre `--cream-dark` da 3.29.
3. **1 defecto menor** de estado hover en el catálogo (`.clear-filters:hover`, 4.12).
4. Ninguna superficie `--cream-dark` admite texto en `--red`/`--slate-light`/`--blue-btn`
   a tamaño normal (3.29 / 3.48 / 4.09): es la regla derivada para futuros desarrollos.

## Recomendaciones (para el plan de ruido visual / siguiente lote)

- **Ficha:** para `.detail-section-title` sobre body, usar `--red-dark` (`#962d22`) en vez
  de `--red`, o dar a `.detail-screenshots` un tratamiento de fondo como `.detail-howto`.
  (A decidir junto con el análisis de jerarquía.)
- **Catálogo:** `--red-dark` también arregla `.clear-filters:hover` (ratio a validar).
- **Regla de sistema:** nunca texto normal en `--red`/`--slate-light`/`--blue-btn` sobre
  `--cream-dark`.
