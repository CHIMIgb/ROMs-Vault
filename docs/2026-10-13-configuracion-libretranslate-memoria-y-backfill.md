# Configuración LibreTranslate (traducción automática de contenido BD)
# Fecha: 2026-10-13
# Estado: Estable con 1 worker (mitiga OOM). Modelos preservados en imagen Docker.

## 1. Resumen

El backfill de traducciones del bloque 2 de i18n se ejecutaba contra LibreTranslate en Docker y sufría `Worker ... SIGKILL! Perhaps out of memory?`. Causa: **4 workers de Gunicorn** cargando los **24 modelos de Argos Translate** simultáneamente (~3.6 GiB RAM). Solución aplicada: relanzar con **`LT_THREADS=1`** preservando los modelos ya descargados (`docker commit`).

## 2. Estado actual (medido)

| Métrica | Valor | Notas |
|---|---|---|
| Contenedor | `libretranslate` (up) | Corriendo con 1 worker |
| Imagen con modelos | `romv-libretranslate:models` | Commit del contenedor tras precarga |
| Tamaño imagen | **6.5 GB** (raíz) | Contiene 2.8 GB de modelos + base |
| Modelos | **24 paquetes** | Pares `es↔en`, `en↔X`, `es↔pt` |
| Uso RAM (reposo) | **~140–480 MiB / 4.76 GiB** | Estable tras cambio a 1 worker |
| Uso RAM (picos traducción) | **< 600 MiB** | Sin OOM |
| Traducción de prueba (`es→en`) | **~0.2 s** | Con modelo ya cargado (cold start mayor) |
| Espacio libre en C: | **~2.26 GB** | Tras limpiar imágenes colgantes/volúmenes |
| Idiomas cargados (`/languages`) | `en, ar, zh-Hans, fr, de, it, ja, ko, pt, ru, es, tr` | 11 destinos + origen |

## 3. Modelos descargados

Ubicación interna: `/home/libretranslate/.local/share/argos-translate/packages/` (2.8 GB)

Paquetes relevantes (12 idiomas):

```text
ar_en, en_ar
de_en, translate-en_de-1_3, translate-de_en-1_3
en_es, translate-es_en-1_9
en_fr, translate-en_fr-1_9, translate-fr_en-1_9
en_it, it_en
en_ja, ja_en
en_ko, ko_en
en_pt, pt_en, translate-en_pt-1_9, translate-pt_en-1_9, es_pt, translate-pt_es-1_0
en_ru, translate-en_ru-1_9, translate-ru_en-1_9
en_tr, translate-en_tr-1_5, translate-tr_en-1_5
en_zh, translate-en_zh-1_9, translate-zh_en-1_9
es_en
```

**Nota importante (código chino):** LibreTranslate devuelve `zh-Hans` para chino simplificado. Se corrigió en `LibreTranslateProvider::mapCode()` (`zh` → `zh-Hans`) para que coincida con lo que expone la API (commit `1f46968`).

## 4. Configuración del contenedor

Parámetros usados al relanzar (preservando modelos):

```powershell
docker run -d --name libretranslate -p 5000:5000 `
  -e LT_LOAD_ONLY=es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar `
  -e LT_THREADS=1 `
  romv-libretranslate:models
```

- `LT_THREADS=1`: reduce RAM drásticamente (evita cargar modelos por worker). Recomendado para hardware con 4–8 GB RAM.
- `LT_LOAD_ONLY`: limita idiomas cargados a los usados por el proyecto (reduce uso de disco/RAM).
- Imagen `romv-libretranslate:models`: contiene los modelos ya descargados (evita re-descarga). Útil con poco espacio libre en disco.

## 5. Por qué `docker commit` (trade-off espacio)

Al hacer `docker commit libretranslate romv-libretranslate:models` se creó una capa extra con el estado del FS (modelos en `/home/libretranslate/.local/...`). Esto añadió **~2.98 GB** de escritura al commit → imagen total **6.5 GB**.

**Decisión justificada:** con **C: ~5.9 GB libres antes del commit** y riesgo de volver a descargar 2.8 GB (más lento/arriesgado si quedaba poco), se optó por **preservar modelos**. Tras limpiar imágenes colgantes (`docker image prune -f`) quedó **~2.26 GB libres**. El sistema es funcional y estable con 1 worker.

**Alternativa (volumen persistente):** usar `-v lt_models:/home/libretranslate/.local` evita inflar la imagen (los modelos viven en volumen). No se aplicó aquí para conservar el estado exacto que ya estaba precargado y validado.

## 6. Backfill de traducciones (CLI)

Script: `scripts/backfill-traducciones.php`

### Características

- **Idempotente**: usa `origen_hash` y respeta `origen='manual'` (nunca pisa traducciones manuales). Reintenta pendientes.
- **Dry-run**: `--dry-run` calcula totales sin escribir.
- **Filtrado**: `--solo=categorias|juegos`, `--idioma=xx`, `--limite=N`
- **Fail-open**: errores se registran vía `LoggerService` → tabla `auditoria` (`accion='traduccion_error'`), no detiene el lote por fallo puntual.
- **CLI-only**: protege con `php_sapi_name() === 'cli'`.

### Ejecución (local, Postgres 127.0.0.1)

El `.env` local **no define `TRANSLATE_*`**. El proveedor se inyecta por entorno al lanzar el script (PowerShell):

```powershell
cd C:\Users\chimi\Documents\GitHub\ROMs-Vault

