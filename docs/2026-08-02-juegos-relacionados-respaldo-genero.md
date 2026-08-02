# Juegos relacionados: respaldo de género garantizado (backend)

**Fecha:** 2026-08-02
**Archivos modificados:**
- `models/Juego.php` — Nueva lógica de `getRelated()` con respaldos de género
- `views/components/related_games.php` — Límite de juegos de 8 a 10

## Qué se hizo

Las recomendaciones de juegos relacionados se dividen en dos secciones: "Más de [consola]" y "Géneros similares". El problema era que la sección de género a veces no aparecía: el `LIMIT` global se lo llenaban los juegos de la misma consola (prioridad por relevancia) o la categoría tenía pocos juegos.

### Nueva lógica de `Juego::getRelated()`
- El límite se divide por la mitad entre las dos secciones: misma consola (relevancia 2) y mismo género (relevancia 1)
- La sección de género se intenta llenar **siempre**, con 4 niveles de respaldo en orden de prioridad y con `ORDER BY RANDOM()` para variedad:
  1. **Mismo género exacto** — misma categoría, preferentemente de otras consolas
  2. **Género muy relacionado** — categorías hermanas: comparten una palabra significativa en el nombre (Acción ↔ Acción-Aventura / Accion RPG, Carreras ↔ Carreras Kart, Deportes ↔ Deportes Extremos…) o pertenecen a la misma familia de género del mapa `FAMILIAS_GENERO` (Rol → JRPG/RPG/Estrategia RPG, Terror → Survival Horror, Peleas → Lucha Libre/Beat em up, Disparos → Shoot em up/Run and gun, etc.)
  3. **Relacionado por el nombre** — juegos cuyo título comparte palabras clave significativas con el título actual (p. ej. Final Fantasy VII → Final Fantasy VIII), ignorando stopwords genéricas
  4. **Último recurso** — cualquier otro juego activo, para que la sección nunca quede vacía cuando existan más juegos en la colección
- Se evitan duplicados: la sección de género excluye los juegos ya mostrados en la de consola y al juego actual
- Se agregan helpers privados: `relacionadosPorConsola()`, `relacionadosPorGenero()`, `queryGenero()`, `categoriasRelacionadas()`, `familiaDeGenero()`, `palabrasClaveTitulo()`, `palabrasSignificativas()` y `normalizar()` (minúsculas + sin acentos vía `iconv` ASCII//TRANSLIT)

### Qué no se cambió
- `views/components/related_games.php` — ya divide por `relevancia` (2 = consola, 1 = género)
- El frontend, los CSS y las demás consultas del modelo

## Decisiones de diseño
- El mapa `FAMILIAS_GENERO` es data editable y comentada, no lógica oculta: si en el futuro entran categorías nuevas, se agregan términos sin tocar SQL
- El nivel 4 es deliberado: el usuario priorizó que la sección "siempre" aparezca; la variedad general es mejor que una sección vacía
- `queryGenero()` centraliza la consulta de la sección de género: un solo método que arma `NOT IN` dinámico con placeholders preparados (sin inyección SQL) y reutiliza los fragmentos `WHERE`
- Las consultas siguen siendo PostgreSQL puras (`ILIKE`, `RANDOM()`), igual que el resto del modelo

## Verificación
- Sin PHP CLI en el entorno de desarrollo: revisión estática del código y compatibilidad con el patrón PDO existente (`LIMIT :lim` con `PARAM_INT` ya se usaba)
- En producción: abrir la ficha de un juego y confirmar que "Géneros similares" siempre aparece con contenido, incluso en categorías escasas o consolas muy pobladas

## Ajuste posterior: 5 y 5
- La llamada a `getRelated()` en `views/components/related_games.php` pasó de `8` a `10` juegos
- Con la división 50/50 del modelo, cada sección recibe 5: 5 de la misma consola + 5 del mismo género, una fila completa del grid de 5 columnas

## Ajuste posterior: títulos de sección sin repeticiones
- Se eliminó el parámetro `$subtitulo` de `$renderRel` en `views/components/related_games.php`
- Sección de consola: título "Más de [consola]" sin el subtítulo en recuadro que repetía el nombre de la consola
- Sección de género: título "Géneros similares" → "Juegos relacionados", sin el subtítulo que repetía la categoría
- CSS muerto eliminado: `.related-title-sub` en `game-grid.css` y su regla responsive en `responsive.css`
