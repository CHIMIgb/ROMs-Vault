# No mostrar errores en producción — ítem 2.4 (OWASP A05)

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [2.4 No mostrar errores en producción (A05)](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)

## Cambio

```
[CREAR] docker/php-production.ini
[MODIFICAR] Dockerfile  → COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
```

Preferido el archivo `conf.d` (SAPI-independiente: Apache mod_php y también FPM/CGI si
cambiara) frente a `php_value` en `.htaccess` (riesgo de 500 en otros SAPIs).

| Directiva | Valor | Efecto |
|---|---|---|
| `display_errors` | `Off` | nunca se exponen errores al cliente |
| `error_reporting` | `E_ALL` | los errores siguen registrándose (logs) |
| `log_errors` | `On` | vuelcan a error_log (Apache) |
| `expose_php` | `Off` | oculta versión de PHP en headers |

## Impacto

- Solo afecta a la **imagen Docker** (`php:8.2-apache`). El dev local (`php -S
  localhost:8000 router.php`) no lee `conf.d` → sigue mostrando errores para depurar.
- No cambia cabeceras de seguridad ni URLs; `/src/*` sigue bloqueado por `router.php`
  y `.htaccess`.

## Verificación

- Sintaxis INI validada (`parse_ini_file` OK).
- Build pendiente de confirmar con Docker Desktop (no disponible en WSL): el pipeline /
  compilación local lo valida al construir la imagen.