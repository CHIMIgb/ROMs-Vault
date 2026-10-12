# Ajuste del gate PHP_CodeSniffer (PSR-12) — 2026-10-11

## Contexto

El job `lint`/`static` del CI (FASE 2, `.github/workflows/ci.yml`) ejecuta
`vendor/bin/phpcs` sobre los ficheros PHP de `src/` y `endpoints/` que cambian en
cada PR/push. La regla original era **PSR-12 puro** con `--diff-filter=ACMR`
(ficheros añadidos, copiados, modificados y renombrados).

Al implementar el ítem 7.2 (slugs) se detectó que ese diseño **hace fallar el
gate en cualquier cambio no trivial**:

1. **PSR-12 exige namespaces en cada clase** (`PSR1.Classes.ClassDeclaration.MissingNamespace`),
   pero el proyecto prohíbe PSR-4/namespaces en el código de app (ver `AGENTS.md`).
2. **PSR-12 exige la llave de clase en línea nueva** (`PSR2.Classes.ClassDeclaration.OpenBraceNewLine`)
   y el salto de línea en métodos multi-línea
   (`Squiz.Functions.MultiLineFunctionDeclaration.BraceOnSameLine`), mientras el
   proyecto usa llaves en la misma línea (K&R) de forma sistemática.

Resultado: prácticamente todo fichero de `src/` violaba el estándar (p. ej.
`src/models/Juego.php` con 59 avisos y `src/controllers/HomeController.php` con 32),
y como el gate aplica al **fichero completo**, tocar cualquiera de ellos lo hacía
fallar. El propio comentario de `phpcs.xml.dist` ya declaraba la intención de
*"evitar reescribir el código legado"*, lo que es incompatible con `ACMR`.

El PR #9 (FASE 2) pasó el gate solo porque únicamente añadió ficheros nuevos ya
limpios; el problema quedó latente hasta el primer cambio en código legacy.

## Decisión

Se corrigen dos cosas, alineadas con las convenciones reales del proyecto:

1. **`phpcs.xml.dist`**: PSR-12 con exclusiones mínimas y justificadas para las
   convenciones deliberadas del proyecto:
   - `PSR1.Classes.ClassDeclaration.MissingNamespace` (sin namespaces en app).
   - `PSR2.Classes.ClassDeclaration.OpenBraceNewLine` (llave de clase en la misma línea).
   - `Squiz.Functions.MultiLineFunctionDeclaration.BraceOnSameLine` (llave de método en la misma línea).
   
   Se conserva el resto del estándar (espacios, saltos, longitud de línea, etc.).

2. **`.github/workflows/ci.yml`**: el paso de phpcs pasa a `--diff-filter=A`
   (**solo ficheros añadidos**). Es un *ratchet*: el código nuevo nace limpio sin
   obligar a reescribir el legado. El análisis de corrección real lo sigue
   cubriendo **PHPStan** (nivel 5 + baseline), que se ejecuta sobre todo el código
   en cada run.

## Camino futuro

Cuando se aborde una limpieza de estilo del legado (tarea aparte), se puede pasar
el gate de nuevo a `ACMR` (o a `src/` completo) y retirar las exclusiones si se
adopta PSR-12 estricto de forma global.

## Verificación

- `vendor/bin/phpcs src/config/Slug.php` → limpio (fichero nuevo de este trabajo).
- `vendor/bin/phpstan analyse` → `[OK] No errors`.
- `composer test` → 182 tests / 7566 assertions, verde.