# Dry-run completo (ver totales)
$env:TRANSLATE_PROVIDER='libretranslate'
$env:TRANSLATE_LIBRE_URL='http://localhost:5000'
C:\xampp\php\php.exe scripts\backfill-traducciones.php --dry-run

# Ejecución real (reanudar desde donde quedó)
$env:TRANSLATE_PROVIDER='libretranslate'
$env:TRANSLATE_LIBRE_URL='http://localhost:5000'
C:\xampp\php\php.exe scripts\backfill-traducciones.php 2>> scratch\backfill-err.log | Tee-Object -FilePath scratch\backfill-out.log
```

**Nota PowerShell:** en bash `$` se escapa con comillas dobles; usar variables `$env:` o escribir en `scratch/*.ps1` (gitignored) tal como se hizo durante la investigación.

### Volumen estimado

- Entidades: 39 categorías + 123 juegos = **162 entidades**
- Destinos: 11 idiomas → **1.782 traducciones pendientes** (dry-run confirmado)
- Ritmo: ~2–3 s por solicitud (depende de cold/warm). Estimado **50–80 min** con 1 worker (estable).

### Reanudar tras OOM/parada

El proceso anterior quedó parado (PHP PID detenido). Al relanzar, el script es **idempotente**: las filas ya creadas con `origen='auto'` y mismo `origen_hash` se omiten (incremental). Los errores registrados en `auditoria` no bloquean reintentos.

## 7. Verificación de salud

```powershell
# Idiomas disponibles
Invoke-RestMethod -Uri 'http://localhost:5000/languages'

# Traducción de prueba
$body = @{ q='Juego de carreras futurista'; source='es'; target='en'; format='text' } | ConvertTo-Json
Invoke-RestMethod -Uri 'http://localhost:5000/translate' -Method Post -ContentType 'application/json' -Body $body
# → {"translatedText":"Future Racing Game"}
```

## 8. Troubleshooting

| Síntoma | Causa | Solución |
|---|---|---|
| `SIGKILL` / workers reiniciándose | OOM (4 workers) | Usar `LT_THREADS=1`. Ver `docker logs libretranslate`. |
| Timeout en primer `translate` | Cold start (modelos cargando tras arranque) | Esperar ~10–30 s tras `docker run` o hacer warm-up con `/languages` + 1 translate. |
| Espacio C: bajo (<1–2 GB) | Imagen 6.5 GB + modelos | Limpiar: `docker image prune -f`, `docker volume prune -f`, eliminar imagen colgante del commit abortado. Considerar migrar modelos a volumen nombrado si se vuelve a recrear. |
| Traduce lento por lotes | 1 worker (gunicorn sync) | Esperado para estabilidad. Con 1 worker evita OOM; rendimiento suficiente para backfill nocturno. |
| `zh` vs `zh-Hans` | API expone `zh-Hans` | Corregido en `LibreTranslateProvider::mapCode()` (mapear `zh` → `zh-Hans`). |

## 9. Referencias

- `src/config/translation/LibreTranslateProvider.php` (mapCode zh→zh-Hans)
- `src/config/TraduccionService.php` (`traducirEntidad(..., bool $dryRun)`, fail-open)
- `src/models/Traduccion.php` (UPSERT respeta `origen='manual'`, incremental)
- `scripts/backfill-traducciones.php` (CLI, idempotente)
- Migración: `data/migrations/2026-10-12-traducciones-contenido.sql`
- Plan original: `docs/_archivo/2026-10-12-plan-traduccion-contenido-bd.md`

## 10. Conclusión

Estado **estable** para completar el backfill: memoria controlada (1 worker), modelos preservados y proceso idempotente. Recomendado reanudar el lote con la configuración actual. No es necesario aumentar memoria de Docker Desktop mientras se mantenga `LT_THREADS=1`.
