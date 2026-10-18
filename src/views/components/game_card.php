<?php
/**
 * views/components/game_card.php
 * Componente reutilizable de tarjeta de juego del catálogo.
 *
 * Fuente única del markup: lo usan las dos rutas de render que antes estaban
 * duplicadas (y divergían) —
 *   - SSR inicial: src/views/home/index.php
 *   - AJAX:        endpoints/ajax_catalog.php (filtros y paginación)
 *
 * Incluye:
 *   - Portada cuadrada con placeholder de disco siempre presente (se ve cuando
 *     no hay imagen o cuando la imagen falla al cargar; la ruta AJAX no tenía
 *     onerror y dejaba el icono de imagen rota).
 *   - Ribbons sobre la portada, coherentes con la ficha del juego: HACK
 *     (ámbar oscuro, formato_imagen = 'Hack') a la izquierda y NUEVO (rojo,
 *     añadido hace < 30 días) a la derecha.
 *   - Idiomas resumidos: con 1 idioma se muestra su nombre; con 2 o más, el
 *     recuento ({count} idiomas) y la lista completa en el atributo title.
 *   - Tamaño con IntlHelper::formatBytes (MB para ROMs pequeñas, GB para las
 *     grandes) y números localizados.
 *
 * Los botones Jugar/Descargar siguen viniendo de game_actions.php (enlace de
 * descarga firmado con HMAC: no se toca).
 */
require_once __DIR__ . '/../../config/IntlHelper.php';

class GameCard {

    /** Días que una ROM recién añadida se marca como NUEVA (igual que la ficha). */
    private const DIAS_NUEVO = 30;

