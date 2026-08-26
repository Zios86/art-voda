(function () {
    'use strict';

    // Родительская часть карты админки: предпросмотр формы и защищённый обмен с iframe.

    var tableSearch = document.getElementById('table-search');
    if (tableSearch) {
        tableSearch.addEventListener('input', function () {
            var query = tableSearch.value.toLocaleLowerCase('ru');
            document.querySelectorAll('#kiosk-table tbody tr').forEach(function (row) {
                row.hidden = row.textContent.toLocaleLowerCase('ru').indexOf(query) === -1;
            });
        });
    }

    var form = document.querySelector('.admin-form');
    if (form) {
        var previewPhoto = document.getElementById('preview-photo');
        var photoInput = document.getElementById('photo');
        function updatePreview() {
            var number = document.getElementById('machine-number').value.trim();
            var addressValue = document.getElementById('address').value.trim();
            var scheduleValue = document.getElementById('schedule').value.trim();
            var metroValue = document.getElementById('metro').value.trim();
            document.getElementById('preview-title').textContent = number ? 'Автомат №' + number : 'Новый автомат';
            document.getElementById('preview-address').textContent = addressValue || 'Адрес появится здесь';
            document.getElementById('preview-details').textContent = [scheduleValue, metroValue ? 'метро ' + metroValue : ''].filter(Boolean).join(' · ') || 'Режим работы';
        }
        form.addEventListener('input', updatePreview); form.addEventListener('change', updatePreview);
        if (photoInput && previewPhoto) photoInput.addEventListener('change', function () {
            var file = photoInput.files && photoInput.files[0]; if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 5 * 1024 * 1024) { photoInput.value=''; alert('Выберите JPG, PNG или WebP размером до 5 МБ.'); return; }
            previewPhoto.src = URL.createObjectURL(file); previewPhoto.hidden = false;
        });
        updatePreview();
    }

    var frame = document.getElementById('admin-map-frame');
    var latitude = document.getElementById('latitude');
    var longitude = document.getElementById('longitude');
    var address = document.getElementById('address');
    var findAddress = document.getElementById('find-address');
    if (!frame || !latitude || !longitude || !address || !findAddress) return;

    var random = new Uint32Array(4);
    // Случайный channel не позволяет постороннему сообщению подменить координаты формы.
    window.crypto.getRandomValues(random);
    var channel = Array.from(random).map(function (value) { return value.toString(16); }).join('-');

    function currentCoordinates() {
        var lat = Number(latitude.value);
        var lng = Number(longitude.value);
        return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : [59.94, 30.32];
    }

    function send(type, details) {
        if (!frame.contentWindow) return;
        frame.contentWindow.postMessage(Object.assign({type: type, channel: channel}, details || {}), '*');
    }

    frame.addEventListener('load', function () {
        send('kioskvoda-map-init', {coordinates: currentCoordinates()});
    });

    findAddress.addEventListener('click', function () {
        var value = address.value.trim();
        if (value) send('kioskvoda-map-search', {address: value});
    });

    window.addEventListener('message', function (event) {
        if (event.source !== frame.contentWindow) return;
        var data = event.data;
        if (data && data.type === 'kioskvoda-map-ready') {
            send('kioskvoda-map-init', {coordinates: currentCoordinates()});
            return;
        }
        if (!data || data.type !== 'kioskvoda-map-coordinates' || data.channel !== channel) return;
        var lat = Number(data.coordinates && data.coordinates[0]);
        var lng = Number(data.coordinates && data.coordinates[1]);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < 59 || lat > 61 || lng < 29 || lng > 32) return;
        latitude.value = lat.toFixed(6);
        longitude.value = lng.toFixed(6);
    });

    window.addEventListener('message', function (event) {
        if (event.source !== frame.contentWindow || !event.data || event.data.type !== 'kioskvoda-map-suggestion' || event.data.channel !== channel) return;
        var suggestion = event.data;
        var areaField = document.getElementById('area');
        var metroField = document.getElementById('metro');
        if (typeof suggestion.address === 'string' && suggestion.address.trim()) address.value = suggestion.address.trim().slice(0,255);
        if (areaField && typeof suggestion.area === 'string' && suggestion.area.trim() && !areaField.value.trim()) areaField.value = suggestion.area.trim().slice(0,100);
        if (metroField && typeof suggestion.metro === 'string' && suggestion.metro.trim() && !metroField.value.trim()) metroField.value = suggestion.metro.trim().replace(/^метро\s+/i,'').slice(0,100);
        form.dispatchEvent(new Event('input',{bubbles:true}));
    });
})();
