# `composer audit` como paso previo a commit/deploy (ítem 9.5)

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§9.5)

---

## 1. Qué se ha hecho

Comprobar que **`composer audit`** funciona y documentarlo como paso previo al
commit y al deploy. Las dependencias (`composer.json` + `composer.lock`
trackeado) reportan **cero advisories de seguridad**:

```
$ composer audit
No security vulnerability advisories found.
```

## 2. Decisión: no se añade script en `composer.json`

Hallazgo en la ejecución: **`audit` ya es un comando nativo de Composer (2.4+)**.
Añadir un script llamado `"audit"` en `scripts` **se ignora** con el aviso *"A
script named audit would override a Composer command and has been skipped"*, lo
que ensuciaría cada `composer validate/run`.

**Decisión del usuario: Opción A** → sin script. El comando se usa directamente:

- **Local / pre-commit:** `composer audit`
- **Deploy / CI (FASE 2 del roadmap):** correrlo en el job `audit` de GitHub
  Actions junto a `composer update --dry-run` y, si reporta vulnerabilidades,
  fallar el pipeline antes de construir la imagen.

Dependabot y GitHub Actions quedan anotados como **pendientes de FASE 2** (no
pertenecen a esta rama).

## 3. Estado de la deuda de esquema (no tocar en este ítem)

`composer validate` muestra un warning preexistente: *"No license specified"*.
Es independiente del ítem 9.5 y no bloquea nada; si se quiere cerrar, se hará
en un ítem separado (decisión de licencia del proyecto).

## 4. Verificación (evidencia)

- `composer validate --no-check-publish` → válido (solo el warning de license,
  preexistente).
- `composer audit` → `No security vulnerability advisories found.` (exit 0).
- `composer test` → verde defendiendo que no hay cambios de código.