# i18n — Migración de la UI a t() · Fase 1 (público)

**Fecha:** 2026-10-09 · **Plan:** §6.3 · **Rama:** `optimizacion-arquitectura`

## Alcance (Fase 1)
Se migran las cadenas hardcodeadas de las vistas públicas a la función global `t()`:

- `src/views/layout/header.php`, `src/views/layout/footer.php`
- `src/views/components/game_actions.php`, `src/views/components/Pagination.php`, `src/views/components/related_games.php`
- `src/views/home/index.php`, `src/views/home/show.php`, `src/views/home/play.php`
- `src/views/errors/404.php`, `src/views/errors/generic.php`

## Decisiones
- Claves reutilizadas de `es.json`/`en.json` (ya poblados en 6.2). Se añadieron: `show.ir_a_captura`, `show.esta_categoria`, `play.consejo_archivo_no_encontrado_js`, `play.consejo_archivo_privado_js`, `play.consejo_limite_descargas_js`, `play.recibidos`, `component.juegos` (o existente), `errors.url`.
- Escapado: texto plano con `htmlspecialchars`; HTML interno intencional (show.esta_rom_es_de/emulador_recomendado, confirm_eliminar) no se escapa (comportamiento actual).
- JS inline: textos inyectados con `json_encode(t(...))` para evitar romper comillas simples/dobles.
- `errors/404.php`: renombrada variable local `t` (intervalo) a `timer` para no ensombrecer la función global `t()`.
- Datos de BD (nombres de consolas/categorías) **no se traducen**.

## Verificación
- `php -l` OK en todos los archivos migrados.
- Suite completa: **161 tests / 2679 assertions OK**.
- Smoke bilingüe parcial (atributo lang + home): sin regresiones.

## Commits
- `feat(i18n): Fase 1 — migrar UI pública (layout/components/home/errors) a t()` (código)
- `docs(i18n): Fase 1` (este documento + registro en el plan si procede)
