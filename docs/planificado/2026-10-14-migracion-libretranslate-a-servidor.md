# Migración de LibreTranslate a un servidor gratuito

**Fecha:** 2026-10-14
**Estado:** planificado (pendiente de implementación y aprobación)
**Ámbito:** infraestructura + i18n (traducción de contenido, bloque 2)
**Documentos relacionados:**
`docs/2026-10-13-configuracion-libretranslate-memoria-y-backfill.md`,
`docs/2026-10-13-despliegue-backfill-idiomas-produccion.md`,
`docs/despliegue/README.md`,
`docs/planificado/2026-10-14-guardado-asincrono-cola-bd-worker.md`

---

## 1. Resumen del cambio

Hoy **LibreTranslate corre solo en Windows** (Docker local, imagen
`romv-libretranslate:models` de 6.5 GB, `LT_THREADS=1`, 12 idiomas, ~140–480 MB
de RAM medidos, modelos de 2.8 GB). Es el **único componente que no está en
servidores**: la aplicación está en Vercel y la base de datos en Neon.

Este plan mueve LibreTranslate a un host **gratuito, persistente, siempre
encendido y con HTTPS**, y habilita la traducción en **runtime** (traducir al
guardar juego/categoría) tocando `docker-entrypoint.sh` (cambio ya autorizado).

**Por qué:**
- El **backfill CLI** (`scripts/backfill-traducciones.php`) deja de depender de
  la máquina local.
- El **runtime** (traducción al guardar) queda operativo en producción.

---

## 2. Host elegido y alternativas (estado 2026)

Requisitos reales de este proyecto (medidos): RAM **< 600 MB** con
`LT_LOAD_ONLY` + `LT_THREADS=1`, disco ~**4–5 GB** (modelos 2.8 GB + imagen),
**volumen persistente** (evitar re-descarga de modelos) y **sin cold start**
(la traducción en runtime es síncrona, con `TRANSLATE_TIMEOUT=8` s por idioma).

| Host | ¿Gratis? | Tipo | RAM free | Disco persistente | Always-on | Tarjeta | Encaje |
|---|---|---|---|---|---|---|---|
| **Oracle Cloud Always Free (ARM)** | Sí (Always Free) | VPS (tú corres Docker) | **2 OCPU / 12 GB** | **200 GB** | **Sí** | Sí (verificación) | **Elegido** |
| Google Cloud Run | Allowance free | Contenedor serverless | 180k vCPU-s + 360k GiB-s/mes | No (efímero) | No (scale-to-zero) | Sí | No cumple runtime gratis (cold start vs 8 s) |
| Azure Container Apps | Allowance free | Contenedor serverless | similar a Cloud Run | No | No | Sí | Igual que Cloud Run |
| Render | Sí | PaaS container | 512 MB | No (free) | No (sleep 15 min) | No | Frágil (RAM/disco) |
| Railway | Trial $5 + $1/mes | PaaS | 0.5 GB free | por uso | Parcial | No | Ajustado |
| SnapDeploy | Sí (100 h/mes) | PaaS | 512 MB | No | No (sleep) | No | Frágil |
| Koyeb | Sí (1 svc) | PaaS | 0.1 vCPU, hold $29 | No | scale-to-zero | Sí | Insuficiente |
| Hugging Face Docker Spaces | **No** (PRO desde jul-2026) | PaaS | — | — | — | — | Descartado |
| Fly.io | **No** (allowances quitadas oct-2024) | PaaS | — | — | — | — | Descartado |
| libretranslate.com | No (**$29/mes**) | API gestionada | — | — | — | — | Descartado (coste) |

**Decisión:** Oracle Cloud Always Free (ARM, `VM.Standard.A1.Flex`) + Docker
Compose (LibreTranslate + Caddy) + **DuckDNS** (subdominio gratuito, ya que no
hay dominio propio).

**Riesgo conocido:** error *"out of host capacity"* al crear la VM ARM.
Mitigación: reintentar en franjas distintas / otra Availability Domain, o usar
de plan B una instancia **AMD micro** (1 GB, siempre gratis) con menos idiomas
cargados.

---

## 3. Arquitectura objetivo

