<?php
require_once __DIR__ . '/Model.php';

/**
 * models/Traduccion.php
 * Acceso a las tablas de traducciones de contenido (i18n, bloque 2).
 *
 * Dos tablas con clave primaria COMPUESTA (entidad_id, idioma):
 *   - categorias_traducciones  (nombre, descripcion)
 *   - juegos_traducciones      (descripcion)
 *
 * El 'es' canónico NO vive aquí (solo se guardan idiomas distintos de 'es').
 * Por eso extiende Model pero NO usa create()/update(): aquellos asumen una PK
 * simple `id`, incompatible con una compuesta. Las escrituras se hacen con
 * UPSERT (INSERT ... ON CONFLICT).
 *
 * Regla de oro: una fila `origen = 'manual'` NUNCA se sobrescribe con una del
 * traductor automático (`auto`). La guarda vive en el ON CONFLICT (WHERE ...).
 */
class Traduccion extends Model {

    /** @var string Tabla por defecto (no usada por los métodos propios). */
    protected $table = 'juegos_traducciones';

    // ── Lectura batch (anti N+1) ──────────────────────────────────────────

    /**
     * Traducciones de categorías por idioma, indexadas por categoria_id.
     * @param array $ids
     * @return array<int,array> categoria_id => fila
     */
    public function getCategorias(array $ids, string $idioma): array {
        return $this->fetchPorIds('categorias_traducciones', 'categoria_id', $ids, $idioma);
    }

    /**
     * Traducciones de juegos por idioma, indexadas por juego_id.
     * @param array $ids
     * @return array<int,array> juego_id => fila
     */
    public function getJuegos(array $ids, string $idioma): array {
        return $this->fetchPorIds('juegos_traducciones', 'juego_id', $ids, $idioma);
    }

    /**
     * Todas las traducciones de una categoría, indexadas por idioma.
     * @return array<string,array> idioma => fila
     */
    public function idiomasDeCategoria(int $categoriaId): array {
        return $this->fetchPorEntidad('categorias_traducciones', 'categoria_id', $categoriaId);
    }

    /**
     * Todas las traducciones de un juego, indexadas por idioma.
     * @return array<string,array> idioma => fila
     */
    public function idiomasDeJuego(int $juegoId): array {
        return $this->fetchPorEntidad('juegos_traducciones', 'juego_id', $juegoId);
    }

    // ── Upsert ────────────────────────────────────────────────────────────

    /**
     * Inserta o actualiza la traducción de una categoría.
     * No pisa una traducción manual salvo que la nueva también sea manual.
     */
    public function upsertCategoria(
        int $categoriaId,
        string $idioma,
        ?string $nombre,
        ?string $descripcion,
        string $origen = 'auto',
        bool $revisado = false,
        ?string $hash = null
    ): bool {
        $sql = "INSERT INTO categorias_traducciones
                    (categoria_id, idioma, nombre, descripcion, origen, revisado, origen_hash, created_at, updated_at)
                VALUES (:entidad_id, :idioma, :nombre, :descripcion, :origen, :revisado, :hash, now(), now())
                ON CONFLICT (categoria_id, idioma) DO UPDATE SET
                    nombre       = EXCLUDED.nombre,
                    descripcion  = EXCLUDED.descripcion,
                    origen       = EXCLUDED.origen,
                    revisado     = EXCLUDED.revisado,
                    origen_hash  = EXCLUDED.origen_hash,
                    updated_at   = now()
                WHERE categorias_traducciones.origen <> 'manual'
                   OR EXCLUDED.origen = 'manual'";

        return $this->ejecutarUpsert($sql, [
            ':entidad_id'  => [$categoriaId, PDO::PARAM_INT],
            ':idioma'      => [$idioma, PDO::PARAM_STR],
            ':nombre'      => [$nombre, PDO::PARAM_STR],
            ':descripcion' => [$descripcion, PDO::PARAM_STR],
            ':origen'      => [$origen, PDO::PARAM_STR],
            ':revisado'    => [$revisado, PDO::PARAM_BOOL],
            ':hash'        => [$hash, PDO::PARAM_STR],
        ]);
    }

    /**
     * Inserta o actualiza la traducción de un juego (descripción e idiomas).
     * No pisa una traducción manual salvo que la nueva también sea manual.
     */
    public function upsertJuego(
        int $juegoId,
        string $idioma,
        ?string $descripcion,
        string $origen = 'auto',
        bool $revisado = false,
        ?string $hash = null,
        ?string $idiomas = null
    ): bool {
        $sql = "INSERT INTO juegos_traducciones
                    (juego_id, idioma, descripcion, idiomas, origen, revisado, origen_hash, created_at, updated_at)
                VALUES (:entidad_id, :idioma, :descripcion, :idiomas, :origen, :revisado, :hash, now(), now())
                ON CONFLICT (juego_id, idioma) DO UPDATE SET
                    descripcion  = EXCLUDED.descripcion,
                    idiomas      = EXCLUDED.idiomas,
                    origen       = EXCLUDED.origen,
                    revisado     = EXCLUDED.revisado,
                    origen_hash  = EXCLUDED.origen_hash,
                    updated_at   = now()
                WHERE juegos_traducciones.origen <> 'manual'
                   OR EXCLUDED.origen = 'manual'";

        return $this->ejecutarUpsert($sql, [
            ':entidad_id'  => [$juegoId, PDO::PARAM_INT],
            ':idioma'      => [$idioma, PDO::PARAM_STR],
            ':descripcion' => [$descripcion, PDO::PARAM_STR],
            ':idiomas'     => [$idiomas, PDO::PARAM_STR],
            ':origen'      => [$origen, PDO::PARAM_STR],
            ':revisado'    => [$revisado, PDO::PARAM_BOOL],
            ':hash'        => [$hash, PDO::PARAM_STR],
        ]);
    }

    // ── Internos ──────────────────────────────────────────────────────────

    /**
     * SELECT * ... WHERE {columna} IN (:ids) AND idioma = :idioma.
     * @param array $ids
     * @return array<int,array>
     */
    private function fetchPorIds(string $tabla, string $columna, array $ids, string $idioma): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        if ($ids === []) {
            return [];
        }

        $ph     = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $clave          = ':id' . $i;
            $ph[]           = $clave;
            $params[$clave] = $id;
        }

        $sql = "SELECT * FROM {$tabla}
                WHERE {$columna} IN (" . implode(',', $ph) . ")
                  AND idioma = :idioma";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_INT);
        }
        $stmt->bindValue(':idioma', $idioma, PDO::PARAM_STR);
        $stmt->execute();

        $salida = [];
        foreach ($stmt->fetchAll() as $fila) {
            $salida[(int) $fila[$columna]] = $fila;
        }
        return $salida;
    }

    /**
     * SELECT * ... WHERE {columna} = :id (todas las traducciones de una entidad).
     * @return array<string,array>
     */
    private function fetchPorEntidad(string $tabla, string $columna, int $idEntidad): array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$tabla} WHERE {$columna} = :id");
        $stmt->bindValue(':id', $idEntidad, PDO::PARAM_INT);
        $stmt->execute();

        $salida = [];
        foreach ($stmt->fetchAll() as $fila) {
            $salida[(string) $fila['idioma']] = $fila;
        }
        return $salida;
    }

    /**
     * Ejecuta el UPSERT con bindings tipados.
     * @param array<string,array{0:mixed,1:int}> $bindings
     */
    private function ejecutarUpsert(string $sql, array $bindings): bool {
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $clave => [$valor, $tipo]) {
            $stmt->bindValue($clave, $valor, $tipo);
        }
        return $stmt->execute();
    }
}
