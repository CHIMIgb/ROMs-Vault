# i18n — Idiomas candidatos (Tier 2 y descartados)

**Fecha:** 2026-10-11 · **Estado:** análisis archivado (NO implementado) · **Origen:** análisis de ampliación i18n (Tier 1 implementado en `docs/2026-10-11-i18n-nuevos-idiomas-tier1.md`).

> Documento de referencia para futuras ampliaciones de idioma. No implica trabajo
> en curso: los idiomas listados aquí **no** están en `I18n::LANGUAGES`.

## Contexto

El soporte i18n parte de `es.json` como base/fallback y exige **paridad total de
claves** (592) por idioma. Añadir un idioma son 5 puntos:

1. `src/i18n/{code}.json` completo y paritario.
2. `I18n::LANGUAGES` (whitelist, única fuente de verdad).
3. `IntlHelper::LOCALES` (locale Intl + fallback).
4. `IntlHelper::EMULATOR_LOCALES` (código de EmulatorJS).
5. `$langFlags` SVG en `src/views/layout/header.php`.

La detección (`Accept-Language`, cookie `rv_lang`, selector `?lang=`) y
`endpoints/ajax_lang.php` funcionan solos; `public/js/rv-lang.js` deriva del DOM.

## Criterios de evaluación

- **Código publicado por el CDN de EmulatorJS** (`cdn.emulatorjs.org/stable/data/localization/`).
  Códigos existentes verificados a la fecha: `ar-AR, de-GER, el-GR, en-US, es-ES,
  it-IT, ja-JA, ko-KO, pt-BR, ro-RO, ru-RU, tr-TR, vi-VN, zh-CN`. **No** existen
  `pl-PL` ni `nl-NL`.
- **Tamaño/actividad de la comunidad retro** (retorno por 592 strings traducidos).
- **Coste técnico especial** (p. ej. dirección de escritura RTL).

## Tier 2 — candidatos recomendados (aplazados)

| Idioma | Código app | Locale Intl | EmulatorJS | Notas |
|---|---|---|---|---|
| Chino simplificado | `zh` (o `zh-CN`) | `zh_CN` | `zh-CN` | Gran mercado; CJK (la fuente `Courier Prime` no cubre CJK → fallback monoespaciado, igual que `ja`). |
| Coreano | `ko` | `ko_KR` | `ko-KO` | CJK; misma nota de fuente que `ja`/`zh`. |
| Árabe | `ar` | `ar_AR` | `ar-AR` | **RTL**: requiere `dir="rtl"`, CSS con logical properties y revisión de layout. Depende del pendiente **FASE 5.2** (RTL). |

## Descartados (y por qué)

| Idioma | Motivo |
|---|---|
| Polaco (`pl`) | Sin código en el CDN de EmulatorJS (`pl-PL` no existe) → la UI del emulador quedaría en fallback `en-US`, rompiendo la coherencia. |
| Neerlandés (`nl`) | Sin código en el CDN de EmulatorJS (`nl-NL` no existe). Mismo motivo que `pl`. |
| Rumano (`ro`) | El CDN sí publica `ro-RO`, pero la comunidad retro objetivo es reducida frente al esfuerzo de 592 strings. Reevaluable. |
| Griego (`el`) | El CDN sí publica `el-GR`, pero comunidad reducida. Reevaluable. |
| Vietnamita (`vi`) | El CDN sí publica `vi-VN`, pero comunidad reducida. Reevaluable. |

## Próximo paso sugerido

1. Implementar **RTL (FASE 5.2)** con logical properties en CSS.
2. Añadir **`zh` y `ko`** (Tier 2 CJK, sin coste RTL) con el mismo checklist de 5 puntos.
3. Añadir **`ar`** solo tras cerrar RTL.
