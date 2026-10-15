<?php
/**
 * Modelo para la cola de traducciones asíncronas.
 * Usa raw PDO, sin ORM.
 */
class TraduccionCola
{
    private $db;

    /**
     * @param PDO|null $db Conexión a usar (por defecto, la de la aplicación).
     *                      Inyectable para poder testear la clase sin BD real.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Encola un trabajo de traducción.
     *
     * Si ya existe un job activo (pendiente/en_proceso) para la misma
     * (entidad, entidad_id), lo reescribimos con los nuevos campos y lo
     * devolvemos a 'pendiente' (reset intentos/error) para evitar duplicados.
     *
     * @return int id del job creado/actualizado
     */
    public function encolar(string $entidad, int $entidadId, array $campos): int
    {
        $entidad = strtolower($entidad);
        if (!in_array($entidad, ['categoria', 'juego'], true)) {
            throw new InvalidArgumentException('entidad_invalida');
        }
        if ($entidadId <= 0) {
            throw new InvalidArgumentException('entidad_id_invalido');
        }
        $camposJson = json_encode($campos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($camposJson === false) {
            $camposJson = '{}';
        }

        // Intento UPDATE de job activo primero (dedupe)
        $sqlUp = 'UPDATE traduccion_cola
                    SET campos = :campos,
                        estado = \'pendiente\',
                        intentos = 0,
                        max_intentos = 5,
                        disponible_en = NOW(),
                        bloqueado_en = NULL,
                        error = NULL
                  WHERE entidad = :entidad
                    AND entidad_id = :eid
                    AND estado IN (\'pendiente\', \'en_proceso\')
                  RETURNING id';
        $stUp = $this->db->prepare($sqlUp);
        $stUp->execute([
            ':entidad' => $entidad,
            ':eid'     => $entidadId,
            ':campos'  => $camposJson,
        ]);
        if ($stUp->rowCount() > 0) {
            $row = $stUp->fetch(PDO::FETCH_ASSOC);
            return (int) $row['id'];
        }

        // INSERT nuevo job
        $sqlIns = 'INSERT INTO traduccion_cola (entidad, entidad_id, campos)
                   VALUES (:entidad, :eid, :campos)
                   RETURNING id';
        $stIns = $this->db->prepare($sqlIns);
        $stIns->execute([
            ':entidad' => $entidad,
            ':eid'     => $entidadId,
            ':campos'  => $camposJson,
        ]);
        $row = $stIns->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('no_se_pudo_encolar');
        }
        return (int) $row['id'];
    }

    /**
     * Reclama el siguiente trabajo pendiente (atomic con SKIP LOCKED).
     *
     * @param string|null $entidad Filtra por 'juego' o 'categoria' (null = cualquiera).
     * @return array|null
     */
    public function reclamar(?string $entidad = null): ?array
    {
        $entidad = $entidad !== null ? strtolower($entidad) : null;
        if ($entidad !== null && !in_array($entidad, ['categoria', 'juego'], true)) {
            $entidad = null;
        }

        // Requiere transacción para SKIP LOCKED + UPDATE seguro
        $this->db->beginTransaction();
        try {
            $filtro = $entidad !== null ? ' AND entidad = :entidad' : '';
            $sqlSel = 'SELECT id
                         FROM traduccion_cola
                        WHERE estado = \'pendiente\'
                          AND disponible_en <= NOW()' . $filtro . '
                     ORDER BY id
                        FOR UPDATE SKIP LOCKED
                        LIMIT 1';
            $stSel = $this->db->prepare($sqlSel);
            if ($entidad !== null) {
                $stSel->bindValue(':entidad', $entidad);
            }
            $stSel->execute();
            $sel = $stSel->fetch(PDO::FETCH_ASSOC);
            if (!$sel) {
                $this->db->commit();
                return null;
            }

            $id = (int) $sel['id'];
            $sqlUpd = 'UPDATE traduccion_cola
                          SET estado = \'en_proceso\',
                              bloqueado_en = NOW(),
                              intentos = intentos + 1,
                              actualizado_en = NOW()
                        WHERE id = :id
                    RETURNING id, entidad, entidad_id, campos, intentos, max_intentos';
            $stUpd = $this->db->prepare($sqlUpd);
            $stUpd->execute([':id' => $id]);
            $row = $stUpd->fetch(PDO::FETCH_ASSOC);
            $this->db->commit();
            if (!$row) {
                return null;
            }
            // Decodificar campos
            $campos = json_decode($row['campos'] ?? '{}', true);
            if (!is_array($campos)) {
                $campos = [];
            }
            return [
                'id'          => (int) $row['id'],
                'entidad'     => $row['entidad'],
                'entidad_id'  => (int) $row['entidad_id'],
                'campos'      => $campos,
                'intentos'    => (int) $row['intentos'],
                'max_intentos'=> (int) $row['max_intentos'],
            ];
        } catch (Exception $e) {
            try {
                $this->db->rollBack();
            } catch (Exception $ex) {
                // ignore
            }
            throw $e;
        }
    }

    /**
     * Marca el trabajo como completado.
     */
    public function marcarOk(int $id): void
    {
        $sql = 'UPDATE traduccion_cola
                   SET estado = \'ok\',
                       disponible_en = NOW(),
                       actualizado_en = NOW()
                 WHERE id = :id';
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
    }

    /**
     * Marca el trabajo como fallido de forma definitiva (dead-letter), sin
     * reintentos. Para fallos permanentes (entidad inválida, sin texto...).
     */
    public function marcarFallido(int $id, string $error): void
    {
        $sql = 'UPDATE traduccion_cola
                   SET estado = \'fallido\',
                       bloqueado_en = NULL,
                       error = :err,
                       actualizado_en = NOW()
                 WHERE id = :id';
        $st = $this->db->prepare($sql);
        $st->execute([
            ':err' => mb_substr($error, 0, 2000),
            ':id'  => $id,
        ]);
    }

    /** Tope del backoff (5 minutos). */
    public const BACKOFF_MAX = 300;

    /**
     * Segundos de espera para el siguiente intento (backoff exponencial).
     * 2^intentos, con mínimo de 1 s y tope de 300 s (5 min).
     */
    public static function backoffSegundos(int $intentos): int
    {
        if ($intentos < 0) {
            $intentos = 0;
        }
        // 2^9 = 512 ya supera el tope, así que a partir de ahí se devuelve el
        // máximo directamente. Evita además que pow() desborde el cast a int
        // (un float fuera de rango se convierte a 0).
        if ($intentos >= 9) {
            return self::BACKOFF_MAX;
        }
        return (int) pow(2, $intentos);
    }

    /**
     * Marca el trabajo como fallido.
     * Si quedan intentos, lo devuelve a 'pendiente' con backoff exponencial.
     * Si no quedan, lo pasa a 'fallido' y guarda el error.
     */
    public function marcarError(int $id, int $intentos, int $maxIntentos, string $error): void
    {
        $errorStr = mb_substr($error, 0, 2000);
        if ($intentos < $maxIntentos) {
            $segundos = self::backoffSegundos($intentos);
            $sql = 'UPDATE traduccion_cola
                       SET estado = \'pendiente\',
                           disponible_en = NOW() + (:s || \' seconds\')::interval,
                           bloqueado_en = NULL,
                           error = :err,
                           actualizado_en = NOW()
                     WHERE id = :id';
            $st = $this->db->prepare($sql);
            $st->execute([
                ':s'   => $segundos,
                ':err' => $errorStr,
                ':id'  => $id,
            ]);
        } else {
            $sql = 'UPDATE traduccion_cola
                       SET estado = \'fallido\',
                           disponible_en = NOW(),
                           bloqueado_en = NULL,
                           error = :err,
                           actualizado_en = NOW()
                     WHERE id = :id';
            $st = $this->db->prepare($sql);
            $st->execute([
                ':err' => $errorStr,
                ':id'  => $id,
            ]);
        }
    }
}
