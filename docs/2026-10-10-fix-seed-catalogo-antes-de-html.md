# Fix: seed del catálogo calculada antes de emitir HTML (ítem 9.1)

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Commit de código:** `2a76ff0`
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§9.1)

---

## 1. Problema

`src/views/components/related_games.php` llamaba a `Juego::getCatalogSeed()`
durante el render. Ese método ejecuta `setcookie()` (`src/models/Juego.php:142`)
cuando el visitante no trae la cookie `rv_cat_seed`; como el componente se
incluye **después** de `layout/header.php`, la cabecera ya se había emitido y
PHP lanzaba:

```
PHP Warning: Cannot modify header information - headers already sent by
(output started at .../src/views/layout/header.php:173)
in .../src/models/Juego.php on line 142
```

Efecto colateral: la cookie `rv_cat_seed` **no se llegaba a emitir** en la
primera visita, por lo que el orden "aleatorio estable" no se fijaba.

### Corrección al plan

El plan ubicaba el problema en `/home/show/1` y en `show.php:56`. En realidad el
componente `related_games.php` se incluye desde **dos** sitios:

- `src/views/home/show.php:300` (ficha de juego)
- `src/views/home/play.php:498` (página del emulador)

Por tanto el warning afectaba a **ambos** flujos, no solo a la ficha.

---

## 2. Solución

Calcular la seed en el **controlador**, antes de emitir cualquier HTML, y
pasarla a la vista:

- `HomeController::show()` → `$catalogSeed = Juego::getCatalogSeed();` justo
  antes de `require_once layout/header.php`.
- `HomeController::play()` → ídem antes del header de la ruta de éxito. Las
  rutas de error tempranas (sin `file_id` / juego inexistente) no renderizan
  recomendados, porque el guard `!empty($juego['id'])` de `related_games.php` no
  se cumple.
- `related_games.php` → usa `$catalogSeed ?? Juego::getCatalogSeed()` (el
  fallback solo aplica si la vista se incluye sin controlador).

Se descartó `ob_start()` (solución menos explícita que el fix documentado).

---

## 3. Archivos modificados

| Acción | Archivo | Cambio |
|--------|---------|--------|
| MODIFICAR | `src/controllers/HomeController.php` | `$catalogSeed` en `show()` y `play()` antes del header |
| MODIFICAR | `src/views/components/related_games.php` | `$catalogSeed ?? Juego::getCatalogSeed()` |

---

## 4. Verificación (evidencia)

- `php -l` de `HomeController.php` y `related_games.php` → sin errores.
- Smoke con `php -S localhost:8137 router.php`, petición **sin** cookie
  `rv_cat_seed`:
  - **Antes del fix** (`git stash`): `STATUS=200`, `COOKIE_SEED=False` y warning
    `Cannot modify header information - headers already sent ... Juego.php:142`.
  - **Con el fix:** `STATUS=200`, `COOKIE_SEED=True`, sin warning.
- `composer test` → **OK (166 tests, 7489 assertions)** (sin warnings nuevos).
