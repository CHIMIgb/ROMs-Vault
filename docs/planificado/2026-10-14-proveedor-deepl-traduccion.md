# Proveedor de traducción DeepL (API Free) en lugar de LibreTranslate

**Fecha:** 2026-10-14
**Estado:** planificado (pendiente de implementación y aprobación)
**Ámbito:** i18n (traducción de contenido, bloque 2)
**Sustituye a:** `docs/planificado/2026-10-14-migracion-libretranslate-a-servidor.md`
(descartado: Oracle bloqueó la verificación de tarjeta y los PaaS gratuitos no
sirven para LibreTranslate por disco efímero + modelos).
**Documentos relacionados:**
`src/config/translation/TranslateProviderFactory.php`,
`src/config/TraduccionService.php`,
`docs/2026-10-13-configuracion-libretranslate-memoria-y-backfill.md`,
`docs/planificado/2026-10-14-guardado-asincrono-cola-bd-worker.md`

---

## 1. Resumen del cambio

Cambiar el proveedor de traducción de **LibreTranslate auto-hospedado** a
**DeepL API Free**: sin servidor, sin Docker y sin worker. La traducción
funciona **directamente desde Vercel** (una llamada HTTPS por idioma destino).

Motivo: no fue posible dar de alta Oracle Cloud (bloqueo de tarjeta) y los PaaS
gratuitos no pueden mantener LibreTranslate (disco efímero: los modelos se
re-descargan, 512 MB de RAM, auto-sleep). DeepL Free ofrece
**500.000 caracteres/mes sin tarjeta**.

---

## 2. Qué cambia respecto a LibreTranslate

| Aspecto | LibreTranslate (antes) | DeepL Free (ahora) |
|---|---|---|
| Servidor | Docker local / VM | **Ninguno** (SaaS) |
| Imagen Docker | `romv-libretranslate:models` (6.5 GB) | **No se necesita** |
| Memoria | 140–480 MB | No aplica |
| Endpoint | `http://localhost:5000` | `https://api-free.deepl.com` |
| Auth | opcional (API key) | **obligatoria** (`DeepL-Auth-Key`) |
| Cuota | ilimitada (propia) | 500.000 caracteres/mes |
| Idiomas a precargar | 12 | No aplica |

---

## 3. Cobertura de idiomas (los 11 destino)

| App | DeepL | Idioma |
|---|---|---|
| `en` | `EN` | Inglés |
| `fr` | `FR` | Francés |
| `pt` | `PT` | Portugués |
| `de` | `DE` | Alemán |
| `it` | `IT` | Italiano |
| `ru` | `RU` | Ruso |
| `tr` | `TR` | Turco |
| `ja` | `JA` | Japonés |
| `zh` | `ZH-HANS` | Chino simplificado |
| `ko` | `KO` | Coreano |
| `ar` | `AR` | Árabe |

Origen `es` → `ES`. **Los 11 idiomas están cubiertos** por DeepL.

---

## 4. Qué se envía y qué NO

- **NO** se envía el campo `idiomas` de los juegos: se resuelve con la tabla
  determinista `IdiomasTranslations` (offline, sin proveedor).
- Solo van a DeepL: `descripcion` (juego) y `nombre` + `descripcion`
  (categoría).
- El flujo de guardado sigue siendo **síncrono** (como hoy): no hace falta
  worker. La cola asíncrona (`2026-10-14-guardado-asincrono-...`) queda como
  mejora **opcional**.

---

## 5. Archivos afectados

```
[CREAR]     src/config/translation/DeepLTranslateProvider.php
  - Implementa request() contra api-free.deepl.com/v2/translate con la cabecera
    Authorization: DeepL-Auth-Key; mapea mapCode() (MAYÚSCULAS, zh->ZH-HANS)

[MODIFICAR] src/config/translation/TranslateProviderFactory.php
  - case 'deepl': new DeepLTranslateProvider($apiKey, $timeout)
  - soportados(): añadir 'deepl'

[MODIFICAR] src/config/translation/AbstractTranslateProvider.php
  - Permitir cabeceras extra en el POST (o que DeepL haga su propio cURL con auth)

[MODIFICAR] docker-entrypoint.sh
  - Pasar al .env del contenedor: TRANSLATE_PROVIDER, TRANSLATE_API_KEY,
    TRANSLATE_TIMEOUT (habilita el runtime en Vercel)

[MODIFICAR] .env.example
  - Documentar 'deepl' como valor de TRANSLATE_PROVIDER y su API key

[CREAR]     tests/Unit/DeepLTranslateProviderTest.php
  - mapCode() y mapeo de la respuesta {translations:[{text}]} (sin red)

[ELIMINAR]  docs/planificado/2026-10-14-migracion-libretranslate-a-servidor.md
  - Reemplazado por este documento

[CREAR]     docs/planificado/2026-10-14-proveedor-deepl-traduccion.md
  - Este documento
```

---

## 6. Detalles de la integración

- **Endpoint:** `POST https://api-free.deepl.com/v2/translate`
  (Free usa `api-free.deepl.com`; las claves Free terminan en `:fx`).
- **Cabecera:** `Authorization: DeepL-Auth-Key <API_KEY>`
- **Cuerpo JSON:**
  ```json
  { "text": ["...", "..."], "target_lang": "EN", "source_lang": "ES" }
  ```
