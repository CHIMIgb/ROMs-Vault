<?php
/**
 * views/components/EmptyState.php
 * Componente reutilizable para estados vacíos con estética CRT
 * (pantalla oscura + scanlines), coherente con el design system retro.
 */

class EmptyState {
    /**
     * Renderiza un estado vacío (listado sin resultados).
     *
     * @param string $titulo   Título ya traducido (Press Start 2P)
     * @param string $msg      Mensaje ya traducido (Courier Prime)
     * @param string|null $btnLabel Etiqueta ya traducida del botón "limpiar" (null = sin botón)
     * @param bool $return     Si es true retorna el HTML como string en lugar de hacer echo
     */
    public static function render(string $titulo, string $msg, ?string $btnLabel = null, bool $return = false): string {
        $html  = '<div class="empty-state" role="status">';
        $html .= '<span class="empty-state-icon" aria-hidden="true"><i data-i="disc"></i></span>';
        $html .= '<p class="empty-state-title">' . $titulo . '</p>';
        $html .= '<p class="empty-state-msg">' . $msg . '</p>';
        if ($btnLabel !== null && $btnLabel !== '') {
            $html .= '<button type="button" class="empty-state-clear">'
                   . '<i data-i="close" aria-hidden="true"></i> ' . $btnLabel
                   . '</button>';
        }
        $html .= '</div>';

        if (!$return) {
            echo $html;
        }
        return $html;
    }
}
