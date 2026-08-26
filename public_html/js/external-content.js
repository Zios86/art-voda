(function () {
    'use strict';

    // Rutube и Яндекс.Карты не загружаются до клика: это быстрее и лучше для приватности.

    function setFailure(container, message) {
        container.removeAttribute('data-loading');
        container.innerHTML = '<div class="external-placeholder__inner"><p></p><button type="button" class="external-placeholder__button" data-load-external>Попробовать снова</button></div>';
        container.querySelector('p').textContent = message;
    }

    function loadIframe(container) {
        var src = container.getAttribute('data-external-iframe');
        if (!src) return;

        var iframe = document.createElement('iframe');
        iframe.src = src;
        iframe.title = container.getAttribute('data-title') || 'Внешний материал';
        iframe.loading = 'lazy';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allow = container.getAttribute('data-allow') || '';
        iframe.allowFullscreen = true;
        iframe.width = '560';
        iframe.height = '315';
        iframe.style.width = '100%';
        iframe.style.border = '0';
        container.replaceChildren(iframe);
    }

    function loadMainMap(container) {
        // Сначала загружается API Яндекса, затем локальный код интерфейса карты.
        var apiUrl = container.getAttribute('data-yandex-map-loader');
        var mapCode = container.getAttribute('data-map-code');
        container.innerHTML = '<div class="external-placeholder"><div class="external-placeholder__inner"><p>Загружаем карту…</p></div></div>';

        var api = document.createElement('script');
        api.src = apiUrl;
        api.async = true;
        api.onload = function () {
            container.innerHTML = '';
            var code = document.createElement('script');
            code.src = mapCode;
            code.async = true;
            code.onerror = function () { setFailure(container, 'Не удалось загрузить карту. Попробуйте позже.'); };
            document.head.appendChild(code);
        };
        api.onerror = function () { setFailure(container, 'Не удалось загрузить сервис Яндекс.Карт.'); };
        document.head.appendChild(api);
    }

    function loadConstructorMap(container) {
        var src = container.getAttribute('data-yandex-constructor');
        container.innerHTML = '<div class="external-placeholder"><div class="external-placeholder__inner"><p>Загружаем карту…</p></div></div>';
        var script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.charset = 'utf-8';
        script.onerror = function () { setFailure(container, 'Не удалось загрузить сервис Яндекс.Карт.'); };
        container.innerHTML = '';
        container.appendChild(script);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-load-external]');
        if (!button) return;
        var container = button.closest('[data-external-iframe], [data-yandex-map-loader], [data-yandex-constructor]');
        if (!container) return;
        if (container.getAttribute('data-loading') === 'true') return;
        container.setAttribute('data-loading', 'true');

        if (container.hasAttribute('data-external-iframe')) {
            loadIframe(container);
            container.removeAttribute('data-loading');
        }
        else if (container.hasAttribute('data-yandex-map-loader')) loadMainMap(container);
        else if (container.hasAttribute('data-yandex-constructor')) loadConstructorMap(container);
    });
})();
