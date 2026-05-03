# ROMs Vault - Catálogo de Videojuegos

Aplicación MVC en PHP para gestionar y mostrar un catálogo de ROMs e ISOs de videojuegos, con panel de administración, exportación a Excel, y sistema de descargas desde Google Drive.

## 📋 Características

- Catálogo público de juegos con filtros por plataforma, género y región
- Paginación de 20 juegos por página (4 columnas × 5 filas)
- Sistema de descargas directas desde Google Drive
- **Emuladores Web Integrados** con soporte para N64, PSP, PS1, NDS y otros (Soporte experimental)
- Panel de administración protegido con **autenticación basada en JWT y Cookies seguras (httpOnly)**
- CRUD completo para gestionar juegos, consolas y categorías
- Subida de imágenes de portada
- **Exportación a Excel (.xlsx)** del contenido de todas las tablas de la base de datos
- Sistema de alertas UI consistentes y modales personalizados
- Modo Oscuro/Claro nativo

## 🚀 Instalación

### Requisitos previos

- PHP 8.1 o superior
- PostgreSQL 16 o superior (también incluye archivo para MySQL si prefieres)
- Composer

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
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=roms-vault
DB_USERNAME=postgres
DB_PASSWORD=tu_password

# JWT y Seguridad
JWT_SECRET=tu_clave_secreta_super_segura
JWT_EXPIRATION=3600
JWT_REFRESH_THRESHOLD=300
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
php -S localhost:8000
```

La aplicación estará disponible en: `http://localhost:8000`

## 🔑 Acceso al panel de administración

Para acceder al área de administración, utiliza la siguiente URL:

```
http://localhost:8000/index.php?controller=auth&action=login
```

## 📁 Estructura del proyecto principal

```
roms-vault/
│
├── .env                      # Configuración de entorno
├── index.php                 # Front controller
├── composer.json             # Dependencias PHP
├── data/                     # Archivos SQL (MySQL y PostgreSQL)
│
├── config/
│   ├── database.php          # Conexión a la base de datos
│   ├── JWTService.php        # Generación y validación de tokens JWT
│   └── AuthMiddleware.php    # Middleware para protección de rutas
│
├── controllers/
│   ├── HomeController.php    # Catálogo público y emulador
│   ├── AuthController.php    # Login/logout con JWT
│   ├── AdminController.php   # Panel de administración (Juegos)
│   ├── ConsolaController.php # Panel de administración (Consolas)
│   ├── CategoriaController.php # Panel de administración (Categorías)
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
│   ├── home/                 # Catálogo y emulador
│   ├── auth/                 # Login
│   └── admin/                # Vistas de gestión
│
└── public/
    ├── css/
    │   └── style.css         # Estilos de la aplicación
    ├── js/
    │   └── rv-alerts.js      # Sistema de modales y notificaciones custom
    └── uploads/              # Imágenes subidas
```

## 🎯 Dependencias destacadas

- **vlucas/phpdotenv**: Para el manejo de variables de entorno seguras (`.env`).
- **firebase/php-jwt**: Implementación segura de JSON Web Tokens.
- **shuchkin/simplexlsxgen**: Para la exportación ultra-rápida de datos a archivos `.xlsx` sin requerir extensiones pesadas de PHP (como `gd` o `zip`).

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