(function () {
    'use strict';

    // Офлайн-поиск читает только локальный запасной JSON и не обращается к MySQL.
    fetch('/data/kiosks.json').then(function (response) { return response.json(); }).then(function (data) {
        var input = document.getElementById('offline-search');
        var list = document.getElementById('offline-list');
        var items = data.kiosks || [];
        function draw() {
            var query = input.value.trim().toLowerCase();
            var found = items.filter(function (item) { return (item.address + ' ' + (item.machine_number || '')).toLowerCase().includes(query); }).slice(0, 20);
            list.replaceChildren();
            found.forEach(function (item) {
                var card = document.createElement('article');
                var title = document.createElement('strong');
                var address = document.createElement('span');
                var status = document.createElement('span');
                card.className = 'item';
                title.textContent = item.machine_number ? 'Автомат №' + item.machine_number : 'Точка продажи';
                address.textContent = item.address;
                status.className = 'status';
                status.textContent = item.status === 'maintenance' ? 'Временно не работает' : item.status === 'planned' ? 'Скоро открытие' : 'Работает';
                card.append(title, address, status);
                list.append(card);
            });
            if (!found.length) list.innerHTML = '<p class="empty">Ничего не найдено</p>';
        }
        input.addEventListener('input', draw);
        draw();
    }).catch(function () {
        document.getElementById('offline-list').innerHTML = '<p class="empty">Список ещё не сохранён на устройстве.</p>';
    });
})();
