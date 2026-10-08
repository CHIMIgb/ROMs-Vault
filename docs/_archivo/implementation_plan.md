# Página Individual de Detalle de Juego 🎮

Crear una página dedicada para cada videojuego del catálogo donde el usuario pueda ver toda la información del juego en detalle: portada grande, galería de capturas de pantalla, sinopsis, ficha técnica completa, botones de descarga/jugar, y una sección de juegos relacionados.

**URL de acceso:** `index.php?controller=home&action=show&id=123`

## Open Questions

> [!IMPORTANT]
> **Galería de capturas de pantalla:** Para mostrar "más fotos" de cada juego, necesitamos crear una tabla nueva en la base de datos (`juego_imagenes`) y un sistema de subida múltiple en el panel de admin. ¿Quieres que incluya esto en la primera versión, o prefieres que primero hagamos la página de detalle con la portada existente y la galería la añadamos después como mejora?

> [!NOTE]
> **Sinopsis:** Actualmente el campo `descripcion` de la DB es un campo de texto libre. ¿Quieres que siga usándose ese campo como sinopsis, o prefieres que añada un campo nuevo más largo (ej. `sinopsis`) separado de la descripción corta?

## Proposed Changes

### Base de Datos

#### [NEW] Tabla `juego_imagenes` (galería de capturas)
- `id` — PK autoincremental.
- `juego_id` — FK → `juegos.id`.
- `imagen` — Ruta al archivo (ej. `public/uploads/screenshots/xxx.webp`).
- `orden` — Entero para controlar el orden de aparición.
- `created_at` — Timestamp.

Se creará ejecutando un script SQL de migración contra la DB de Neon.

---

### Modelo

#### [NEW] `models/JuegoImagen.php`
- Modelo para la tabla `juego_imagenes`.
- Método `getByJuegoId($juegoId)` — Obtener todas las capturas de un juego ordenadas por `orden`.

#### [MODIFY] [Juego.php](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/models/Juego.php)
- Añadir método `findWithDetails($id)` — Query con JOINs a `consolas` y `categorias` para traer el nombre de la consola y categoría junto con todos los campos del juego en una sola consulta.

---

### Controlador

#### [MODIFY] [HomeController.php](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/controllers/HomeController.php)
- Añadir acción pública `show($id)`:
  1. Obtener el juego completo con `findWithDetails($id)`.
  2. Si no existe o no está activo → redirigir al 404.
  3. Obtener capturas de pantalla con `JuegoImagen::getByJuegoId($id)`.
  4. Obtener juegos relacionados con `Juego::getRelated($id, $consolaId, $categoriaId, 6)`.
  5. Renderizar `views/home/show.php` envuelto en header/footer.

---

### Vista

#### [NEW] `views/home/show.php`
Estructura propuesta de la página:

```
┌─────────────────────────────────────────────┐
│  ← Volver al catálogo                       │
├──────────────┬──────────────────────────────┤
│              │  TÍTULO DEL JUEGO            │
│   PORTADA    │  ───────────────────         │
│   GRANDE     │  Consola · Categoría · Región│
│              │  Fecha de lanzamiento        │
│              │  Idiomas · Tamaño · Game ID  │
│              │                              │
│              │  [▶ Jugar Online] [⬇ Descargar]│
├──────────────┴──────────────────────────────┤
│  SINOPSIS                                   │
│  Lorem ipsum dolor sit amet...              │
├─────────────────────────────────────────────┤
│  GALERÍA DE CAPTURAS                        │
│  [img1] [img2] [img3] [img4]               │
├─────────────────────────────────────────────┤
│  JUEGOS RELACIONADOS                        │
│  [card] [card] [card] [card]               │
└─────────────────────────────────────────────┘
```

- La portada se mostrará a tamaño grande con un efecto de hover/zoom.
- La sinopsis será el campo `descripcion` existente.
- La galería usará un carrusel ligero en CSS/JS puro (sin librerías externas).
- Los juegos relacionados reutilizarán el diseño de `.game-card` ya existente.

---

### Panel de Admin (subida de capturas)

#### [MODIFY] [views/admin/edit.php](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/views/admin/edit.php)
- Añadir una sección debajo del campo de portada: "Capturas de pantalla".
- Input de tipo `file` con atributo `multiple` para subir varias imágenes a la vez.
- Mostrar las capturas ya existentes con opción de eliminar individualmente y reordenar.

#### [MODIFY] [AdminController.php](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/controllers/AdminController.php)
- Modificar `edit()` y `add()` para procesar la subida de múltiples capturas (reutilizando la lógica de conversión a WebP que ya creamos).
- Guardar cada captura en `public/uploads/screenshots/`.

---

### Estilos CSS

#### [MODIFY] [game-details.css](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/public/css/modules/game-details.css)
- Añadir estilos para `.game-detail-page` con el layout retro del proyecto.
- Diseño de la ficha técnica en dos columnas (portada + info).
- Galería de capturas con scroll horizontal o grid.
- Sección de juegos relacionados reutilizando `.game-card`.
- Responsive: en móvil, la portada pasará arriba y la info debajo (una sola columna).

#### [MODIFY] [responsive.css](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/public/css/modules/responsive.css)
- Añadir media queries para la página de detalle.

---

### Links desde el catálogo

#### [MODIFY] [views/home/index.php](file:///c:/Users/chimi/Documents/GitHub/ROMs-Vault/views/home/index.php)
- Hacer que al hacer clic en la portada o en el título de una tarjeta de juego, navegue a `index.php?controller=home&action=show&id=XX`.

---

## Verification Plan

### Manual Verification
1. Navegar al catálogo y hacer clic en un juego → debe abrir la página de detalle.
2. Verificar que se muestran todos los campos: título, portada, sinopsis, consola, categoría, región, idiomas, tamaño, descargas, partidas.
3. Verificar que los botones "Jugar Online" y "Descargar" funcionan desde la página de detalle.
4. Verificar que la sección "Juegos Relacionados" muestra juegos de la misma consola o categoría.
5. Verificar responsividad en móvil (una columna).
6. Subir capturas desde el admin y verificar que aparecen en la galería.
