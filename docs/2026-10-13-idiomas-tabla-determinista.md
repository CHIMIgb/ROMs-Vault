# Idiomas de juegos: tabla determinista (corrección de calidad)

**Fecha:** 2026-10-13
**Rama:** `feature/i18n-traduccion-contenido-bd`
**Ámbito:** i18n / traducción de contenido (`juegos_traducciones.idiomas`)

## 1. Motivación

El backfill de `juegos_traducciones.idiomas` (doc `2026-10-13-backfill-idiomas-juegos.md`)
usaba el **proveedor de traducción** enviando la lista de idiomas
como una cadena con delimitador `" / "`. Verificado en producción, ese enfoque
produce **dos clases de error** en un vocabulario corto y cerrado:

1. **Token único (alucinación).** Los 44 juegos con `idiomas = 'Inglés'`
   (y 9 con `'Español'`) se corrompían al traducir un solo nombre sin contexto:

   | destino | valor guardado (incorrecto) |
   |---|---|
   | fr | `Changements climatiques` |
   | tr | `İngilizce İngilizce İngilizce English` |
   | ja | `日本語 English` |
   | zh | `联合国` |

2. **Listas cortas (duplicación / pérdida / reordenación).** Con 2-3 tokens el
   proveedor intercambia el orden o duplica (`İngilizce, Fransızca, Almanca, Almanca`
   para `Inglés, Francés, Alemán`). Con los 12 nombres canónicos en una sola
   petición llegó a **perder el primer ítem** y a duplicar (`Noruego, Noruego`).

Se comprobó además que **incluso traduciendo cada nombre individualmente con el
prefijo `"Idioma: "`** el proveedor falla: en turco devuelve **países**
(`Hollanda`, `İsveç`, `Danimarka`, `Finlandiya`, `Norveç` en vez de
Felemenkçe/İsveççe/Danca/Fince/Norveççe) y en ja/ko pierde el sufijo `語/어`
(`ロシア`, `네덜란드`).

**Conclusión:** un vocabulario cerrado no debe traducirse con un servicio ML.
La decisión de guardar el valor traducido en la BD se mantiene; lo que cambia es
**cómo se produce**: una tabla determinista en lugar del proveedor.

## 2. Solución

Nueva clase `src/config/IdiomasTranslations.php`:

- `MAP[canónicoEs][idioma]` con los **12 nombres canónicos** (Español, Inglés,
  Francés, Alemán, Italiano, Japonés, Ruso, Holandés, Sueco, Danés, Finlandés,
  Noruego) en los **11 idiomas destino** (en, fr, de, it, pt, ru, tr, ja, zh, ko, ar).
  Estilo de etiqueta (inicial en mayúscula) para que los chips sean homogéneos.
- `PARCHE[idioma]` con la palabra `parche` localizada (sufijo `(parche)`).
- `traducir(?string $origenEs, string $target): ?string`:
  - Normaliza y **deduplica** con `IdiomasNormalizer::tokens()` (descarta basura).
  - **Preserva el orden** de aparición del origen.
  - Token conocido -> nombre localizado; **token desconocido -> se conserva tal cual**
    (no se inventa).
  - `es` o idioma no soportado -> origen normalizado.
  - Sin red, sin proveedor, totalmente determinista.

Integración en `TraduccionService`:

- `traducirCampos()`: el campo `idiomas` se resuelve con la tabla y **NO se envía
  al proveedor**; el resto de campos (p. ej. `descripcion`) siguen yendo al
  proveedor. Si no queda nada que enviar, **no se llama** al proveedor.
- `generar()`: la exigencia de proveedor configurado solo aplica si hay campos
  que dependan de él (`requiereProveedor()`); el backfill de solo `idiomas` ya no
  necesita red.
- `traducirEntidad()` y `traducirIdiomasJuego()` aceptan `bool $forzar = false`
  para re-generar aunque el `origen_hash` coincida (sigue respetando `manual`).

CLI `scripts/backfill-traducciones.php`: nueva opción **`--forzar`**.

## 3. Archivos

```
[CREAR]     src/config/IdiomasTranslations.php
[MODIFICAR] src/config/TraduccionService.php
[MODIFICAR] scripts/backfill-traducciones.php
[CREAR]     tests/Unit/IdiomasTranslationsTest.php
[MODIFICAR] tests/Unit/TraduccionServiceCamposTest.php
[MODIFICAR] tests/Integration/TraducirIdiomasJuegoTest.php
```

## 4. Verificación

- `php -l` sin errores (6 archivos).
- **Unit: 133 OK** (antes 119): +10 de `IdiomasTranslationsTest`, `TraduccionServiceCamposTest`
  reescrito (idiomas no viaja al proveedor).
- **Integration: 78 OK** (antes 76): +`Real sin proveedor escribe idiomas con la tabla`,
  +`Forzar regenera aunque el hash coincida`.
- Regeneración real **offline** (sin proveedor de traducción):
  `php scripts/backfill-traducciones.php --campo=idiomas --forzar`
  -> **1331 filas escritas, 0 errores** (121 juegos x 11 idiomas).
- Comprobación en BD tras el backfill:

  | comprobación | resultado |
  |---|---|
  | filas con idiomas por idioma | 121 en cada uno de los 11 |
  | fuga de español en filas no-`es` | 0 |
  | alucinaciones conocidas | 0 |
  | duplicados adyacentes | 0 |

- Spot-check `idiomas = 'Inglés'` -> `English, Anglais, İngilizce, 英語, 英语, 영어,
  الإنجليزية, Английский, Englisch, Inglese, Inglês` (correcto en los 11).
- Spot-check 5 idiomas: orden preservado en todos. `(parche)` localizado
  (`Spanish (patch)`, `スペイン語 (パッチ)`, `İspanyolca (yama)`...).
- Smoke UI (`/juego/carnivores-dinosaur-hunter`, 12 idiomas): **1 chip** por
  idioma con el valor correcto.

## 5. Regenerar en producción

1. Desplegar el código (incluye `IdiomasTranslations.php`).
2. Ejecutar una vez, contra la BD de producción:

   ```
   php scripts/backfill-traducciones.php --campo=idiomas --forzar
   ```

   Es offline y rápido (no requiere `TRANSLATE_*`). Las filas `origen='manual'`
   nunca se tocan.

## 6. Gotchas

- Un cambio de **método** no invalida `origen_hash` (depende solo del texto
  origen en español); por eso la corrección necesita `--forzar` o resetear
  `idiomas`/`origen_hash`.
- Si en el futuro se añade un idioma canónico nuevo, hay que añadirlo al `MAP`
  de `IdiomasNormalizer` **y** a la tabla `MAP` de `IdiomasTranslations` en los
  11 destinos.