    /**
     * Renderiza una tarjeta de juego.
     *
     * @param array $juego  Fila del listado (juegos.* + consola_nombre/categoria_nombre)
     * @param int   $index  Posición 1-based en el grid (1 = primera tarjeta)
     * @param bool  $return Si es true devuelve el HTML en lugar de imprimirlo
     */
    public static function render(array $juego, int $index = 0, bool $return = false): string {
        $id      = (int) ($juego['id'] ?? 0);
        $titulo  = (string) ($juego['titulo'] ?? '');
        $slug    = (string) ($juego['slug'] ?? '');
        $imagen  = (string) ($juego['imagen'] ?? '');
        $idiomas = trim((string) ($juego['idiomas'] ?? ''));

        $url       = $slug !== '' ? '/juego/' . rawurlencode($slug) : '/home/show/' . $id;
        $esHack    = ($juego['formato_imagen'] ?? '') === 'Hack';
        $esNuevo   = self::esNuevo($juego['created_at'] ?? null);
        $numIdiomas = self::contarIdiomas($idiomas);

        $html  = '<div class="game-card">';
        $html .= '<div class="game-card-inner">';

        // ── Portada + enlace + título ──────────────────────────────────────
        $html .= '<a href="' . htmlspecialchars($url) . '" class="game-detail-link">';
        $html .= '<div class="game-cover' . ($imagen === '' ? ' no-image' : '') . '">';
        // Disco placeholder siempre presente: se ve cuando no hay imagen o
        // cuando la imagen falla al cargar (onerror la oculta).
        $html .= '<i data-i="disc" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>';
        if ($imagen !== '') {
            $html .= '<img src="' . htmlspecialchars($imagen) . '"'
                   . ' alt="' . htmlspecialchars($titulo) . '"'
                   . ' loading="lazy" decoding="async"'
                   . ($index === 1 ? ' fetchpriority="high"' : '')
                   . ' onerror="this.style.display=\'none\';">';
        }
        // Ribbons (coherentes con la ficha): se pintan sobre la portada por z-index.
        if ($esHack) {
            $html .= '<span class="game-hack-ribbon">'
                   . '<i data-i="zap" aria-hidden="true"></i> ' . t('show.hack')
                   . '</span>';
        }
        if ($esNuevo) {
            $html .= '<span class="game-new-badge">' . t('show.nuevo') . '</span>';
        }
        $html .= '</div>'; // .game-cover
        $html .= '<h3 class="game-title">' . htmlspecialchars($titulo) . '</h3>';
        $html .= '</a>';

        // ── Tags: género / plataforma / región ────────────────────────────
        $html .= '<div class="game-metadata">';
        $html .= '<span class="game-tag genre">'
               . htmlspecialchars((string) ($juego['categoria_nombre'] ?? t('home.sin_genero')))
               . '</span>';
        $html .= '<span class="game-tag platform">'
               . htmlspecialchars((string) ($juego['consola_nombre'] ?? t('home.multi')))
               . '</span>';
        $html .= '<span class="game-tag language">'
               . htmlspecialchars((string) ($juego['region'] ?? t('home.region_all')))
               . '</span>';
        $html .= '</div>';

        // ── Panel de datos ────────────────────────────────────────────────
        $html .= '<div class="game-info">';

        $html .= '<div class="game-info-item">';
        $html .= '<span class="game-info-label">' . t('home.idiomas') . '</span>';
        if ($numIdiomas >= 2) {
            // Resumen + lista completa en el tooltip (antes se cortaba con
            // ellipsis y la información quedaba perdida).
            $html .= '<span class="game-info-value" title="' . htmlspecialchars($idiomas) . '">'
                   . htmlspecialchars(t('component.idiomas_varios'))
                   . '</span>';
        } else {
            // 0 o 1 idioma: el propio nombre (o el fallback) ya es la versión
            // más corta posible; no hay nada que resumir.
            $html .= '<span class="game-info-value">'
                   . htmlspecialchars($idiomas !== '' ? $idiomas : 'English')
                   . '</span>';
        }
        $html .= '</div>';

        $html .= '<div class="game-info-item">';
        $html .= '<span class="game-info-label">' . t('home.tamano') . '</span>';
        $html .= '<span class="game-info-value">' . IntlHelper::formatBytes($juego['size_bytes'] ?? 0) . '</span>';
        $html .= '</div>';

        if (!empty($juego['downloads_count'])) {
            $html .= '<div class="game-info-item">';
            $html .= '<span class="game-info-label">' . t('home.descargas') . '</span>';
            $html .= '<span class="game-info-value">' . IntlHelper::formatNumber($juego['downloads_count']) . '</span>';
            $html .= '</div>';
        }

        if (!empty($juego['plays_count'])) {
            $html .= '<div class="game-info-item">';
            $html .= '<span class="game-info-label">' . t('home.jugadas') . '</span>';
            $html .= '<span class="game-info-value">' . IntlHelper::formatNumber($juego['plays_count']) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .game-info

        // ── Acciones (Jugar online + Descargar firmada) ───────────────────
        // Contexto compacto: game_actions.php lee $juego y $detailContext.
        $detailContext = false;
        ob_start();
        require __DIR__ . '/game_actions.php';
        $html .= (string) ob_get_clean();

        $html .= '</div>'; // .game-card-inner
        $html .= '</div>'; // .game-card

        if (!$return) {
            echo $html;
        }
        return $html;
    }

    /**
     * ROM añadida en los últimos DIAS_NUEVO días (mismo criterio que la ficha).
     */
    private static function esNuevo(?string $createdAt): bool {
        if ($createdAt === null || $createdAt === '') {
            return false;
        }
        $ts = strtotime($createdAt);
        return $ts !== false && (time() - $ts) < self::DIAS_NUEVO * 86400;
    }

    /**
     * Número de idiomas del campo `juegos.idiomas` (texto separado por coma,
     * punto o punto y coma — mismo criterio que IdiomasNormalizer y la ficha).
     */
    private static function contarIdiomas(string $raw): int {
        if ($raw === '') {
            return 0;
        }
        $total = 0;
        foreach (preg_split('/[,.;]/u', $raw) ?: [] as $token) {
            if (trim($token) !== '') {
                $total++;
            }
        }
        return $total;
    }
}
