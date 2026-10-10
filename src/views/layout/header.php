<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) I18n::getLang()) ?>">
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

    <link rel="icon" type="image/png" href="public/uploads/icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <header>

            <!-- ── BRAND ── Logo + Título ─────────────────── -->
            <div class="header-brand">

                <!-- Logo: icono de la aplicación al inicio del título -->
                <a href="/" class="header-logo-link" title="<?= t('header.inicio_titulo') ?>">
                    <img src="public/uploads/icon.png"
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
                <a href="/admin/dashboard">
                    <i data-i="shield-2" aria-hidden="true"></i>
                    <?= t('nav.panel_admin') ?>
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
                // Conserva los parámetros GET actuales (excepto lang) como campos ocultos.
                $langHidden = [];
                foreach ($_GET as $key => $value) {
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
                ];
                ?>
                <form class="lang-switch" method="get" action="<?= $langAction ?>">
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
