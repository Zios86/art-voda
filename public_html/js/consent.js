(function () {
    'use strict';

    // Управление согласием: необязательная Метрика запускается только после явного разрешения.

    var STORAGE_KEY = 'kioskvoda_consent_v1';
    var STORAGE_VERSION = '2026-08-14';
    var METRIKA_ID = 42363314;
    var metrikaStarted = false;
    var metrikaScript = null;
    var metrikaGeneration = 0;

    function storageGet(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    }

    function storageSet(key, value) {
        try { window.localStorage.setItem(key, value); return true; } catch (error) { return false; }
    }

    function storageRemove(key) {
        try { window.localStorage.removeItem(key); } catch (error) {}
    }

    function readConsent() {
        var raw = storageGet(STORAGE_KEY);
        if (!raw) return null;
        try {
            var value = JSON.parse(raw);
            return value && value.version === STORAGE_VERSION ? value : null;
        } catch (error) {
            return null;
        }
    }

    function writeConsent(analytics) {
        storageSet(STORAGE_KEY, JSON.stringify({
            version: STORAGE_VERSION,
            analytics: Boolean(analytics),
            savedAt: new Date().toISOString()
        }));
        storageRemove('cookieAccepted');
    }

    function expireCookie(name, domain) {
        var domainPart = domain ? '; domain=' + domain : '';
        document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + domainPart;
    }

    function removeAnalyticsData() {
        var names = ['_ym_uid', '_ym_d', '_ym_isad', '_ym_visorc_42363314', '_ym_metrika_enabled'];
        var host = window.location.hostname;
        var parts = host.split('.');
        var baseDomain = parts.length > 1 ? '.' + parts.slice(-2).join('.') : '';

        document.cookie.split(';').forEach(function (cookie) {
            var name = cookie.split('=')[0].trim();
            if (name.indexOf('_ym') === 0 && names.indexOf(name) === -1) names.push(name);
        });

        names.forEach(function (name) {
            expireCookie(name, '');
            if (host) expireCookie(name, host);
            if (baseDomain) expireCookie(name, baseDomain);
        });

        try {
            Object.keys(window.localStorage).forEach(function (key) {
                if (key.indexOf('_ym') === 0) window.localStorage.removeItem(key);
            });
        } catch (error) {}

        try {
            Object.keys(window.sessionStorage).forEach(function (key) {
                if (key.indexOf('_ym') === 0) window.sessionStorage.removeItem(key);
            });
        } catch (error) {}
    }

    function stopMetrika() {
        metrikaGeneration += 1;

        if (metrikaStarted && typeof window.ym === 'function') {
            try { window.ym(METRIKA_ID, 'destruct'); } catch (error) {}
        }

        document.querySelectorAll('script[data-optional-analytics]').forEach(function (node) {
            node.remove();
        });
        metrikaScript = null;
        metrikaStarted = false;
        removeAnalyticsData();
    }

    function startMetrika() {
        // Скрипт аналитики создаётся динамически, чтобы до согласия не было сетевого запроса.
        if (metrikaStarted) return;
        metrikaStarted = true;
        metrikaGeneration += 1;
        var generation = metrikaGeneration;

        window.ym = window.ym || function () {
            (window.ym.a = window.ym.a || []).push(arguments);
        };
        window.ym.l = Date.now();

        metrikaScript = document.createElement('script');
        metrikaScript.async = true;
        metrikaScript.src = 'https://mc.yandex.ru/metrika/tag.js';
        metrikaScript.dataset.optionalAnalytics = 'true';
        metrikaScript.addEventListener('load', function () {
            if (generation !== metrikaGeneration) stopMetrika();
        });
        metrikaScript.addEventListener('error', function () {
            if (generation === metrikaGeneration) {
                metrikaStarted = false;
                metrikaScript = null;
            }
        });
        document.head.appendChild(metrikaScript);

        window.ym(METRIKA_ID, 'init', {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: false
        });
    }

    function showBanner(settingsMode) {
        var banner = document.getElementById('cookieBanner');
        if (!banner) return;

        var details = document.getElementById('cookieDetails');
        var checkbox = document.getElementById('analyticsConsent');
        var save = banner.querySelector('[data-cookie-save]');
        var accept = banner.querySelector('[data-cookie-accept]');
        var reject = banner.querySelector('[data-cookie-reject]');
        var configure = banner.querySelector('[data-cookie-configure]');
        var current = readConsent();

        if (checkbox) checkbox.checked = Boolean(current && current.analytics);
        if (details) details.hidden = !settingsMode;
        if (save) save.hidden = !settingsMode;
        if (accept) accept.hidden = settingsMode;
        if (reject) reject.hidden = settingsMode;
        if (configure) configure.hidden = settingsMode;
        banner.hidden = false;
    }

    function hideBanner() {
        var banner = document.getElementById('cookieBanner');
        if (banner) banner.hidden = true;
    }

    function applyChoice(analytics) {
        // Отказ удаляет аналитические данные; повторное согласие может запустить Метрику заново.
        var previous = readConsent();
        writeConsent(analytics);
        hideBanner();
        if (analytics) startMetrika();
        else {
            stopMetrika();
            if (previous && previous.analytics) window.location.reload();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('cookieBanner');
        var current = readConsent();

        if (current && current.analytics) startMetrika();
        if (!current) showBanner(false);

        if (banner) {
            banner.querySelector('[data-cookie-accept]').addEventListener('click', function () { applyChoice(true); });
            banner.querySelector('[data-cookie-reject]').addEventListener('click', function () { applyChoice(false); });
            banner.querySelector('[data-cookie-configure]').addEventListener('click', function () { showBanner(true); });
            banner.querySelector('[data-cookie-save]').addEventListener('click', function () {
                var checkbox = document.getElementById('analyticsConsent');
                applyChoice(Boolean(checkbox && checkbox.checked));
            });
        }

        document.querySelectorAll('[data-cookie-settings]').forEach(function (button) {
            button.addEventListener('click', function () { showBanner(true); });
        });
    });

    window.kioskvodaConsent = {
        open: function () { showBanner(true); },
        stopAnalytics: stopMetrika
    };
})();
