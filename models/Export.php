<?php
/**
 * models/Export.php
 * Modelo para obtener los datos formateados listos para exportar a Excel.
 */

require_once __DIR__ . '/Model.php';

class Export extends Model {

    /**
     * Devuelve la lista de tablas permitidas para exportar y su nombre amigable.
     */
    public static function getAvailableTables(): array {
        return [
            'juegos'     => 'Juegos',
            'consolas'   => 'Consolas',
            'categorias' => 'Categorías',
            'usuarios'   => 'Usuarios',
            'personas'   => 'Personas',
            'roles'      => 'Roles',
            'descargas'  => 'Descargas',
        ];
    }

    /**
     * Obtiene los datos de la tabla especificada.
     * Retorna un array con 'headers' (nombres de las columnas) y 'data' (filas).
     */
    public function getTableData(string $tableName): array {
        $allowed = self::getAvailableTables();
        if (!array_key_exists($tableName, $allowed)) {
            throw new InvalidArgumentException("Tabla no permitida para exportación.");
        }

        switch ($tableName) {
            case 'juegos':
                return $this->getJuegosData();
            case 'consolas':
                return $this->getConsolasData();
            case 'categorias':
                return $this->getCategoriasData();
            case 'usuarios':
                return $this->getUsuariosData();
            case 'personas':
                return $this->getPersonasData();
            case 'roles':
                return $this->getRolesData();
            case 'descargas':
                return $this->getDescargasData();
            default:
                return ['headers' => [], 'data' => []];
        }
    }

    private function getJuegosData(): array {
        $query = "
            SELECT 
                j.id, 
                j.titulo, 
                j.imagen,
                j.descripcion,
                j.consola_id,
                c.nombre AS consola_nombre, 
                j.categoria_id,
                cat.nombre AS categoria_nombre, 
                j.region, 
                j.fecha_lanzamiento, 
                j.idiomas, 
                j.formato_imagen,
                j.game_id_code,
                j.google_drive_file_id,
                j.google_drive_view_link,
                j.size_bytes, 
                j.downloads_count, 
                j.plays_count, 
                j.activo, 
                j.created_at,
                j.updated_at
            FROM juegos j
            LEFT JOIN consolas c ON j.consola_id = c.id
            LEFT JOIN categorias cat ON j.categoria_id = cat.id
            ORDER BY j.id ASC
        ";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['size_bytes'] = $row['size_bytes'] ? round($row['size_bytes'] / 1048576, 2) . ' MB' : '0 MB';
            $row['activo'] = $row['activo'] ? 'Sí' : 'No';
        }

        return [
            'headers' => [
                'ID', 'Título', 'URL Imagen', 'Descripción', 'ID Consola', 'Nombre Consola', 
                'ID Categoría', 'Nombre Categoría', 'Región', 'Fecha Lanzamiento', 'Idiomas', 
                'Formato Imagen', 'Game ID Code', 'Google Drive ID', 'Google Drive View Link', 
                'Tamaño', 'Descargas', 'Jugadas', 'Activo', 'Creado el', 'Actualizado el'
            ],
            'data'    => $data
        ];
    }

    private function getConsolasData(): array {
        $query = "SELECT * FROM consolas ORDER BY id ASC";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['activo'] = $row['activo'] ? 'Sí' : 'No';
        }

        return [
            'headers' => ['ID', 'Nombre', 'Descripción', 'Fabricante', 'Activo', 'Fecha Creación'],
            'data'    => $data
        ];
    }

    private function getCategoriasData(): array {
        $query = "SELECT * FROM categorias ORDER BY id ASC";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['activo'] = $row['activo'] ? 'Sí' : 'No';
        }

        return [
            'headers' => ['ID', 'Nombre', 'Descripción', 'Activo', 'Fecha Creación'],
            'data'    => $data
        ];
    }

    private function getUsuariosData(): array {
        $query = "
            SELECT 
                u.id, 
                u.persona_id,
                p.nombre AS persona_nombre, 
                p.apellido AS persona_apellido, 
                p.email AS persona_email, 
                u.username, 
                u.rol_id,
                r.nombre AS rol_nombre, 
                u.activo, 
                u.last_login, 
                u.created_at,
                u.updated_at
            FROM usuarios u
            LEFT JOIN personas p ON u.persona_id = p.id
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY u.id ASC
        ";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['activo']       = $row['activo'] ? 'Sí' : 'No';
            // PII: enmascarar el email antes de exportarlo
            $row['persona_email'] = self::maskEmail($row['persona_email']);
        }

        return [
            'headers' => [
                'ID', 'ID Persona', 'Nombre Persona', 'Apellido Persona', 'Email Persona', 
                'Username', 'ID Rol', 'Nombre Rol', 'Activo', 
                'Último Login', 'Creado el', 'Actualizado el'
            ],
            'data'    => $data
        ];
    }

    private function getPersonasData(): array {
        $query = "SELECT * FROM personas ORDER BY id ASC";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            // PII: enmascarar el email antes de exportarlo
            $row['email'] = self::maskEmail($row['email']);
        }

        return [
            'headers' => ['ID', 'Nombre', 'Apellido', 'Email', 'Teléfono', 'Fecha Creación'],
            'data'    => $data
        ];
    }

    /**
     * Enmascara un email mostrando solo la primera letra y el dominio:
     * j***@example.com. Devuelve el valor original si no parece un email.
     */
    private static function maskEmail(?string $email): string {
        if (empty($email)) {
            return '';
        }
        $pos = strpos($email, '@');
        if ($pos === false) {
            return $email;
        }
        $local  = substr($email, 0, $pos);
        $domain = substr($email, $pos);
        $first  = $local !== '' ? $local[0] : '*';
        return $first . str_repeat('*', max(2, strlen($local) - 1)) . $domain;
    }

    private function getRolesData(): array {
        $query = "SELECT * FROM roles ORDER BY id ASC";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'headers' => ['ID', 'Nombre', 'Descripción', 'Fecha Creación'],
            'data'    => $data
        ];
    }

    private function getDescargasData(): array {
        $query = "
            SELECT 
                d.id, 
                d.juego_id,
                j.titulo AS juego_titulo, 
                d.cookie_id, 
                d.ip_address, 
                d.user_agent,
                d.downloaded_at, 
                d.completed
            FROM descargas d
            LEFT JOIN juegos j ON d.juego_id = j.id
            ORDER BY d.downloaded_at DESC
        ";
        $stmt = $this->pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['completed'] = $row['completed'] ? 'Sí' : 'No';
        }

        return [
            'headers' => ['ID', 'ID Juego', 'Título Juego', 'ID Sesión (Cookie)', 'Dirección IP', 'User Agent', 'Fecha Descarga', 'Completada'],
            'data'    => $data
        ];
    }
}
