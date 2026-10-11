# Fix — TypeError en login con usuario inexistente

Fecha: 2026-10-08

## Síntoma (reportado por el usuario)

Escribir un nombre de usuario que **no existe** en el login mostraba un
pantallazo de error en lugar del mensaje genérico «Usuario o contraseña
incorrectos»:

```
Fatal error: Uncaught TypeError: Usuario::estaBloqueado(): Argument #1 ($user)
must be of type ?array, bool given, called in
...\src\controllers\AuthController.php on line 29 ...
```

Con la **contraseña mala** (usuario existente) el error no aparecía, por eso
pasó desapercibido.

## Causa raíz

El lockout por cuenta (plan §2.2, commit `4aab50f`) introdujo
`Usuario::estaBloqueado(?array $user): bool` con tipo estricto. Pero
`Usuario::findByUsername()` devolvía el resultado crudo de `PDO::fetch()`, que
retorna **`false`** cuando no hay fila. `AuthController::login()` (línea 29)
pasaba ese `false` a `estaBloqueado(?array)` → `TypeError`.

Con usuario existente, `$user` era `array` y la función operaba bien, por eso
solo fallaba el camino de «usuario no registrado».

## Fix aplicado

1. **`src/models/Usuario.php`** — `findByUsername()` ahora tiene retorno tipo
   `?array` y devuelve `fetch() ?: null`: el contrato honesto es «no existe →
   `null`», no `false`. Protege a cualquier caller futuro, no solo al login.
2. **`src/controllers/AuthController.php`** — blindaje en `login()`:
   `$user = $usuarioModel->findByUsername($username) ?: null;`.

No se tocó `estaBloqueado()`: el tipo estricto `?array` es correcto; el bug
estaba en el caller.

## Verificación

- `php -l` en `Usuario.php`, `AuthController.php`, `SecurityTest.php`: sin errores.
- Suite `Security`: `OK (16 tests, 46 assertions)` con el nuevo test de
  regresión `testLoginUsuarioInexistenteDevuelveErrorGenerico200`.
- Suite completa: `OK (139 tests, 366 assertions)`.
- Smoke manual con php -S + `roms-vault-test`:
  - `POST /auth/login` usuario inexistente → **HTTP 200**, body con
    «Usuario o contraseña incorrectos», 0 ocurrencias de `TypeError`/`Fatal error`.
  - `POST /auth/login` contraseña mala (usuario `admin` existe) → **HTTP 200**,
    mismo mensaje genérico. Ambos caminos indistinguibles (no se revela si la
    cuenta existe).

## Notas para los hallazgos §9

- `Usuario::fallosActuales()` no tiene callers en el código: candidato a dead
  code (revisar en la siguiente pasada de auditoría).