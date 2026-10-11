# ADR: Routing por convención, sin tabla de rutas en la base de datos

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Estado:** Aceptado
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§7.1)

---

## Contexto

ROMs-Vault enruta con convención en `index.php`: `controller/action/id`
(URLs limpias) y legacy `index.php?controller=X&action=Y&id=Z`. El router
sanitiza con regex alfanumérica estricta (`index.php:77`) antes de instanciar
el controller (`src/controllers/{Controlador}Controller.php`).

Se plantea como mejora "tabla de rutas en BD": almacenar URL → controlador/acción
en una tabla y resolver según el path.

## Decisión

**No** usar una tabla de rutas en base de datos. Se mantiene el routing por
convención + regex estricta en `index.php`.

### Razones

1. **Superficie de ejecución arbitraria:** una tabla de rutas en BD permite que
   *cualquier fila* sea un handler (`/ruta → Controlador::accion`). Un registro
   malicioso o un SQLi parcialmente mitigado podrían llegar a invocar
   controladores/acciones no previstas — supera el riesgo de RCE frente al
   whitelist de controllers por convención.
2. **Coste por petición:** cada request añadiría una query + caché; en un router
   por convención la resolución es local y predecible (un solo archivo).
3. **Legibilidad/auditabilidad:** el mapa de rutas de la app está en el código
   (un vistazo a `src/controllers/`), no oculto en datos.
4. **Consistencia con el stack canónico:** vanilla, sin ORM ni framework;
   inyectar reglas dinámicas desde BD contradice el diseño.

## Alternativas consideradas

| Alternativa | Veredicto |
|-------------|-----------|
| Tabla de rutas en BD | Descartada: ver razones anteriores (RCE, query/caché, opacidad). |
| Archivo `routes.php` en PHP | Descartada por ahora: el router por convención ya cubre el caso; un archivo explícito se reevaluará si el proyecto crece (FASE 2). |
| Framework/router externo | Descartada: prohibido por las reglas del proyecto (stack vanilla). |

## Consecuencias

- Se mantiene la API de URLs actual (limpia + legacy); `vercel.json` conserva
  `"rewrites": []` y el enrutado vive en `.htaccess` / `router.php`.
- La evolución planificada es **7.2: slugs de contenido** (`juegos.slug` /
  `consolas.slug`, dato del registro) para URLs tipo `/juego/mario-bros`, sin
  tabla de rutas — el slug identifica el recurso, el controlador se resuelve
  por convención.