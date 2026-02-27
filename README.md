# ROMs Vault - Catálogo de Videojuegos

Aplicación MVC en PHP para gestionar y mostrar un catálogo de ROMs e ISOs de videojuegos, con panel de administración y sistema de descargas desde Google Drive.

## 📋 Características

- Catálogo público de juegos con filtros por plataforma, género y región
- Paginación de 20 juegos por página (4 columnas × 5 filas)
- Sistema de descargas directas desde Google Drive
- Panel de administración protegido con login
- CRUD completo para gestionar juegos
- Subida de imágenes de portada

## 🚀 Instalación

### Requisitos previos

- PHP 7.4 o superior
- PostgreSQL 16 o superior
- Composer

### Pasos de instalación

1. **Clonar o descargar el repositorio**

```bash
git clone https://github.com/tu-usuario/roms-vault.git
cd roms-vault
```

2. **Instalar dependencias con Composer**

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

```bash
# Importar el archivo SQL (ajusta la ruta según donde tengas el archivo)
psql -U postgres -d roms-vault -f database/roms-vault.sql
```

5. **Configurar el archivo de entorno**

Crear un archivo `.env` en la raíz del proyecto:

```env
# Configuración de PostgreSQL
DB_HOST=
DB_PORT=
DB_NAME=roms-
DB_USER=
DB_PASSWORD=

# Clave secreta para sesiones
SESSION_SECRET=api_key_de_google_drive
```

6. **Dar permisos a la carpeta de uploads**

```bash
# En Linux/Mac
chmod 777 public/uploads/

# En Windows (no suele ser necesario, pero asegúrate de que la carpeta exista)
mkdir public\uploads
```

## 🎮 Ejecutar la aplicación

### Usando el servidor integrado de PHP

```bash
# Desde la raíz del proyecto
php -S localhost:8000
```

La aplicación estará disponible en: `http://localhost:8000`

### Usando XAMPP/WAMP/Laragon

Copia la carpeta del proyecto a:

- **XAMPP:** `C:\xampp\htdocs\roms-vault\`
- **WAMP:** `C:\wamp64\www\roms-vault\`
- **Laragon:** `C:\laragon\www\roms-vault\`

Accede vía navegador:

```
http://localhost/index.php
```

## 🔑 Acceso al panel de administración

Para acceder al área de administración, utiliza la siguiente URL:

```
http://localhost:8000/index.php?controller=auth&action=login
```


## 📁 Estructura del proyecto

```
roms-vault/
│
├── .env                      # Configuración de entorno
├── .htaccess                 # Redirecciones Apache
├── index.php                 # Front controller
├── composer.json             # Dependencias PHP
│
├── config/
│   └── database.php          # Conexión a PostgreSQL
│
├── controllers/
│   ├── HomeController.php    # Catálogo público
│   ├── AuthController.php    # Login/logout
│   └── AdminController.php   # Panel de administración
│
├── models/
│   ├── Model.php             # Modelo base
│   ├── Juego.php             # Gestión de juegos
│   ├── Consola.php           # Consulta de consolas
│   ├── Categoria.php         # Consulta de categorías
│   └── Usuario.php           # Autenticación
│
├── views/
│   ├── layout/
│   │   ├── header.php        # Cabecera común
│   │   └── footer.php        # Pie común
│   ├── home/
│   │   └── index.php         # Catálogo con filtros
│   ├── auth/
│   │   └── login.php         # Formulario de login
│   └── admin/
│       ├── dashboard.php     # Listado de juegos
│       ├── add.php           # Añadir juego
│       └── edit.php          # Editar juego
│
└── public/
    ├── css/
    │   └── style.css         # Estilos de la aplicación
    ├── js/
    │   └── script.js         # JavaScript (tema, etc.)
    └── uploads/              # Imágenes subidas
```

## 🎯 Funcionalidades

### Catálogo público

- Visualización de juegos en cuadrícula de 4 columnas
- Filtros por plataforma, género y región
- Paginación (20 juegos por página)
- Descarga directa desde Google Drive

### Panel de administración

- Login seguro con hash de contraseñas
- Dashboard con listado de juegos
- Añadir nuevos juegos con portada
- Editar juegos existentes
- Eliminar juegos
- Estadísticas básicas

### Error de dependencias

Si `composer install` falla, intenta:

```bash
composer clear-cache
composer install --no-dev
```

### No se ven las imágenes

Asegúrate de que la carpeta `public/uploads/` tenga permisos de escritura.