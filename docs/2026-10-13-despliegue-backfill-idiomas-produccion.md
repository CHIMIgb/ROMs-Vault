# Despliegue: backfill de idiomas en producción (paso pendiente)

**Fecha:** 2026-10-13
**Ámbito:** i18n (bloque 2) - `juegos_traducciones.idiomas`
**Estado:** pendiente (se ejecuta **una vez**, tras desplegar el código)
**Origen:** `docs/2026-10-13-idiomas-tabla-determinista.md` (sección 5) y
`docs/2026-10-13-backfill-idiomas-juegos.md` (sección 5)

---

## 1. Contexto

La traducción de `juegos_traducciones.idiomas` se genera con una **tabla
determinista** (`src/config/IdiomasTranslations.php`), no con el proveedor ML.
Es **offline**: no necesita `TRANSLATE_*` ni LibreTranslate. En local ya se
ejecutó y quedó corregida; **en producción (Neon) queda pendiente** tras el
despliegue del código que incluye la tabla determinista.

El modo `--campo=idiomas --forzar` **re-genera** el campo `idiomas` aunque el
`origen_hash` ya coincida (necesario porque un cambio de método no invalida el
hash), **sin re-traducir** `descripcion` y **sin pisar** filas `origen='manual'`.

---

## 2. Prerrequisitos (en orden)

1. **Código desplegado** con `src/config/IdiomasTranslations.php` (commit de
   `docs/2026-10-13-idiomas-tabla-determinista.md`).
2. **Migración aplicada a la BD de producción (Neon)**:
   `data/migrations/2026-10-13-idiomas-en-juegos-traducciones.sql`
   (aditiva: `ALTER TABLE ... ADD COLUMN IF NOT EXISTS idiomas TEXT`).
   Si `juegos_traducciones` no existe aún en Neon, aplicar antes
   `data/migrations/2026-10-12-traducciones-contenido.sql`.
3. **Origen normalizado** (idempotente), para que el vocabulario sea canónico:
   `php scripts/normalizar-idiomas.php` contra producción (o `--dry-run` antes).
4. **Máquina de ejecución:** un **clon del repositorio** con PHP 8.2 + Composer
   y un `.env` con `DB_*` de **Neon** (`DB_SSLMODE=require`).
   **No se puede lanzar dentro del contenedor**: `.dockerignore` excluye
   `scripts/`, por lo que los scripts no están en la imagen.
5. **Respaldo rápido** (opcional pero recomendado):
   `SELECT id, idioma, idiomas, origen FROM juegos_traducciones;` exportado a CSV.

---

## 3. Comando

```bash
# Desde el clon del repo, con .env apuntando a Neon
php scripts/backfill-traducciones.php --campo=idiomas --forzar
```

- `--campo=idiomas` fuerza `--solo=juegos` y usa la tabla determinista; no llama
  al proveedor.
- `--forzar` regenera aunque el hash coincida (corrige filas escritas por el
  método anterior).
- Antes se puede previsualizar con `--dry-run` (no escribe).

En Windows: `C:\xampp\php\php.exe scripts\backfill-traducciones.php --campo=idiomas --forzar`.

---

## 4. Salida esperada

```
== Backfill de traducciones (i18n, bloque 2) ==
Proveedor : n/a (tabla idiomas)
Destino   : en, fr, de, it, pt, ru, tr, ja, zh, ko, ar (11)
Modo      : REAL + FORZAR
  juego  #... -> 11 OK
...
--- Resumen ---
Categorías : 0 traducción(es) escritas
Juegos     : 1331 traducción(es) escritas
Errores    : 0 (entidades: 0)
Total      : 1331 traducción(es) escritas.
```

- **1331 filas** = 121 juegos con idiomas x 11 idiomas destino.
- **0 errores**. Código de salida 0.
- ~7 min en local; en producción depende de la latencia a Neon.

---

## 5. Verificación (contra Neon)

```sql
-- 1) Filas con idiomas rellenos
SELECT count(*) FROM juegos_traducciones
 WHERE idiomas IS NOT NULL AND btrim(idiomas) <> '';
-- Esperado: 1331

-- 2) Reparto homogéneo por idioma
SELECT idioma, count(*) FROM juegos_traducciones
 WHERE idiomas IS NOT NULL AND btrim(idiomas) <> ''
 GROUP BY idioma ORDER BY idioma;
-- Esperado: 121 en cada uno de los 11 idiomas

-- 3) La descripción queda intacta
SELECT count(*) FROM juegos_traducciones
 WHERE descripcion IS NULL OR btrim(descripcion) = '';
-- Esperado: 0

-- 4) Sin fuga de español en filas no-'es'
SELECT count(*) FROM juegos_traducciones
 WHERE idioma <> 'es' AND idiomas ILIKE '%español%';
-- Esperado: 0

-- 5) Spot-check: juego con idiomas = 'Inglés'
SELECT idioma, idiomas FROM juegos_traducciones
 WHERE entidad_id = :id
 ORDER BY idioma;
```

Además, smoke en la UI: abrir una ficha de juego y comprobar que los chips de
idiomas se muestran localizados y separados en varios idiomas (es/ja/zh/ar/ko).

---

## 6. Idempotencia

- Segunda ejecución con `--dry-run`: **0 pendientes**.
- Segunda ejecución real (sin `--forzar`): **0 escritas** (el `origen_hash`
  coincide). Con `--forzar` vuelve a escribir las mismas 1331 filas (mismo
  contenido), por lo que reejecutar es seguro.
- Las filas `origen='manual'` **nunca** se tocan.

---

## 7. Rollback

Cambio **no destructivo** (solo rellena/corrige `idiomas`; no toca `descripcion`
ni filas manuales). Para revertir:

```sql
UPDATE juegos_traducciones SET idiomas = NULL;  -- opcional
```

Gotcha: si se resetea `idiomas`, resetear también `origen_hash` de esas filas;
de lo contrario el incremental las salta (ver
`docs/2026-10-13-idiomas-tabla-determinista.md`, sección 6).

---

## 8. Gotchas

- **Sin red**: usar el proveedor es innecesario; si `TRANSLATE_PROVIDER=none`
  (default) el comando funciona igual.
- **No lanzar desde el contenedor**: `scripts/` no está en la imagen
  (`.dockerignore`). Ejecutar desde un clon.
- **Neon**: `DB_SSLMODE=require` (o vacío, que cae al default `require`).
- **`--forzar` es necesario** para corregir filas ya generadas por el método
  anterior (un cambio de método no invalida `origen_hash`).
- **Respeto a `manual`**: si un juego tiene `origen='manual'`, su `idiomas` no se
  sobrescribe; es el comportamiento deseado.

---

## 9. Referencias

- `docs/2026-10-13-idiomas-tabla-determinista.md` (tabla determinista + `--forzar`)
- `docs/2026-10-13-backfill-idiomas-juegos.md` (diseño del backfill)
- `docs/2026-10-13-traduccion-idiomas-juegos.md` (migración + normalización)
- `docs/despliegue/README.md` (runbook general: migraciones, scripts, smoke)
- `scripts/backfill-traducciones.php`, `scripts/normalizar-idiomas.php`
