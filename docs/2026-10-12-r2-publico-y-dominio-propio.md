# Acceso público de imágenes en R2 y dominio propio — estado y hoja de ruta

- **Fecha:** 2026-10-12
- **Estado:** análisis y decisión documentados; **sin cambios de código**
- **Plan de referencia:** `docs/2026-10-12-plan-migracion-imagenes-r2.md`
- **Implementación:** `docs/2026-10-12-migracion-imagenes-r2-implementacion.md`
- **Docs de Cloudflare (actualizados 2026-09-25):** https://developers.cloudflare.com/r2/buckets/public-buckets/

## 1. Estado actual (2026-10-12)

- El bucket `roms-vault-imagenes` usa la **Public Development URL (`r2.dev`)**:
  `https://pub-ac645afb1acc4c2192171c732285594d.r2.dev`.
- Por tanto, **todos los objetos son públicos**: cualquier persona con la URL puede
  descargarlos. No hay firma, token ni control por objeto.
- Esto es lo adecuado hoy: las portadas/capturas son contenido público que ya se
  muestra en `<img>` en la web. El navegador carga las imágenes **directo desde el
  CDN** (`pub-<hash>.r2.dev`), sin pasar por la aplicación.

## 2. Qué implica usar `r2.dev`

Según la documentación de Cloudflare:

- `r2.dev` es una URL de **desarrollo / non-production** y está **rate-limited**
  (puede fallar con tráfico alto); no garantiza SLA.
- **No permite** WAF custom rules, caching avanzado, access controls ni Bot
  Management: esas capacidades solo existen con **custom domain**.
- Por defecto los buckets NO son públicos; la exposición vía `r2.dev` se activa
  explícitamente (nuestro caso).
- **No crear un CNAME manual al `r2.dev`**: es un camino no soportado por
  Cloudflare. El dominio propio se conecta desde el panel del bucket.

## 3. Por qué `https://roms-vault.vercel.app/` NO sirve como dominio propio

- Los custom domains de R2 exigen que el dominio sea una **zona de la MISMA
  cuenta de Cloudflare** que el bucket.
- `roms-vault.vercel.app` es un subdominio gestionado por **Vercel**: no se puede
  registrar como zona en Cloudflare ni conectar al bucket. Sirve solo para la
  aplicación (Vercel/deploy), **no** como CDN de imágenes.
- Los `<img>` de la web hoy cargan desde `pub-<hash>.r2.dev` (correcto: el
  navegador va directo al CDN sin pasar por la app de Vercel).

## 4. Hoja de ruta: cuando exista un dominio propio

1. **Tener un dominio propio** y registrarlo en Cloudflare como zona (p. ej.
   `midominio.com`) en la **misma cuenta** de Cloudflare que el bucket.
2. En el panel del bucket (`R2 -> Buckets -> roms-vault-imagenes -> Settings ->
   Custom Domains`) conectar un subdominio (p. ej. `img.midominio.com`).
3. **Desactivar la Public Development URL (`r2.dev`)** para no dejar la vía
   pública de desarrollo abierta en paralelo.
4. Activar **Hotlink Protection** en la zona (evita que otros sitios incrusten
   las imágenes y roben ancho de banda). Es la protección recomendada para
   contenido público.
5. Opcional, si algún día se quisiera **acceso firmado**:
   - **WAF Token Authentication** (Cloudflare): URLs con token.
   - **Cloudflare Zero Trust Access**: control por identidad (para equipos).
   - **Worker + firma HMAC** al renderizar cada `<img>` (mismo patrón que
     `rom_proxy.php`/`UrlSigner` para ROMs, pero más coste de CPU y TTL cortos).
6. Cambiar `R2_PUBLIC_BASE` y `R2_PUBLIC_HOST` en `.env` local y en las
   Environment Variables de Vercel. La CSP de `index.php` se ajusta sola
   (lee `R2_PUBLIC_HOST`).
7. **Actualizar las referencias en BD**: `juegos.imagen` y `juegos.capturas`
   guardan URLs completas de `pub-...r2.dev`. Al cambiar el host hay que
   reemplazar el prefijo en **local y Neon**, p. ej.:

   ```sql
   UPDATE juegos
      SET imagen    = replace(imagen, 'https://pub-<hash>.r2.dev/', 'https://img.midominio.com/'),
          capturas  = replace(capturas, 'https://pub-<hash>.r2.dev/', 'https://img.midominio.com/')
    WHERE imagen LIKE 'https://pub-%' OR capturas LIKE 'https://pub-%';
   ```

   Idealmente antes de que Neon tenga tráfico, o reutilizando
   `scripts/migrar-imagenes-r2.php` con un modo de actualización de prefijo.

## 5. Decisión recomendada

- **Portadas/capturas**: mantener acceso público (es contenido público por
  diseño). En producción: **dominio propio + desactivar `r2.dev` + Hotlink
  Protection**.
- **ROMs**: sin cambios; ya están protegidos con `UrlSigner` (firma HMAC + TTL)
  en `endpoints/rom_proxy.php`.
- **Cuándo**: cuando exista un dominio propio (hoy no hay) y antes de dar el
  salto con tráfico real. Mientras tanto, `r2.dev` es suficiente para desarrollo.

## 6. Costes (2026-10-12)

| Concepto | Coste |
|---|---|
| Zona en Cloudflare (DNS/CDN/SSL, plan Free) | Gratis |
| Conectar un custom domain al bucket R2 | Gratis |
| El dominio en sí (registro anual, ej. `midominio.com`) | ~10-15 USD/año según TLD; en Cloudflare Registrar se vende al costo (sin margen) |
| Delegar nameservers a Cloudflare (*full setup*, plan Free) | Gratis |
| Hotlink Protection + WAF básico + caché | Gratis (plan Free) |
| Plan Pro de Cloudflare (opcional, WAF avanzado) | 20 USD/mes (no necesario hoy) |
| R2 (almacenamiento + operaciones) | Free tier: 10 GB + 1M Class A + 10M Class B/mes; las portadas actuales quedan muy por debajo |

Conclusión: **sin dominio propio no hay coste** (Vercel app + R2 se mantienen en
sus Free tiers). Con dominio propio, el único desembolso es el **registro del
dominio (~10-15 USD/año)**; todo lo demás de este plan es gratis.