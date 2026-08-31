(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-confirm]');
        if (!form) return;
        var message = form.getAttribute('data-confirm') || 'Продолжить операцию?';
        if (!window.confirm(message)) event.preventDefault();
    });
})();
