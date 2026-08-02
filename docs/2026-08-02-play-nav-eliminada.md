# Eliminada barra de navegación superior en la vista de jugar online

**Fecha:** 2026-08-02
**Archivos modificados:**
- `views/home/play.php` — Eliminado el bloque `.play-nav` completo
- `public/css/modules/game-details.css` — Eliminado CSS muerto de `.play-nav` / `.btn-back`
- `public/css/modules/responsive.css` — Eliminada la regla responsive de `.play-nav`

## Qué se hizo

La vista "jugar online" (`play.php`) tenía una barra de navegación superior con dos elementos:

- Un botón **"Volver al Catálogo"** (`.btn-back`)
- Al lado, el **título del juego** (`.play-nav-title`) con la consola entre corchetes (`.play-nav-platform`), separados por una barra vertical (`.play-nav-sep`)

Ambos se eliminaron por completo: la barra no aportaba navegación extra (el layout general ya la ofrece) y quitaba espacio vertical al emulador.

### Qué no se eliminó
- El botón **"Volver al catálogo"** de la pantalla de error (`.btn-back-catalog`, usado en la vista PHP y en el render dinámico JS) se conserva: es parte del diagnóstico de error y no está junto al título.
- El resto de la página de emulador y sus estilos quedan intactos.

## Verificación
- Revisión estática: las clases `.play-nav`, `.btn-back`, `.play-nav-sep`, `.play-nav-title` y `.play-nav-platform` ya no se referencian en ninguna vista; `.btn-back-catalog` sigue presente en `play.php`.
- En producción: abrir la vista de jugar online y confirmar que ya no hay barra superior; forzar un error (archivo no encontrado) para confirmar que la pantalla de error conserva su botón.
