(function () {
    'use strict';

    // Общие интерактивные функции сайта: меню, анимации, калькулятор, карта и установка PWA.

    document.documentElement.classList.add('js');
    var installPrompt = null;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        installPrompt = event;
        document.querySelectorAll('[data-install-app]').forEach(function (button) { button.hidden = false; });
    });

    function setMenu(open) {
        var menu = document.getElementById('mobile-menu');
        var toggle = document.getElementById('menu-toggle');
        if (!menu || !toggle) return;

        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
        document.body.classList.toggle('menu-is-open', open);
    }

    function normalizePath(value) {
        try {
            var url = new URL(value, window.location.origin);
            return url.pathname === '/index.php' ? '/' : url.pathname;
        } catch (error) {
            return value;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Все DOM-зависимые обработчики подключаются после готовности разметки.
        var toggle = document.getElementById('menu-toggle');
        var menu = document.getElementById('mobile-menu');
        var currentPath = normalizePath(window.location.href);

        if (toggle) {
            toggle.addEventListener('click', function () {
                setMenu(toggle.getAttribute('aria-expanded') !== 'true');
            });
        }

        if (menu) {
            menu.addEventListener('click', function (event) {
                if (event.target.closest('a')) setMenu(false);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMenu(false);
                if (toggle) toggle.focus();
            }
        });

        document.querySelectorAll('#desktop-menu a, #mobile-menu a').forEach(function (link) {
            var linkUrl = new URL(link.href, window.location.origin);
            if (normalizePath(link.href) === currentPath && (currentPath !== '/' || linkUrl.hash === '')) {
                link.classList.add('active-btn');
            }
        });

        var header = document.getElementById('site-header');
        function updateHeader() {
            if (header) header.classList.toggle('is-scrolled', window.scrollY > 16);
        }
        updateHeader();
        window.addEventListener('scroll', updateHeader, {passive: true});

        var revealItems = document.querySelectorAll('[data-reveal]');
        // IntersectionObserver запускает анимацию один раз и не нагружает постоянный scroll.
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            revealItems.forEach(function (item) { item.classList.add('is-visible'); });
        } else {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, {rootMargin: '0px 0px -8% 0px', threshold: 0.08});
            revealItems.forEach(function (item) { observer.observe(item); });
        }

        var bottle = document.querySelector('[data-water-bottle]');
        if (bottle) {
            if (!('IntersectionObserver' in window)) bottle.classList.add('is-filled');
            else {
                var bottleObserver = new IntersectionObserver(function (entries) {
                    if (entries[0].isIntersecting) { bottle.classList.add('is-filled'); bottleObserver.disconnect(); }
                }, {threshold: 0.35});
                bottleObserver.observe(bottle);
            }
        }

        var calculator = document.getElementById('savings-calculator');
        if (calculator) {
            var litres = document.getElementById('daily-litres');
            var comparison = document.getElementById('comparison-price');
            var money = new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 0});
            function updateSavings() {
                var daily = Math.max(0, Number(litres.value) || 0);
                var otherPrice = Math.max(10, Number(comparison.value) || 10);
                var kioskMonthly = daily * 30 * 10;
                var otherMonthly = daily * 30 * otherPrice;
                document.getElementById('kiosk-monthly').textContent = money.format(kioskMonthly) + ' ₽';
                document.getElementById('other-monthly').textContent = money.format(otherMonthly) + ' ₽';
                document.getElementById('monthly-saving').textContent = money.format(Math.max(0, otherMonthly - kioskMonthly)) + ' ₽/мес.';
            }
            litres.addEventListener('input', updateSavings);
            comparison.addEventListener('input', updateSavings);
            calculator.addEventListener('submit', function (event) { event.preventDefault(); });
            updateSavings();
        }

        document.querySelectorAll('[data-volume]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('[data-volume]').forEach(function (item) { item.classList.toggle('is-selected', item === button); });
                var volume = Number(button.getAttribute('data-volume'));
                document.getElementById('volume-price').textContent = 'Объём ' + String(volume).replace('.', ',') + ' л = ' + String(volume * 10).replace('.', ',') + ' ₽';
            });
        });

        function openNearest() {
            // Если карта ещё не загружена, сначала имитируем нажатие на её безопасный placeholder.
            var section = document.getElementById('marketplace');
            if (section) section.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
            if (window.kioskvodaMap) { window.kioskvodaMap.findNearest(); return; }
            var loadButton = document.querySelector('#map [data-load-external]');
            if (loadButton) loadButton.click();
            document.addEventListener('kioskvoda-map-ready', function () { if (window.kioskvodaMap) window.kioskvodaMap.findNearest(); }, {once: true});
        }
        document.querySelectorAll('[data-nearest-kiosk]').forEach(function (button) { button.addEventListener('click', openNearest); });

        document.querySelectorAll('[data-install-app]').forEach(function (button) {
            var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
            var isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
            if (installPrompt || (isIos && !isStandalone)) button.hidden = false;
            button.addEventListener('click', function () {
                if (!installPrompt) {
                    var help = document.getElementById('install-help');
                    if (help && typeof help.showModal === 'function') help.showModal();
                    return;
                }
                installPrompt.prompt();
                installPrompt.userChoice.finally(function () { installPrompt = null; button.hidden = true; });
            });
        });
        window.addEventListener('appinstalled', function () { document.querySelectorAll('[data-install-app]').forEach(function (button) { button.hidden = true; }); });

        if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
            window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
        }
    });
})();