```
Internet ──HTTPS(443)──> [Caddy] ──interno──> [LibreTranslate:5000] ──volumen──> lt_models
                              (VM Oracle ARM, 2 OCPU / 12 GB, Ubuntu)

Backfill CLI / runtime (Vercel) ──HTTPS──> https://roms-vault-lt.duckdns.org/translate
```

- **Caddy** obtiene el certificado Let's Encrypt del subdominio DuckDNS mediante
  reto **HTTP-01** (basta abrir el puerto 80 en la Security List). Alternativa si
  el 80 no está disponible: reto **DNS-01** con el plugin `caddy-dns/duckdns`
  (imagen de Caddy con plugin).
- **Volumen nombrado** `lt_models` en `/home/libretranslate/.local` → los modelos
  se descargan **una vez** y sobreviven a reinicios.
- **Firewall (Security List):** abrir solo **443** (y 80 solo para el reto ACME).
  **Nunca** exponer 5000.
- **Persistencia y ARM64:** la imagen oficial `libretranslate/libretranslate`
  publica `linux/arm64`, compatible con la VM Ampere.

---

## 4. Archivos afectados

```
[CREAR] deploy/libretranslate/docker-compose.yml
  - Servicio libretranslate (LT_LOAD_ONLY=12 idiomas, LT_THREADS, LT_API_KEYS,
    volumen lt_models) + servicio caddy (reverse_proxy + TLS)

[CREAR] deploy/libretranslate/Caddyfile
  - roms-vault-lt.duckdns.org { reverse_proxy libretranslate:5000 }

[CREAR] deploy/libretranslate/.env.example
  - DUCKDNS_API_TOKEN, DUCKDNS_DOMAIN (solo para la VM)

[CREAR] deploy/libretranslate/README.md
  - Pasos del host: crear VM, Security List (solo 443/80), instalar Docker,
    preload de modelos, arranque, API key, verificación, rollback

[MODIFICAR] docker-entrypoint.sh
  - Añadir al .env regenerado: TRANSLATE_PROVIDER, TRANSLATE_LIBRE_URL,
    TRANSLATE_API_KEY, TRANSLATE_TIMEOUT (habilita el runtime)

[MODIFICAR] .env.example
  - Documentar TRANSLATE_LIBRE_URL (URL remota https) y TRANSLATE_API_KEY;
    eliminar/marcar el `TRANSLATE_LIBRE_API_KEY` (el código NO lo lee)

[MODIFICAR] docs/despliegue/README.md
  - Sección LibreTranslate: pasa de "Docker local" a "servidor remoto (Oracle)"

[MODIFICAR] .dockerignore
  - Excluir `deploy/` para no inflar la imagen de la app

[CREAR] docs/planificado/2026-10-14-migracion-libretranslate-a-servidor.md
  - Este documento
```

---

## 5. Variables, tokens y dependencias

- **En la VM** (`deploy/libretranslate/.env`): `DUCKDNS_API_TOKEN`,
  `DUCKDNS_DOMAIN` (además de los `LT_*`).
- **En la app** (Vercel / `.env`): `TRANSLATE_PROVIDER=libretranslate`,
  `TRANSLATE_LIBRE_URL=https://roms-vault-lt.duckdns.org`,
  `TRANSLATE_API_KEY=<clave>`, `TRANSLATE_TIMEOUT=8`.
- **Sin dependencias Composer/npm nuevas.** Software libre: Docker, Caddy,
  DuckDNS, LibreTranslate.
- **Color de seguridad:** con `LT_API_KEYS=true`, el endpoint exige `api_key`
  (que la app ya envía como `TRANSLATE_API_KEY`). Ver la doc oficial de
  LibreTranslate para el almacén de claves.

Ejemplo de `docker-compose.yml` (borrador, `LT_THREADS` ajustable al host):

```yaml
services:
  libretranslate:
    image: libretranslate/libretranslate:latest
    restart: unless-stopped
    environment:
      LT_LOAD_ONLY: es,en,fr,de,it,pt,ru,tr,ja,zh,ko,ar
      LT_THREADS: "4"
      LT_API_KEYS: "true"
    volumes:
      - lt_models:/home/libretranslate/.local
    expose:
      - "5000"

  caddy:
    image: caddy:2
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - libretranslate

volumes:
  lt_models:
  caddy_data:
  caddy_config:
```

