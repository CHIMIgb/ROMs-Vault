<?php

/**
 * config/GoogleFonts.php
 * Origen único de Google Fonts (CSS, preconnect y CSP).
 *
 * Las tipografías del proyecto (Press Start 2P y Courier Prime) se cargan con un
 * @import en public/css/modules/variables.css. El CSS no puede leer constantes
 * PHP, así que ese fichero conserva la URL literal y
 * tests/Unit/EmulatorJSGoogleFontsTest.php verifica que sigue coincidiendo con
 * CSS_URL. El resto de puntos —preconnect del layout y las directivas
 * style-src/font-src de la CSP— se generan desde aquí.
 *
 * Ver docs/2026-10-16-centralizacion-hosts-externos.md.
 */

class GoogleFonts {
    /** Origen de las hojas de estilo (preconnect y style-src de la CSP). */
    public const CSS_ORIGIN = 'https://fonts.googleapis.com';

    /** Origen de los ficheros de fuente (preconnect y font-src de la CSP). */
    public const FILES_ORIGIN = 'https://fonts.gstatic.com';

    /** URL del @import que debe coincidir con public/css/modules/variables.css. */
    public const CSS_URL = self::CSS_ORIGIN . '/css2?'
        . 'family=Press+Start+2P&family=Courier+Prime:ital,wght@0,400;0,700;1,400&display=swap';
}
