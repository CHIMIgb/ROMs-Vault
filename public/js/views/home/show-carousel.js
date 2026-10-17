// views/home/show-carousel.js - carrusel de capturas y visor (lightbox)
(function () {
    'use strict';
    // ── Carrusel de capturas ──
    var carousel = document.querySelector('[data-carousel]');
    if (!carousel) return;

    var track   = carousel.querySelector('[data-carousel-track]');
    var slides  = track.children;
    var prev    = carousel.querySelector('[data-carousel-prev]');
    var next    = carousel.querySelector('[data-carousel-next]');
    var counter = carousel.querySelector('[data-carousel-counter]');
    var thumbs  = Array.prototype.slice.call(document.querySelectorAll('[data-carousel-thumb]'));
    var index   = 0;
    var total   = slides.length;
    if (!total) return;

    function goTo(i) {
        if (i < 0) i = total - 1;
        if (i >= total) i = 0;
        index = i;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
        if (counter) counter.textContent = (index + 1) + ' / ' + total;
        thumbs.forEach(function (t, ti) {
            var active = ti === index;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-current', active ? 'true' : 'false');
        });
    }

    if (prev) prev.addEventListener('click', function () { goTo(index - 1); });
    if (next) next.addEventListener('click', function () { goTo(index + 1); });

    thumbs.forEach(function (t) {
        t.addEventListener('click', function () {
            goTo(parseInt(t.getAttribute('data-carousel-thumb'), 10));
        });
    });

    // Flechas del teclado solo cuando el foco está dentro del carrusel
    document.addEventListener('keydown', function (e) {
        if (!carousel.contains(document.activeElement)) return;
        if (e.key === 'ArrowLeft')  { e.preventDefault(); goTo(index - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); goTo(index + 1); }
    });

    // Swipe táctil
    var startX = null;
    carousel.addEventListener('touchstart', function (e) {
        startX = e.touches[0].clientX;
    }, { passive: true });
    carousel.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) {
            goTo(index + (dx < 0 ? 1 : -1));
        }
        startX = null;
    }, { passive: true });

    // ── Visor a pantalla completa (lightbox) ──────────────────────────────
    var lightbox = document.querySelector('[data-lightbox]');
    if (!lightbox) return;

    var lbTrack     = lightbox.querySelector('[data-lightbox-track]');
    var lbPrev      = lightbox.querySelector('[data-lightbox-prev]');
    var lbNext      = lightbox.querySelector('[data-lightbox-next]');
    var lbCounter   = lightbox.querySelector('[data-lightbox-counter]');
    var lbThumbsBox = lightbox.querySelector('[data-lightbox-thumbs]');
    var lbCloseBtns = Array.prototype.slice.call(lightbox.querySelectorAll('[data-lightbox-close]'));
    var lastFocused = null;
    var lbIndex     = 0;

    // Slides y miniaturas del visor: clonadas del carrusel (mismos src → caché)
    var lbSlides = [];
    Array.prototype.forEach.call(slides, function (slide) {
        var clone = slide.cloneNode(true);
        clone.classList.add('screenshot-slide--lightbox');
        var openBtn = clone.querySelector('.screenshot-open');
        if (openBtn) openBtn.remove();
        lbSlides.push(clone);
        lbTrack.appendChild(clone);
    });

    var lbThumbs = [];
    Array.prototype.forEach.call(thumbs, function (thumb) {
        var clone = thumb.cloneNode(true);
        clone.setAttribute('data-lightbox-thumb', thumb.getAttribute('data-carousel-thumb'));
        clone.removeAttribute('data-carousel-thumb');
        lbThumbs.push(clone);
        lbThumbsBox.appendChild(clone);
    });

    function goToLb(i) {
        if (i < 0) i = total - 1;
        if (i >= total) i = 0;
        lbIndex = i;
        lbTrack.style.transform = 'translateX(-' + (lbIndex * 100) + '%)';
        if (lbCounter) lbCounter.textContent = (lbIndex + 1) + ' / ' + total;
        lbThumbs.forEach(function (t, ti) {
            var active = ti === lbIndex;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-current', active ? 'true' : 'false');
        });
    }

    function lbKeyHandler(e) {
        if (e.key === 'Escape') { e.preventDefault(); closeLb(); return; }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); goToLb(lbIndex - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); goToLb(lbIndex + 1); }
    }

    function openLb(i) {
        lastFocused = document.activeElement;
        goToLb(i);
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
        var closeBtn = lightbox.querySelector('.screenshot-lightbox-close');
        if (closeBtn) closeBtn.focus();
        document.addEventListener('keydown', lbKeyHandler);
    }

    function closeLb() {
        lightbox.hidden = true;
        document.body.style.overflow = '';
        document.removeEventListener('keydown', lbKeyHandler);
        if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    // Abrir desde cada captura del carrusel
    var openBtns = carousel.querySelectorAll('[data-carousel-open]');
    Array.prototype.forEach.call(openBtns, function (b) {
        b.addEventListener('click', function () {
            openLb(parseInt(b.getAttribute('data-carousel-open'), 10));
        });
    });

    if (lbPrev) lbPrev.addEventListener('click', function () { goToLb(lbIndex - 1); });
    if (lbNext) lbNext.addEventListener('click', function () { goToLb(lbIndex + 1); });

    lbThumbs.forEach(function (t) {
        t.addEventListener('click', function () {
            goToLb(parseInt(t.getAttribute('data-lightbox-thumb'), 10));
        });
    });

    lbCloseBtns.forEach(function (b) {
        b.addEventListener('click', closeLb);
    });

    // Swipe táctil dentro del visor
    var lbStartX = null;
    lightbox.addEventListener('touchstart', function (e) {
        lbStartX = e.touches[0].clientX;
    }, { passive: true });
    lightbox.addEventListener('touchend', function (e) {
        if (lbStartX === null) return;
        var dx = e.changedTouches[0].clientX - lbStartX;
        if (Math.abs(dx) > 40) {
            goToLb(lbIndex + (dx < 0 ? 1 : -1));
        }
        lbStartX = null;
    }, { passive: true });
})();