`Caddyfile`:

```
roms-vault-lt.duckdns.org {
    reverse_proxy libretranslate:5000
}
```

---

## 6. Orden de ejecución

1. **Alta (usuario):** crear cuenta Oracle (tarjeta de verificación) y VM ARM
   Ubuntu 1–2 OCPU / 6–12 GB + boot volume ≥ 47 GB; crear subdominio en DuckDNS
  apuntando a la IP pública; ajustar la Security List (solo 443/80).
- 2. **Preparación (repo):** crear `deploy/libretranslate/*` + el cambio de
  `docker-entrypoint.sh` y `.env.example` (misma rama/commit).
3. **Despliegue en la VM:** instalar Docker + Docker Compose; `docker compose up -d`.
4. **Preload de modelos:** `curl /languages` + una `translate` de prueba
  (cold start ~10–30 s); confirmar que los 12 idiomas quedan cargados.
- 5. **API key:** crear una clave en LibreTranslate y guardarla como
  `TRANSLATE_API_KEY` en Vercel.
- 6. **Backfill de producción:** `php scripts/backfill-traducciones.php
  --campo=idiomas --forzar` → 1331 filas / 0 errores (paso ya documentado).
- 7. **Runtime:** desplegar la app (Vercel) con `TRANSLATE_*` (entrypoint tocado).
- 8. **Docs + roadmap:** docs fechados y checklist FASE 10.

---

## 6. Verificación

- `curl https://roms-vault-lt.duckdns.org/languages` → los **12** idiomas.
- `curl -X POST https://roms-vault-lt.duckdns.org/translate -d api_key=...
  -d q='Juego de carreras' -d source=es -d target=en` → `Future racing game`
  (o equivalente).
- **Reinicio** del contenedor → los modelos persisten (sin re-descarga).
- `php scripts/backfill-traducciones.php --dry-run` contra la URL remota →
  totales correctos; backfill real → 1331 filas / 0 errores.
- **Runtime:** guardar un juego en admin → se traduce a los 11 idiomas en < 8 s
  cada uno (se mide en la respuesta del guardado).
- `docker stats` → RAM muy por debajo de 12 GB.

---

## 7. Impacto y compatibilidad

- **No** se tocan URLs públicas, CSP ni `vercel.json` (`rewrites: []`).
- Sin cambios de esquema ni de BD.
- Mientras `TRANSLATE_PROVIDER=none` (default del código) el comportamiento no
  cambia; el runtime se activa solo cuando el entrypoint pasa `TRANSLATE_*`.
- El **Docker local se mantiene** como alternativa de desarrollo (no se elimina).

---

## 8. Gotchas

- **"Out of host capacity"** al crear la VM ARM (reintentar / otra AD / plan B AMD).
- **ARM64:** usar la imagen con tag `linux/arm64` (la oficial lo publica).
- **Cold start** del primer `/translate` tras arrancar (~10–30 s): hacer warm-up.
- **`LT_THREADS`:** en local se usó `1` por RAM (4.76 GB); en la VM de 12 GB se
  puede subir a 4 para acelerar el backfill.
- **No renombrar** `ajax_*.php` ni `rom_proxy.php` (firmas HMAC y `fetch`).
- **`TRANSLATE_LIBRE_API_KEY`**: existe comentado en `.env.example` pero el código
  usa `TRANSLATE_API_KEY`; no confundirlos.

---

## 9. Rollback

- Volver `TRANSLATE_PROVIDER=none` (o quitar `TRANSLATE_*` del entrypoint) y
  redesplegar → runtime desactivado, sin cambios en BD.
- Apagar/eliminar la VM de Oracle (no afecta a app ni BD).
- El Docker local sigue disponible como respaldo inmediato.

---

## 10. Estado

- [ ] Crear VM Oracle + DuckDNS (usuario).
- [ ] Preparar `deploy/libretranslate/*` + entrypoint.
- [ ] Desplegar, preload de modelos y API key.
- [ ] Backfill de producción.
- [ ] Activar runtime y desplegar la app.
- [ ] Docs + roadmap (FASE 10).
