# Retención de imágenes en Vercel Container Registry (VCR)

- **Fecha:** 2026-10-11
- **Ámbito:** Deploy / infraestructura (Vercel Container Runtime + VCR)
- **Tipo:** Incidente resuelto + herramienta de mantenimiento

## Contexto

El proyecto `roms-vault` desplegado en Vercel usa el **Container Runtime (Beta)**:
Vercel construye el `Dockerfile` del repo y **empuja la imagen al Vercel Container
Registry (VCR)** con el tag del commit (`vcr.vercel.com/odst1/roms-vault/dockerfile:<sha>`).
Cada deploy —**incluidos los Preview**— genera y sube una imagen nueva.

Vercel **no** aplica retención automática: las imágenes se acumulan indefinidamente.
El plan **Hobby** limita a **50 imágenes por repositorio** (Pro: 10,000).

## Incidente (2026-10-11)

Un deploy de Preview de la rama `pendientes-9.2-y-restantes` (commit `c3b9ccd`) falló.
El build de Docker terminó bien (14/14 pasos), pero la fase de push al registry fue
rechazada. El mensaje-resumen de Vercel era engañoso ("verify access… or create the
repository"); el **error real** estaba en la salida de Docker:

```
denied: repository has reached the maximum allowed number of images
```

### Causa raíz

El repositorio VCR `dockerfile` había alcanzado su tope: **50/50 imágenes**
(39 con tag de deploy + 11 sin tag, restos de builds anteriores).

### Solución aplicada

Limpieza manual del registry, conservando solo la imagen de producción:

| Antes | Después |
|---|---|
| 50/50 imágenes → push rechazado | 2/50 imágenes |
| Deploy Preview `Error` | `Ready in 1m` |
| Imagen `c3b9ccd824d9` rechazada | Imagen `c3b9ccd824d9` empujada |

1. Diagnóstico (`vercel vcr image ls dockerfile --limit 100`).
2. Borrado de 49 imágenes antiguas (`vercel vcr image rm dockerfile <id> --yes`),
   conservando `image_b4qDkPVAWPQMkcqKUzWTrqQaCE3O` (tag `359356092e16`).
3. `vercel redeploy <deployment>` → Preview `Ready`.

## Herramienta de mantenimiento

Para evitar que el problema se repita, se añade `scripts/prune-vcr-images.ps1`:
conserva las **N imágenes más recientes** y borra el resto.

### Uso

```powershell
# Previsualizar sin borrar (recomendado antes de tocar nada)
./scripts/prune-vcr-images.ps1 -DryRun

# Conservar las 5 más recientes (valor por defecto)
./scripts/prune-vcr-images.ps1

# Conservar solo las 3 más recientes
./scripts/prune-vcr-images.ps1 -Keep 3
```

### Parámetros y variables de entorno

| Parámetro | Variable de entorno | Defecto | Descripción |
|---|---|---|---|
| `-Repo` | `VCR_REPO` | `dockerfile` | Repositorio VCR a purgar |
| `-Keep` | `VCR_KEEP` | `5` | Imágenes recientes a conservar |
| `-Project` | `VERCEL_PROJECT` | (vacío) | Proyecto Vercel (en local usa `.vercel`) |
| `-Scope` | `VERCEL_SCOPE` | (vacío) | Team/slug de Vercel (necesario en CI) |
| `-Token` | `VERCEL_TOKEN` | (vacío) | Token de Vercel (en local usa la sesión) |
| `-DryRun` | — | `false` | Muestra qué se borraría sin borrar |

- El script fija `CI=1` para evitar el prompt interactivo de "actualizar Vercel CLI".
- Localiza la raíz del repo por sí mismo (usa el `.vercel/` linkeado), así que puede
  invocarse desde cualquier directorio.
- Es **idempotente**: si hay ≤ `Keep` imágenes, no hace nada y sale con código 0.
- Termina con código 1 si algún borrado falla (útil para CI).

### Uso en CI (opcional, no incluido)

Podría añadirse un workflow programado (cron semanal) que ejecute el script con un
secret `VERCEL_TOKEN`:

```powershell
$env:VERCEL_TOKEN = ${{ secrets.VERCEL_TOKEN }}
./scripts/prune-vcr-images.ps1 -Project roms-vault -Scope odst1 -Keep 5
```

Requiere crear un token de Vercel con permiso de borrado sobre VCR y registrarlo como
secret. **No forma parte de este cambio**; se planificaría como tarea aparte.

## Notas

- Borrar imágenes **no** afecta a los deploys en ejecución; solo elimina la capacidad
  de *rollback* a esa imagen concreta (Vercel la reconstruye desde git si hace falta).
- El nombre del repositorio VCR (`dockerfile`) deriva del `Dockerfile`; si se renombra,
  ajustar `-Repo`.
- Referencia: <https://vercel.com/docs/container-registry> y
  <https://vercel.com/docs/container-registry/cli-reference>.
