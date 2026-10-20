# 2026-10-18 — Análisis de ruido visual de la ficha (`/juego`) — PENDIENTE

Estado: **análisis documentado, NO ejecutado.** Decisión del usuario: aplicar solo el fix de
contraste ahora (ver `docs/2026-10-18-auditoria-contraste-wcag-aa.md`) y dejar este trabajo
para un lote aparte, previa aprobación de plan §10 específico.

## Objetivo

Evaluar si la ficha acumula "ornamento competitivo" (marcos, sellos, sombras duras, iconos,
chips) que debilite la jerarquía de lectura, y proponer reducciones concretas cuando se
aborde. Ninguna recomendación aquí debe implementarse sin plan aprobado.

## Contexto de identidad

La ficha es una pieza showpiece de la estética Game Boy/CRT: hero cover + panel, sellos,
scanlines, chips, marcos de TV. La identidad es deliberada y debe preservarse; el análisis
busca **ruido**, no eliminar carácter.

## Métricas objetivas (render real, `clock-tower-advance`)

| Métrica | Valor |
|---|---|
| Iconos `<i data-i>` en la página | 30 |
| Elementos `svg.pxi` | 11 |
| Selectores/clases distintos con presencia en la página | 79 |
| Scanlines CRT (bloques) | 2 (hero cover + carrusel) |

### Densidad por sección

| Sección | Chips/tags | Iconos | Scanlines | Sellos |
|---|---|---|---|---|
| hero (`game-detail-hero`) | 5 | 3 | 1 | 1 (region-seal) |
| capturas (`detail-screenshots`) | 0 | 10 | 1 | 0 |
| cómo jugar (`detail-howto`) | 4 | 3 | 0 | 0 |
| relacionados (`related-section`) | 10 | 6 | 0 | 0 |

## Hipótesis de ruido (a validar en el lote)

1. **Cantidad de iconos.** 30 iconos en un viewport de ficha es alto; muchos son decorativos
   y podrían competir con el contenido. Revisar cuáles aportan significado y cuáles son
   eco visual (candidatos: repetir icono en cada `detail-section-title`, en cada step de
   cómo jugar, en cada tarjeta relacionada).
2. **Scanlines duplicadas.** Hero cover y carrusel tienen scanlines CRT; el hero además tiene
   marco + sello. Puede ser fiel al CRT o puede ser duplicación de motivo. Evaluar.
3. **Chips en "relacionados" (10).** La sección de relacionados usa su propio sistema de tags
   (`related-tag--platform/language`); convive con los chips de la zona superior de la ficha.
   Validar que no se lea como dos "sistemas de chips" distintos.
4. **Sombras duras + bordes en todos los contenedores.** El patrón `border 3px + box-shadow
   raise` se repite en `.detail-howto`, `.detail-screenshots` (nuevo) y tarjetas; verificar
   que la repetición no aplane la jerarquía (todos los bloques con el mismo peso visual).
5. **Sección de capturas recién convertida en panel.** Ahora que `.detail-screenshots` tiene
   fondo/borde/sombra como `.detail-howto`, hay dos paneles idénticos consecutivos antes de
   relacionados. Confirmar en el lote que la secuencia no se lea monótona.

## Método propuesto para el lote (cuando se aborde)

1. Captura visual real (no solo métricas) o render por sección.
2. Lista priorizada de candidatos a simplificar, cada uno con: elemento, qué se quita/atenúa,
   impacto esperado en jerarquía, y riesgo de identidad.
3. Plan §10 con el subconjunto que el usuario apruebe; verificación con `smoke_panel`,
   `smoke_play`, Unit y render.

## Referencias

- `docs/2026-10-17-auditoria-uiux-bloque3-lote-a-d.md` (auditoría UI/UX previa).
- `docs/2026-10-17-lote-c-design-system.md` (disciplina de tokens).
- `docs/2026-10-18-auditoria-contraste-wcag-aa.md` (contraste; fix del título aplicado).
