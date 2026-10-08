<?php
/**
 * views/components/Pagination.php
 * Componente reutilizable para la paginación de listas.
 */

class Pagination {
    /**
     * Renderiza la paginación y la información de registros mostrados.
     *
     * @param int    $currentPage  Página actual
     * @param int    $totalPages   Total de páginas
     * @param string $paramStr     Parámetros adicionales de la URL (ej. &busqueda=foo)
     * @param string $controller   Controlador para el enlace (ej. 'admin')
     * @param string $action       Acción para el enlace (ej. 'dashboard')
     * @param int    $countCurrent Cantidad de elementos mostrados en esta página
     * @param int    $totalItems   Cantidad total de elementos que existen
     * @param string $itemLabel    Etiqueta para los elementos (ej. 'juegos', 'consolas')
     * @param string $idPrefix     Prefijo para los IDs generados (ej. 'admin')
     * @param string $extraHtml    HTML adicional para inyectar en la caja de información (ej. filtros activos)
     * @param bool   $return       Si es true, retorna el string HTML en lugar de imprimirlo.
     */
    public static function render(
        int $currentPage, 
        int $totalPages, 
        string $paramStr, 
        string $controller, 
        string $action, 
        int $countCurrent, 
        int $totalItems, 
        string $itemLabel = 'registros', 
        string $idPrefix = 'pagination',
        string $extraHtml = '',
        bool $return = false
    ): string {
        if ($totalPages <= 0) {
            return '';
        }

        ob_start();
        
        $baseUrl = "?controller=" . urlencode($controller) . "&action=" . urlencode($action);
        if (!empty($paramStr) && !str_starts_with($paramStr, '&')) {
            $paramStr = '&' . $paramStr;
        }

        ?>
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top:1.5rem;" <?= $idPrefix ? 'id="' . htmlspecialchars($idPrefix) . '-pagination"' : '' ?>>
            <?php if ($currentPage > 1): ?>
                <a href="<?= $baseUrl ?>&page=<?= $currentPage - 1 ?><?= $paramStr ?>"
                   class="pagination-link" data-page="<?= $currentPage - 1 ?>">
                   <i data-i="chevron-left"></i> Anterior
                </a>
            <?php endif; ?>
            
            <?php
            $range = 2; 
            $pages = [];
            for ($i = 1; $i <= $totalPages; $i++) {
                if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $range) {
                    $pages[] = $i;
                }
            }
            
            $prev = null;
            foreach ($pages as $i):
                if ($prev !== null && $i - $prev > 1): ?>
                    <span style="padding:0 0.3rem;color:var(--slate-light);">...</span>
                <?php endif;
                
                if ($i === $currentPage): ?>
                    <span class="pagination-current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>&page=<?= $i ?><?= $paramStr ?>"
                       class="pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
                <?php endif;
                
                $prev = $i;
            endforeach; 
            ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $baseUrl ?>&page=<?= $currentPage + 1 ?><?= $paramStr ?>"
                   class="pagination-link" data-page="<?= $currentPage + 1 ?>">
                   Siguiente <i data-i="chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($totalItems > 0): ?>
        <div class="pagination-info" style="margin-top:0.75rem;" <?= $idPrefix ? 'id="' . htmlspecialchars($idPrefix) . '-info"' : '' ?>>
            Mostrando <?= number_format($countCurrent) ?> de <?= number_format($totalItems) ?> <?= htmlspecialchars($itemLabel) ?>
            <?php if ($totalPages > 1): ?>
                - Página <?= $currentPage ?> de <?= $totalPages ?>
            <?php endif; ?>
            <?= $extraHtml ?>
        </div>
        <?php endif; ?>
        <?php
        
        $html = ob_get_clean();
        
        if (!$return) {
            echo $html;
        }
        
        return $html;
    }
}
