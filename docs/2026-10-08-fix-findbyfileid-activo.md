# Corrección del hallazgo de publicación en `findByFileId()` — §9

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Origen:** [Hallazgo 1 de la auditoría IDOR (2.3)](2026-10-08-auditoria-idor.md)
**Ítem del roadmap:** [Sección 9 — hallazgos adicionales](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)

## Problema

`Juego::findByFileId()` no filtraba `j.activo = true`:
- `home/play?file_id=…` (sin firma) permitía **jugar un juego desactivado** con una URL
  conocida y además ejecutaba `incrementPlays()` (métricas de contenido no publicado).
- `home/download?file_id=&t=&sig=` (firma válida) permitía **descargar un juego
  desactivado** cuya firma se generó mientras estaba visible.

`home/show()` ya filtraba activos (`findWithDetails`); el panel admin usa `find($id)`,
así que no se toca el listado de inactivos del dashboard.

## Cambio

`src/models/Juego.php` — `findByFileId()`:

```sql
WHERE j.google_drive_file_id = ? AND j.activo = true
```

Tras el cambio, un juego inactivo no es jugable ni descargable (`play()` responde 404
"juego no existe"; `download()` 404 "archivo no encontrado").

## Verificación

- Test de regresión nuevo: `tests/Integration/JuegoModelTest.php` (juego activo →
  encontrado; juego inactivo → `false`). Suite completa **123 tests / 320 assertions OK**.