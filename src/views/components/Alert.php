<?php
/**
 * views/components/Alert.php
 * Componente reutilizable para renderizar alertas inline en la aplicación.
 */

class Alert {
    /**
     * Renderiza una alerta HTML.
     * 
     * @param string $type  Tipo de alerta ('info', 'success', 'warning', 'danger')
     * @param string $msg   Mensaje de la alerta (se permite HTML, usar htmlspecialchars si es necesario)
     * @param string|null $icon Nombre del icono data-i (por defecto se asigna uno según el tipo)
     * @param string $style CSS inline adicional (ej. padding, text-align)
     * @param bool $return Si es true retorna el HTML como string en lugar de hacer echo
     */
    public static function render(string $type, string $msg, ?string $icon = null, string $style = '', bool $return = false): string {
        if ($icon === null) {
            switch ($type) {
                case 'danger':  $icon = 'close'; break;
                case 'success': $icon = 'check'; break;
                case 'warning': $icon = 'alert-triangle'; break;
                case 'info':    $icon = 'info'; break;
                default:        $icon = 'info'; break;
            }
        }

        $styleAttr = $style ? ' style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : '';
        
        $html = '<div class="rv-inline-alert rv-inline--' . htmlspecialchars($type) . ' rv-inline--visible"' . $styleAttr . '>';
        
        if ($icon === '✖' || $icon === 'x') {
            $html .= '<span class="rv-inline-icon">✖</span>';
        } elseif ($icon !== '') {
            $html .= '<span class="rv-inline-icon"><i data-i="' . htmlspecialchars($icon) . '"></i></span>';
        }
        
        $html .= '<span class="rv-inline-msg">' . $msg . '</span>';
        $html .= '</div>';

        if (!$return) {
            echo $html;
        }
        return $html;
    }
}
