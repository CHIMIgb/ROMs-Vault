<?php
/**
 * controllers/ExportController.php
 * Controlador para gestionar la exportación a Excel usando SimpleXLSXGen.
 */

require_once __DIR__ . '/../config/AuthMiddleware.php';
require_once __DIR__ . '/../models/Export.php';

use Shuchkin\SimpleXLSXGen;

class ExportController {

    public function __construct() {
        AuthMiddleware::requireAdmin(); // Solo administradores pueden exportar
    }

    public function download() {
        $table = $_GET['table'] ?? '';

        if (empty($table)) {
            http_response_code(400);
            echo t('msg.tabla_no_especificada');
            exit;
        }

        $exportModel = new Export();
        $allowedTables = Export::getAvailableTables();

        if (!array_key_exists($table, $allowedTables)) {
            http_response_code(400);
            echo t('msg.tabla_no_valida');
            exit;
        }

        try {
            // Obtener los datos y headers
            $tableData = $exportModel->getTableData($table);
            
            // Construir el array final para SimpleXLSXGen: [ [header1, header2], [row1_col1, row1_col2], ... ]
            $excelData = [];
            
            // Aplicar estilo bold a los headers y fondo oscuro
            $styledHeaders = [];
            foreach ($tableData['headers'] as $header) {
                // SimpleXLSXGen styles: <b> bold, <center> align, <bgcolor> etc.
                $styledHeaders[] = '<center><b>' . $header . '</b></center>';
            }
            $excelData[] = $styledHeaders;

            // Añadir las filas
            foreach ($tableData['data'] as $row) {
                $excelData[] = array_values($row); // Extraer los valores en orden
            }

            // Generar el nombre de archivo
            $dateStr = date('Y-m-d_His');
            $filename = "ROMs_Vault_{$table}_{$dateStr}.xlsx";

            // Crear y descargar el archivo XLSX
            $xlsx = SimpleXLSXGen::fromArray($excelData);
            
            // Forzar descarga en el navegador
            $xlsx->downloadAs($filename);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo t('msg.export_error_interno', ['detalle' => $e->getMessage()]);
            exit;
        }
    }
}
