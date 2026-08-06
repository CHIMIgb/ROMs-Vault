# ROMs Vault - Catálogo de Videojuegos

Aplicación MVC en PHP para gestionar y mostrar un catálogo de ROMs e ISOs de videojuegos, con panel de administración, exportación a Excel, y sistema de descargas desde Google Drive.

## 📋 Características

- Catálogo público de juegos con filtros por plataforma, género y región
- Paginación de 20 juegos por página (4 columnas × 5 filas)
- Sistema de descargas directas desde Google Drive
- **Emuladores Web Integrados** con soporte para N64, PSP, PS1, NDS y otros (Soporte experimental)
- **Proxy de streaming seguro** para ROMs con:
  - Rate limiting configurable por IP
  - URLs firmadas con HMAC (anti-scraping, TTL de 2 horas)
  - CORS restrictivo con whitelist de orígenes
  - Cache de URLs resueltas de Google Drive
  - Streaming chunk a chunk sin cargar archivos en memoria
- Panel de administración protegido con **autenticación basada en JWT y Cookies seguras (httpOnly)**
- **Autenticación con Google OAuth**
- CRUD completo para gestionar juegos, consolas y categorías
- Subida de imágenes de portada
- **Exportación a Excel (.xlsx)** del contenido de todas las tablas de la base de datos
- Sistema de alertas UI consistentes y modales personalizados
- Modo Oscuro/Claro nativo

## 🚀 Instalación

### Requisitos previos

- PHP 8.1 o superior
- PostgreSQL 16 o superior
- Composer
- Docker (opcional, para despliegue containerizado)

### Pasos de instalación

1. **Clonar o descargar el repositorio**

```bash
git clone https://github.com/tu-usuario/roms-vault.git
cd roms-vault
```

2. **Instalar dependencias con Composer**

Incluye la librería para exportar a Excel (`shuchkin/simplexlsxgen`) y otras utilidades:

```bash
composer install
```

3. **Configurar la base de datos PostgreSQL**

Crear una base de datos llamada `roms-vault`:

```bash
# Acceder a PostgreSQL
psql -U postgres

# Crear la base de datos
CREATE DATABASE "roms-vault" WITH ENCODING 'UTF8';

# Salir de PostgreSQL
\q
```

4. **Importar la estructura de la base de datos**

Los archivos `.sql` se encuentran en la nueva carpeta `data/`:

```bash
# Importar el archivo SQL para PostgreSQL
psql -U postgres -d roms-vault -f data/roms-vaultDB-postgreSQL.sql
```

5. **Configurar el archivo de entorno**

Copia el archivo `.env.example` a `.env` en la raíz del proyecto y configura tus credenciales:

```env
# Base de datos
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=roms-vault
DB_USER=postgres
DB_PASSWORD=tu_password
DB_CHARSET=

# Sesión
SESSION_SECRET=tu_session_secret

# JWT y Seguridad
JWT_SECRET=tu_clave_secreta_super_segura
JWT_EXPIRATION=3600
JWT_REFRESH_THRESHOLD=600

# Google OAuth (opcional)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# ROM Proxy — Seguridad y rendimiento
RATE_LIMIT_MAX=30          # Máximo de peticiones por IP en la ventana de tiempo
RATE_LIMIT_WINDOW=60       # Ventana de tiempo en segundos
ALLOWED_ORIGINS=           # Orígenes CORS permitidos (separados por coma). Vacío = permitir todos
                           # Ejemplo: https://mi-dominio.com,https://roms-vault.vercel.app
```

6. **Dar permisos a la carpeta de uploads**

```bash
# En Linux/Mac
chmod 777 public/uploads/
```

## 🎮 Ejecutar la aplicación

### Usando el servidor integrado de PHP

```bash
# Desde la raíz del proyecto
php -S localhost:8000 router.php
```

La aplicación estará disponible en: `http://localhost:8000`

> `router.php` habilita las **URLs limpias**: el catálogo se ve en `http://localhost:8000/`,
> la ficha de un juego en `/home/show/12`, el login en `/auth/login`, etc.
> Las URLs antiguas con `index.php?controller=...` siguen funcionando.

### Usando Docker

```bash
# Construir la imagen
docker build -t roms-vault .

# Ejecutar el contenedor
docker run -p 8080:80 --env-file .env roms-vault
```

La aplicación estará disponible en: `http://localhost:8080`

## 🧪 Ejecutar los tests

