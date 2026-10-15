# Fix: 500 al guardar un juego (formato_imagen VARCHAR(20)) + validación de longitudes

- **Fecha:** 2026-10-15
- **Rama:** `fix/formato-imagen-largo-validacion`
- **HEAD previo:** `4fbbafb` (merge del PR #20)
- **Estado:** implementado y verificado. Migración **ya aplicada en Neon producción**
  y en las BD locales (`roms-vault`, `roms-vault-test`).

---

## 1. Resumen

Al guardar un juego en producción con `formato_imagen = "Windows Binary (.exe)"`
(21 caracteres) la aplicación devolvía **HTTP 500**:

```
Uncaught PDOException: SQLSTATE[22001]: String data, right truncated:
7 ERROR: value too long for type character varying(20)
in src/models/Model.php:51   (Model->update)
#1 AdminController.php(310)  (Edit -> update)
#2 AdminController.php(188)  ('formato_imagen' => $_POST['formato_imagen'])
```

La columna `juegos.formato_imagen` era `VARCHAR(20)`: los valores del formulario
la desbordan. No existía **ninguna** validación: el `<input>` no tenía
`maxlength` y el controlador tomaba `$_POST` en crudo, dejando propagar la
`PDOException` hasta un fatal 500.

Se corrige en dos frentes:

- **(a) Esquema:** se amplía `formato_imagen` y `region` a `VARCHAR(50)`.
- **(b) Código:** se valida la longitud de los campos antes de tocar la BD
  (mensaje de formulario en vez de 500), se añaden `maxlength` y se captura la
  `PDOException` como red de seguridad.

## 2. Causa raíz

| Aspecto | Detalle |
|---|---|
| Columna | `juegos.formato_imagen VARCHAR(20)` (idéntico en local, test y Neon) |
| Valor | `"Windows Binary (.exe)"` = 21 caracteres |
| Validación | ninguna (ni cliente ni servidor) |
| Manejo de error | `Model::update()` deja propagar `PDOException`; nadie la captura |

`region VARCHAR(10)` no podía desbordarse desde el formulario (es un `<select>`
con opciones cortas: `PAL`, `NTSC`, `NTSC-J`, `NTSC-U`), pero se amplió igual por
consistencia y para eliminar el riesgo latente ante peticiones manipuladas.

## 3. Archivos

| Acción | Archivo | Cambio |
|---|---|---|
| CREAR | `data/migrations/2026-10-15-formato-imagen-region-largo.sql` | `ALTER COLUMN ... TYPE VARCHAR(50)` para `formato_imagen` y `region` (idempotente, no destructiva) |
| MODIFICAR | `data/roms-vaultDB-postgreSQL.sql` | Esquema base: `region VARCHAR(50)`, `formato_imagen VARCHAR(50)` |
| MODIFICAR | `data/roms-vaultDB-MySQL.sql` | Volcado MySQL espejo, mismas longitudes |
| MODIFICAR | `src/controllers/AdminController.php` | Const `JUEGO_LIMITES`, `validarJuego()`, validación en `add()`/`edit()` y `try/catch (PDOException)` |
| MODIFICAR | `src/views/admin/add.php` | `maxlength` en los inputs de texto |
| MODIFICAR | `src/views/admin/edit.php` | `maxlength` en los inputs de texto |
| MODIFICAR | `src/i18n/*.json` (12 idiomas) | Nueva clave `msg.campo_largo` con `{campo}` y `{limite}` |
| CREAR | `tests/Integration/AdminJuegoValidacionTest.php` | 4 casos: regresión, límite inclusivo, no-persistencia y conservación del valor |

## 4. Detalles de implementación

### 4.1 Migración (idempotente)

```sql
ALTER TABLE public.juegos ALTER COLUMN formato_imagen TYPE VARCHAR(50);
ALTER TABLE public.juegos ALTER COLUMN region TYPE VARCHAR(50);
```

Envuelto en `DO $$ ... $$` que comprueba `information_schema.columns` y solo
actúa si `character_maximum_length < 50`. Re-ejecutarla es un no-op (verificado).

### 4.2 Validación en el controlador

`validarJuego(array $data): ?string` recorre `JUEGO_LIMITES`
(`titulo` 200, `region` 50, `idiomas` 200, `formato_imagen` 50, `game_id_code` 50,
`google_drive_file_id` 100, `google_drive_view_link` 500) y devuelve el mensaje
`msg.campo_largo` ya traducido, o `null` si todo es válido.

En `add()` y `edit()` el POST se procesa en **dos fases**:

1. construir `$data` + normalizar idiomas + `validarJuego()`.
2. si la validación pasó, ejecutar el resto (subidas, movimientos en R2, guardado).

Así, un error de validación **no produce efectos secundarios** (no sube ni borra
archivos). En `edit()` además se hace `array_merge($juego, $data)` para que el
formulario repinte lo que escribió el usuario (la vista pinta desde `$juego`, no
desde `$_POST`).

### 4.3 Red de seguridad

`Model::create()/update()` se envuelven en `try/catch (PDOException)`: ante
cualquier otro dato inválido se registra con `error_log('[admin/...] ...')` y se
devuelve el error de formulario en vez de un 500.

## 5. Validación

- `phpunit`: **OK (258 tests, 14909 assertions)** (4 nuevos).
- `phpstan analyse` (nivel 5): **No errors**.
- Migración: probada en `roms-vault`, `roms-vault-test` y **Neon**.
  - Antes: `formato_imagen=20`, `region=10`.
  - Después: `formato_imagen=50`, `region=50`.
  - Re-ejecución: no-op.

## 6. Riesgos y notas

- El fix (a) en Neon **ya resuelve el 500 en producción** aunque aún no se
  despliegue el código: la columna admite el valor.
- El registro `juegos.id = 156` conserva el valor truncado previo
  `"Windows Binary (.exe"` (sin cerrar paréntesis). Se corrige re-guardando el
  juego; no se modifica a mano para no tocar datos de negocio sin autorización.
- `region` se amplió aunque el formulario no puede desbordarlo: defensa en
  profundidad.

## 7. Idiomas que no se traducían

Los idiomas de juegos nuevos/editados **no se traducían**: el encolado funcionaba
(`traduccion_cola`) y el worker CLI también, pero el **cron de GitHub Actions
solo se disparó 2 veces en 11 horas** (esperado cada 5 min), por lo que la cola
casi nunca se drenaba.

Resuelto con el **drenado oportunista** (Opción A) descrito en
`docs/2026-10-15-drenado-oportunista-traducciones.md`: tras responder el guardado,
el admin procesa unos pocos trabajos de la cola sin bloquear al usuario. El job
pendiente de `juego/156` ya se drenó en producción.