- **Respuesta:** `{ "translations": [ { "detected_source_language": "ES",
  "text": "..." } ] }` → el proveedor extrae los `text` **en orden**.
- **Códigos:** DeepL usa MAYÚSCULAS; `mapCode()` debe convertir `en`->`EN`,
  `zh`->`ZH-HANS`, etc. `source_lang=ES`.
- **Límites:** máx. **50 textos por petición** (aquí se envían 1–2), timeout
  `TRANSLATE_TIMEOUT` (default 8 s).
- **Errores:** `429`/`5xx` → reintentar con backoff; `456` → **cuota agotada**
  (no reintentar); `400` → petición inválida. Ante error, el flujo actual
  **degrada** (fail-open) y registra `traduccion_error`.

### Cuota (500.000 caracteres/mes)

DeepL cobra por **caracteres de origen enviados**, y cada idioma destino
cuenta aparte. Estimación: si una descripción tiene ~300 caracteres,
traducirla a 11 idiomas cuesta ~3.300 caracteres por juego.

- Guardados: ~150 por mes dentro de la cuota.
- **Backfill inicial**: `nº de juegos × caracteres × 11`. Para ~100 juegos con
  descripciones cortas cabe en un mes; si el catálogo es grande, repartirlo en
  varios meses (el incremental por `origen_hash` evita repetir).
- El backfill de `idiomas` **no consume cuota** (tabla determinista).

---

## 7. ¿Se sigue necesitando la imagen Docker de LibreTranslate?

**No.** Con DeepL la traducción sale por HTTPS a la nube, así que:

- **No** necesitas el contenedor ni la imagen `romv-libretranslate:models`
  (puedes borrarla: libera ~6.5 GB).
- **No** necesitas ningún worker ni servidor.
- El código de `LibreTranslateProvider` **se conserva** (no se borra): sigue
  disponible como respaldo si algún día quieres volver a LT local (p. ej. si se
  agota la cuota de DeepL, cambiando `TRANSLATE_PROVIDER=libretranslate`).

---

## 8. Variables, tokens y dependencias

- `.env` / Vercel: `TRANSLATE_PROVIDER=deepl`, `TRANSLATE_API_KEY=<clave>`,
  `TRANSLATE_TIMEOUT=8` (opcional: `TRANSLATE_TARGETS`).
- `TRANSLATE_LIBRE_URL` deja de usarse (puede quedar vacío).
- **Sin dependencias nuevas** (se usa cURL, ya presente).
- La **clave de DeepL es un secreto**: solo en `.env` local y en las variables
  de entorno de Vercel; jamás en el repo.

---

## 9. Orden de ejecución

1. `DeepLTranslateProvider` + `mapCode()` + mapeo de respuesta.
2. `AbstractTranslateProvider`: soporte de cabecera de auth.
3. `TranslateProviderFactory`: `case 'deepl'` + `soportados()`.
4. `docker-entrypoint.sh` + `.env.example`.
5. Test unitario del proveedor (sin red).
6. Crear la cuenta DeepL Free, obtener la API key y ponerla en Vercel.
7. Re-desplegar y probar el guardado.
8. Eliminar el doc de LibreTranslate y actualizar docs.

---

## 10. Verificación

- `vendor/bin/phpunit --testsuite Unit --filter DeepL` en verde.
- Guardar un juego en admin → `descripcion` traducida a los 11 idiomas y
  guardada en `juegos_traducciones` (visible al cambiar de idioma en la web).
- Guardar una categoría → `nombre` + `descripcion` traducidos.
- Con `TRANSLATE_PROVIDER=none` (o clave inválida) el guardado **no se rompe**:
  registra `traduccion_error` y sigue.
- `php scripts/backfill-traducciones.php --dry-run` (solo `descripcion`) contra
  DeepL → totales y consumo coherentes.

---

## 11. Impacto y compatibilidad

- **No** se tocan URLs públicas, CSP ni `vercel.json` (`rewrites: []`).
- Sin cambios de esquema ni de BD.
- Con `TRANSLATE_PROVIDER=none` (default) el comportamiento actual no cambia.
- El Docker local de LT deja de ser necesario para traducir.

---

## 12. Gotchas

- DeepL **solo** acepta `text`/`target_lang` por JSON con la cabecera de auth;
  el helper `postJson()` actual no envía cabeceras extra.
- Códigos en **MAYÚSCULAS**; `zh` debe ir como `ZH-HANS`.
- `456` = cuota agotada: no reintentar; avisar en logs.
- **Los textos salen de tu infraestructura** (privacidad) y dependes de un
  tercero: era el compromiso aceptado de la Opción 2.
- La API key Free de DeepL es un secreto (no confundir con `TRANSLATE_LIBRE_API_KEY`).

---

## 13. Estado

- [ ] Crear `DeepLTranslateProvider` + `mapCode()`.
- [ ] Extender `AbstractTranslateProvider` (cabeceras).
- [ ] Registrar `deepl` en la factory.
- [ ] `docker-entrypoint.sh` + `.env.example`.
- [ ] Test unitario.
- [ ] Alta en DeepL + API key en Vercel.
- [ ] Activar y probar en producción.
- [ ] Eliminar el doc de LibreTranslate y actualizar docs.