El proyecto incluye tests automatizados con **PHPUnit** para la lógica de negocio
crítica (firma HMAC de URLs, tokens JWT, rate limiting y protección CSRF) y para
los flujos de integración (router, autenticación y CRUD de consolas/emuladores).

Los tests usan un **entorno aislado**: los unit tests no tocan tu base de datos
real ni tu `.env`; definen su propio `JWT_SECRET` de prueba. Los tests de
integración levantan un servidor `php -S` contra una **base de datos PostgreSQL
local de prueba** (`roms-vault-test`), nunca contra producción (Neon).

```bash
# Desde la raíz del proyecto
composer install            # Instala dependencias incluyendo phpunit (require-dev)
composer test               # Ejecuta todos los tests
```

Para los tests de integración (se requiere la BD local `roms-vault-test` y la
contraseña se pasa por entorno, nunca versionada):

```bash
# Linux / CI (PostgreSQL de prueba en localhost)
TEST_DB_PASSWORD=<password-postgres-local> vendor/bin/phpunit

# Windows (php.exe)
cmd.exe /c "set TEST_DB_PASSWORD=<password-postgres-local>&& C:\xampp\php\php.exe vendor\bin\phpunit"
```

La salida usa el formato **testdox**, que muestra en pantalla el nombre y el
propósito de cada test para saber exactamente qué se está verificando:

```
Router (Tests\Integration\Router)
 ✔ Ruta raiz devuelve 200
 ✔ Ruta login devuelve 200
 ✔ Ruta admin protegida redirige sin sesion
 ...
OK (61 tests, 136 assertions)
```

### Estructura de tests

```
tests/
├── bootstrap.php           # Carga clases y define el entorno de prueba (JWT_SECRET fake)
├── Unit/                   # Tests unitarios de clases aisladas
│   ├── UrlSignerTest.php   # Firma/verificación HMAC de URLs (2h TTL, anti-tampering)
│   ├── JWTServiceTest.php  # Generación, validación y expiración de tokens JWT
│   ├── RateLimiterTest.php # Ventana deslizante por IP y detección de IP real
│   └── CsrfServiceTest.php # Patrón "Double Submit Cookie" y verificación por POST/header/query
└── Integration/            # Tests de integración (HTTP real + BD PostgreSQL de prueba)
    ├── Server.php          # Helper: levanta php -S con router.php, cookies y CSRF
    ├── RouterTest.php      # URLs limpias y retrocompatibilidad (index.php?controller=...)
    ├── AuthFlowTest.php    # Login fallido/exitoso, dashboard protegido, logout, rate limit
    ├── CrudTest.php        # CRUD de consolas vía HTTP con verificación de persistencia
    └── EmuladorModelTest.php # Consultas del modelo Emulador contra la BD real
```

> Los tests están pensados para ejecutarse también en **CI (GitHub Actions)**,
> así que son deterministas: no dependen del orden, del estado global ni de la red.

## 🔑 Acceso al panel de administración

Para acceder al área de administración, utiliza la siguiente URL:

```
http://localhost:8000/auth/login
```

## 🌐 Despliegue en Vercel

El proyecto incluye un `vercel.json` con rewrites para que las URLs limpias
(`/home/show/12`, `/admin/dashboard`, ...) también funcionen en producción.
Vercel sirve los archivos reales (`ajax_*.php`, `rom_proxy.php`, `public/`) sin
cambios y enruta el resto al front controller `index.php`.

## 📁 Estructura del proyecto principal

