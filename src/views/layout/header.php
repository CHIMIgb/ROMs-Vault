<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) I18n::getLang()) ?>" dir="<?= I18n::isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Base del documento: las URLs limpias (/controlador/accion) hacen que
         las rutas relativas (public/css, public/js, rom_proxy.php, etc.)
         deban resolverse siempre contra la raíz del sitio. -->
    <base href="/">
    <?php if (class_exists('CsrfService')): ?>
        <?= CsrfService::metaTag() ?>
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle ?? t('header.catalogo_titulo')) ?></title>
    <?php if (!empty($pageDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?>

    <?php if (!empty($pageType)): ?>
        <!-- Open Graph / compartir en redes -->
        <meta property="og:type" content="<?= htmlspecialchars($pageType) ?>">
        <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? t('app.nombre')) ?>">
        <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
        <meta property="og:image" content="<?= htmlspecialchars($pageImage ?? '') ?>">
        <meta property="og:url" content="<?= htmlspecialchars($pageUrl ?? '') ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? t('app.nombre')) ?>">
        <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
        <meta name="twitter:image" content="<?= htmlspecialchars($pageImage ?? '') ?>">
    <?php endif; ?>

    <?php if (!empty($pageJsonLd)): ?>
        <!-- Datos estructurados VideoGame (SEO) -->
        <script type="application/ld+json"><?= json_encode($pageJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>

    <link rel="icon" type="image/png" href="public/img/icon.png">

    <link rel="preconnect" href="<?= GoogleFonts::CSS_ORIGIN ?>">
    <link rel="preconnect" href="<?= GoogleFonts::FILES_ORIGIN ?>" crossorigin>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <header>

            <!-- ── BRAND ── Logo + Título ─────────────────── -->
            <div class="header-brand">

                <!-- Logo: icono de la aplicación al inicio del título -->
                <a href="/" class="header-logo-link" title="<?= t('header.inicio_titulo') ?>">
                    <img src="public/img/icon.png"
                         alt="<?= t('app.nombre') ?>"
                         class="header-logo"
                         width="56" height="56">
                </a>

                <div class="header-brand-text">
                    <h1><?= t('app.nombre') ?></h1>
                    <p class="subtitle">
                        <!-- play icon -->
                        <i data-i="play" data-cls="pxi-subtitle" aria-hidden="true"></i>
                        <?= t('app.slogan') ?>
                    </p>
                </div>
            </div>
            <!-- ────────────────────────────────────────────── -->

            <nav>
                <a href="/">
                    <i data-i="gamepad"  aria-hidden="true"></i>
                    <?= t('nav.catalogo') ?>
                </a>
                <?php 
                $currentUser = AuthMiddleware::getUser();
                if ($currentUser): 
                ?>
                <a href="/consola/index">
                    <i data-i="gamepad" aria-hidden="true"></i>
                    <?= t('nav.consolas') ?>
                </a>
                <a href="/emulador/index">
                    <i data-i="joystick" aria-hidden="true"></i>
                    <?= t('nav.emuladores') ?>
                </a>
                <a href="/categoria/index">
                    <i data-i="dashboard" aria-hidden="true"></i>
                    <?= t('nav.categorias') ?>
                </a>
                <a href="/admin/tfa">
                    <i data-i="shield-2" aria-hidden="true"></i>
                    <?= t('nav.2fa') ?>
                </a>
                <a href="/auth/logout">
                    <i data-i="logout"   aria-hidden="true"></i>
                    <?= t('nav.cerrar_sesion') ?>
                </a>
                <?php endif; ?>

                <?php
                // ── Selector de idioma (FASE 5.3) ───────────────────────────
                // Reutiliza el flujo ?lang= de index.php: persiste la cookie
                // rv_lang y redirige a la misma URL sin el parámetro.
                //
                // IMPORTANTE: un formulario GET REEMPLAZA la query string del
                // `action` con sus propios campos. Por eso NO podemos poner los
                // parámetros actuales en el action (se perderían: p.ej. el
                // file_id de /home/play). En su lugar, el action apunta SOLO a
                // la ruta y los parámetros actuales viajan como campos ocultos.
                // Banderas como SVG inline: renderizado garantizado en todos
                // los SO (a diferencia de emoji) y compatible con la CSP.
                // Un <select> nativo no admite SVG dentro de <option>, así que
                // usamos un desplegable propio (CSS-only vía :focus-within) en
                // el que cada opción es un <button type="submit" name="lang">.
                $lang = I18n::getLang();
                $langPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
                $langAction = htmlspecialchars($langPath, ENT_QUOTES, 'UTF-8');
                // Conserva los parámetros que el visitante envió de verdad en la
                // URL (filtros, y en las URLs legacy controller/action/id),
                // excepto lang. Se lee de QUERY_STRING y no de $_GET porque el
                // router añade a $_GET los de la ruta limpia: en
                // /juego/power-rangers-ninja-storm el formulario reenviaría
                // ?controller=home&action=showBySlug&id=power-rangers-ninja-storm,
                // duplicando en la query lo que ya va en la ruta.
                $langHidden = [];
                $langQueryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
                if ($langQueryString !== '') {
                    $langQueryParams = [];
                    parse_str($langQueryString, $langQueryParams);
                    foreach ($langQueryParams as $key => $value) {
                        if ($key === 'lang') {
                            continue;
                        }
                        if (is_array($value)) {
                            foreach ($value as $v) {
                                if (is_scalar($v)) {
                                    $langHidden[] = [$key . '[]', (string)$v];
                                }
                            }
                        } elseif (is_scalar($value)) {
                            $langHidden[] = [$key, (string)$value];
                        }
                    }
                }
                $langFlags = [
                    'es' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect width="7" height="14" fill="#006847"/>'
                          . '<rect x="13" width="7" height="14" fill="#ce1126"/>'
                          . '<circle cx="10" cy="7" r="1.8" fill="#8a6d3b"/>'
                          . '</svg>',
                    'en' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<g fill="#b22234">'
                          . '<rect y="0" width="20" height="1.08"/><rect y="2.15" width="20" height="1.08"/>'
                          . '<rect y="4.31" width="20" height="1.08"/><rect y="6.46" width="20" height="1.08"/>'
                          . '<rect y="8.62" width="20" height="1.08"/><rect y="10.77" width="20" height="1.08"/>'
                          . '<rect y="12.92" width="20" height="1.08"/>'
                          . '</g>'
                          . '<rect width="8" height="7.5" fill="#3c3b6e"/>'
                          . '<g fill="#ffffff">'
                          . '<circle cx="1.6" cy="1.5" r="0.34"/><circle cx="4" cy="1.5" r="0.34"/><circle cx="6.4" cy="1.5" r="0.34"/>'
                          . '<circle cx="2.8" cy="3.75" r="0.34"/><circle cx="5.2" cy="3.75" r="0.34"/>'
                          . '<circle cx="1.6" cy="6" r="0.34"/><circle cx="4" cy="6" r="0.34"/><circle cx="6.4" cy="6" r="0.34"/>'
                          . '</g>'
                          . '</svg>',
                    'fr' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect width="6.67" height="14" fill="#0055a4"/>'
                          . '<rect x="13.33" width="6.67" height="14" fill="#ef4135"/>'
                          . '</svg>',
                    'pt' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#009c3b"/>'
                          . '<polygon points="10,1.6 18.4,7 10,12.4 1.6,7" fill="#ffdf00"/>'
                          . '<circle cx="10" cy="7" r="3" fill="#002776"/>'
                          . '<path d="M7.2 6.1 A3 3 0 0 0 12.8 6.1" stroke="#ffffff" stroke-width="0.7" fill="none"/>'
                          . '</svg>',
                    'de' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="4.67" fill="#000000"/>'
                          . '<rect y="4.67" width="20" height="4.67" fill="#dd0000"/>'
                          . '<rect y="9.33" width="20" height="4.67" fill="#ffce00"/>'
                          . '</svg>',
                    'ja' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect x="0.5" y="0.5" width="19" height="13" fill="none" stroke="#e0e0e0" stroke-width="0.5"/>'
                          . '<circle cx="10" cy="7" r="4" fill="#bc002d"/>'
                          . '</svg>',
                    'it' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect width="6.67" height="14" fill="#009246"/>'
                          . '<rect x="13.33" width="6.67" height="14" fill="#ce2b37"/>'
                          . '</svg>',
                    'ru' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect y="4.67" width="20" height="4.67" fill="#0039a6"/>'
                          . '<rect y="9.33" width="20" height="4.67" fill="#d52b1e"/>'
                          . '<rect x="0.5" y="0.5" width="19" height="13" fill="none" stroke="#e0e0e0" stroke-width="0.5"/>'
                          . '</svg>',
                    'tr' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#e30a17"/>'
                          . '<circle cx="7.5" cy="7" r="3.5" fill="#ffffff"/>'
                          . '<circle cx="8.9" cy="7" r="2.8" fill="#e30a17"/>'
                          . '<polygon points="13,5.1 13.45,6.39 14.81,6.41 13.72,7.23 14.12,8.54 13,7.76 11.88,8.54 12.28,7.23 11.19,6.41 12.55,6.39" fill="#ffffff"/>'
                          . '</svg>',
                    'zh' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#de2910"/>'
                          . '<polygon points="4.3,2.2 4.85,3.6 6.3,3.6 5.1,4.5 5.55,5.9 4.3,5.05 3.05,5.9 3.5,4.5 2.3,3.6 3.75,3.6" fill="#ffde00"/>'
                          . '<circle cx="8.4" cy="5.2" r="0.9" fill="#ffde00"/>'
                          . '<circle cx="9.55" cy="6.9" r="0.6" fill="#ffde00"/>'
                          . '<circle cx="8.1" cy="8.35" r="0.55" fill="#ffde00"/>'
                          . '<circle cx="6.85" cy="6.95" r="0.55" fill="#ffde00"/>'
                          . '</svg>',
                    'ko' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect x="0.5" y="0.5" width="19" height="13" fill="none" stroke="#cdcdcd" stroke-width="0.5"/>'
                          . '<g stroke="#000000" stroke-width="0.9">'
                          . '<path d="M10 1.8 Q11.8 4 10 6.2" fill="none"/>'
                          . '<path d="M10 6.2 Q8.2 8.4 10 10.6" fill="none"/>'
                          . '<path d="M10 10.6 Q11.8 12.8 10 15" fill="none"/>'
                          . '</g>'
                          . '<g stroke="#000000" stroke-width="0.5">'
                          . '<rect x="3.4" y="3.3" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="3.4" y="4.7" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="3.4" y="3.3" width="0.6" height="2" fill="#000000" stroke="none"/>'
                          . '<rect x="15" y="8.3" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="15" y="9.7" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="15" y="8.3" width="0.6" height="2" fill="#000000" stroke="none"/>'
                          . '<rect x="3.4" y="8.3" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="3.4" y="9.7" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="3.4" y="8.3" width="0.6" height="2" fill="#000000" stroke="none"/>'
                          . '<rect x="15" y="3.3" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="15" y="4.7" width="1.6" height="0.6" fill="#000000" stroke="none"/>'
                          . '<rect x="15" y="3.3" width="0.6" height="2" fill="#000000" stroke="none"/>'
                          . '</g>'
                          . '<circle cx="10" cy="7" r="1.7" fill="#cd2e3a"/>'
                          . '<path d="M10 5.3 Q12.5 7 10 8.7 Z" fill="#0047a0"/>'
                          . '<path d="M10 5.3 Q7.5 7 10 8.7 Z" fill="#cd2e3a"/>'
                          . '</svg>',
                    'ar' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#165d31"/>'
                          . '<path d="M5.7 5.6 Q10 2.8 14.3 5.6 L14.3 8.4 Q10 11.2 5.7 8.4 Z" fill="none" stroke="#ffffff" stroke-width="1.1"/>'
                          . '<path d="M5.2 3.6 L7.9 5.7 L8.3 5.3 L5.9 3.4 L7.2 2.3 L6.6 1.9 L5.6 2.8 L4.6 1.6 L4.1 2 L5.4 3.2 L3.5 4.4 L3.9 4.9 Z" fill="#ffffff"/>'
                          . '</svg>',
                ];
                ?>
                <form class="lang-switch" method="get" action="<?= $langAction ?>" data-enhance="lang">
                    <?php foreach ($langHidden as [$hk, $hv]): ?>
                        <input type="hidden" name="<?= htmlspecialchars($hk, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($hv, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <button type="button" class="lang-trigger"
                            aria-haspopup="listbox"
                            aria-label="<?= htmlspecialchars(t('header.seleccionar_idioma')) ?>"
                            title="<?= htmlspecialchars(t('header.seleccionar_idioma')) ?>">
                        <?= $langFlags[$lang] ?? '' ?>
                        <span class="lang-code"><?= htmlspecialchars(strtoupper($lang), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                    <ul class="lang-menu" role="listbox"
                        aria-label="<?= htmlspecialchars(t('header.seleccionar_idioma')) ?>">
                        <?php foreach ($langFlags as $code => $flag): ?>
                            <li>
                                <button type="submit" name="lang" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                        class="lang-option" role="option"
                                        aria-selected="<?= $lang === $code ? 'true' : 'false' ?>">
                                    <?= $flag ?>
                                    <span class="lang-code"><?= htmlspecialchars(strtoupper($code), ENT_QUOTES, 'UTF-8') ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </form>
            </nav>
        </header>
        <main>
