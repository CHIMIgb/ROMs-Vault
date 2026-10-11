# Exclusión de `tests/` en el escaneo de secretos de GitGuardian

> Fecha: 2026-10-10 · Commits: `fa2bd39` (config `.gitguardian.yaml`)
> Relacionado: PR #7 · [`2026-10-08-suite-security-tests.md`](./2026-10-08-suite-security-tests.md) · [`2026-10-08-credenciales-por-defecto.md`](./2026-10-08-credenciales-por-defecto.md)
>
> **Actualización (2026-10-10):** tras la limpieza de credenciales hardcodeadas
> ([`2026-10-10-eliminacion-credenciales-hardcoded.md`](./2026-10-10-eliminacion-credenciales-hardcoded.md))
> los literales citados abajo **ya no existen en el repo** (viven en `tests/.env.test`).
> La configuración de exclusión de `tests/` sigue siendo válida para la CI futura.

## Resumen

El check **GitGuardian Security Checks** del PR #7 (`optimizacion-arquitectura` →
`main`) marcó **4 hallazgos de secretos**, todos en archivos de test. Se verificó
que son **fixtures** (ningún secreto real) y se deja constancia de la
configuración para que no vuelvan a dispararse, distinguiendo los **dos
mecanismos** que ofrece GitGuardian: el fichero `.gitguardian.yaml` (ggshield,
local/CI) y las **Filepath exclusions del dashboard** (las que gobiernan el
check del PR).

## Hallazgos reportados por GitGuardian

| GitGuardian id | Tipo | Archivo | Qué es realmente |
| --- | --- | --- | --- |
| 38036324 | Generic High Entropy Secret | `tests/Integration/TwoFactorFlowTest.php` | secreto TOTP del usuario de prueba `admin_tfa` — **vector conocido de la RFC 6238** (base32 de `12345678901234567890`). |
| 32401271 | Username Password | `tests/Security/SecurityTest.php` | contraseña del **seed de test** (BD `roms-vault-test`), nunca de producción. |
| 35860542 | Generic High Entropy Secret | `tests/Security/SecurityTest.php` | secreto JWT **falso** del servidor de integración (`tests/Integration/Server.php`). |
| 38036325 | Generic Password | `tests/Security/SecurityTest.php` | valor de un test de **inyección SQL** (`username => "' OR '1'='1"`). |

**Conclusión:** los cuatro son datos de prueba. El único "credencial" con forma
de secreto (la contraseña del seed de test) no existe en producción (Neon), y el resto
son literales inventados o vectores públicos de test. No hay ningún secreto real
comprometido.

## Los dos mecanismos de exclusión (no confundirlos)

GitGuardian documenta dos cosas distintas y **solo una** afecta al check del PR:

| Mecanismo | Dónde se configura | Afecta al check del PR | Notas |
| --- | --- | --- | --- |
| `.gitguardian.yaml` (`secret.ignored_paths`) | Fichero **en el repo** | **No** | Config de **ggshield (CLI)**: escaneos locales, hooks y CI. |
| **Filepath exclusions** | **Dashboard** → `Settings > Secrets detection > Filepath exclusions` | **Sí** | Se puede acotar **por repositorio**. Además **retira retroactivamente** los incidentes ya existentes. |

Fuentes: `docs.gitguardian.com/endpoint-protection/configuration-reference` (formato
del fichero de ggshield) y `docs.gitguardian.com/secrets-detection/customize-detection/exclusion-rules`
(filepath exclusions en *workspace settings*).

## Cambios aplicados

### 1. Repo (este commit)

`[CREAR] .gitguardian.yaml` en la raíz:

```yaml
version: 2
secret:
  ignored_paths:
    - 'tests/**'
```

Deja la exclusión lista para **ggshield local** y para la **futura CI** (FASE 2
del roadmap). Por sí solo **no** pone verde el check de GitHub.

### 2. Dashboard (acción manual del propietario)

Añadir en `Settings > Secrets detection > Filepath exclusions` el patrón
**`**/tests/**`** acotado al repositorio **`CHIMIgb/ROMs-Vault`**. Es lo que
gobierna el check del PR y lo que elimina los 4 incidentes existentes.

## Verificación

- `git status` limpio tras el commit; `.gitguardian.yaml` versionado (no está en
  `.gitignore`).
- No se tocó ningún test (los archivos se mantienen legibles).
- Cambio sin impacto en runtime: no afecta a URLs, CSP, `vercel.json`, ni a la
  suite de tests.
- El check de GitHub pasa una vez aplicada la **filepath exclusion** del
  dashboard y re-evaluada la PR.

## Notas / pendientes

- Si en el futuro se adopta **ggshield** en la CI (FASE 2), este `.gitguardian.yaml`
  ya cubre la carpeta `tests/`.
- Alternativa equivalente en el dashboard: marcar los 4 incidentes como
  *false positive / test credential / low risk* (GitGuardian alinea entonces el
  check con el motivo ignorado).