```
roms-vault/
│
├── .env                      # Configuración de entorno (no se sube al repo)
├── .env.example              # Plantilla de configuración
├── index.php                 # Front controller / Router principal
├── rom_proxy.php             # Proxy de streaming seguro para ROMs (Google Drive)
├── composer.json             # Dependencias PHP
├── phpunit.xml               # Configuración de PHPUnit (tests)
├── Dockerfile                # Imagen Docker (PHP 8.2 + Apache)
├── docker-entrypoint.sh      # Script de inicio del contenedor
├── .htaccess                 # Reglas de rewrite y seguridad Apache
├── data/                     # Archivos SQL (PostgreSQL)
├── tests/                    # Tests automatizados (PHPUnit)
│   └── Unit/                 # Tests unitarios de lógica de negocio
│
├── config/
│   ├── database.php          # Conexión a la base de datos (PostgreSQL)
│   ├── JWTService.php        # Generación y validación de tokens JWT
│   └── AuthMiddleware.php    # Middleware para protección de rutas
│
├── controllers/
│   ├── HomeController.php    # Catálogo público y emulador
│   ├── AuthController.php    # Login/logout con JWT y Google OAuth
│   ├── AdminController.php   # Panel de administración (Juegos)
│   ├── ConsolaController.php # Panel de administración (Consolas)
│   ├── CategoriaController.php # Panel de administración (Categorías)
│   ├── ErrorsController.php  # Páginas de error (404, 403, 500)
│   └── ExportController.php  # Generación de descargas Excel (.xlsx)
│
├── models/
│   ├── Model.php             # Modelo base
│   ├── Juego.php             # Gestión de juegos
│   ├── Consola.php           # Gestión de consolas
│   ├── Categoria.php         # Gestión de categorías
│   ├── Usuario.php           # Autenticación
│   └── Export.php            # Consultas para exportar bases de datos completas
│
├── views/
│   ├── components/
│   │   └── Alert.php         # Componente reutilizable para alertas UI
│   ├── layout/               # Cabeceras y pies de página
│   ├── home/                 # Catálogo, detalle y emulador
│   ├── auth/                 # Login
│   ├── admin/                # Vistas de gestión
│   └── errors/               # Páginas de error personalizadas
│
├── public/
│   ├── css/
│   │   └── style.css         # Estilos de la aplicación
│   ├── js/
│   │   └── rv-alerts.js      # Sistema de modales y notificaciones custom
│   └── uploads/              # Imágenes subidas
│
├── ajax_admin.php            # Endpoint AJAX para operaciones de administración
├── ajax_autocomplete.php     # Endpoint AJAX para autocompletado de búsqueda
├── ajax_catalog.php          # Endpoint AJAX para filtrado del catálogo
├── ajax_categoria.php        # Endpoint AJAX para gestión de categorías
└── ajax_consola.php          # Endpoint AJAX para gestión de consolas
```

## 🔒 Seguridad del Proxy de ROMs

El archivo `rom_proxy.php` actúa como intermediario seguro entre EmulatorJS y Google Drive:

| Característica | Descripción |
|---|---|
| **Rate Limiting** | Límite configurable de peticiones por IP (`RATE_LIMIT_MAX` / `RATE_LIMIT_WINDOW`) |
| **URLs Firmadas (HMAC)** | Cada URL del proxy incluye una firma temporal que expira en 2 horas |
| **CORS Restrictivo** | Whitelist de orígenes permitidos (`ALLOWED_ORIGINS` en `.env`) |
| **Validación en BD** | Solo permite `file_id` que existan en la base de datos y estén activos |
| **Cache de URLs** | Cachea URLs resueltas de Google Drive por 10 minutos |
| **Streaming eficiente** | Transmite en chunks de 256 KB sin cargar el archivo completo en memoria |
| **Security Headers** | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` |

## 🎯 Dependencias destacadas

- **vlucas/phpdotenv**: Para el manejo de variables de entorno seguras (`.env`).
- **firebase/php-jwt**: Implementación segura de JSON Web Tokens.
- **shuchkin/simplexlsxgen**: Para la exportación ultra-rápida de datos a archivos `.xlsx` sin requerir extensiones pesadas de PHP (como `gd` o `zip`).
- **phpunit/phpunit**: *(dev)* Framework de testing para los tests unitarios de seguridad (HMAC, JWT, rate limiting, CSRF).

## 🛠 Solución de problemas comunes

### Error de JWT o Sesión Expirada al instante
Comprueba que el timezone de tu servidor de PHP coincida con el esperado o que el `JWT_SECRET` en el archivo `.env` se haya configurado correctamente.

### Error de dependencias
Si `composer install` falla, intenta:

```bash
composer clear-cache
composer install --no-dev
```

### No se ven las imágenes
Asegúrate de que la carpeta `public/uploads/` tenga permisos de escritura y lectura correctos para el usuario de tu servidor web.

### Error 403 o "enlace expirado" al jugar una ROM
Las URLs del proxy expiran después de 2 horas. Recarga la página del juego para obtener un enlace nuevo.

### Error 429 "Demasiadas peticiones"
El rate limiting está bloqueando tu IP. Espera el tiempo configurado en `RATE_LIMIT_WINDOW` (por defecto 60 segundos) o ajusta los valores en `.env`.