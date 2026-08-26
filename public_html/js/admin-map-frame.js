(function () {
    'use strict';
    // Код изолированного iframe: клик/перетаскивание метки и поиск адреса через Яндекс.
    var channel = '';
    var pendingCoordinates = [59.94, 30.32];
    var map = null;
    var marker = null;
    var apiReady = false;

    function validCoordinates(value) {
        var lat = Number(value && value[0]);
        var lng = Number(value && value[1]);
        return Number.isFinite(lat) && Number.isFinite(lng) && lat >= 59 && lat <= 61 && lng >= 29 && lng <= 32 ? [lat, lng] : null;
    }
    function sendCoordinates(coords) {
        if (channel) parent.postMessage({type: 'kioskvoda-map-coordinates', channel: channel, coordinates: coords}, '*');
    }
    function sendAddressSuggestion(object, coords) {
        if (!channel || !object) return;
        var meta = object.properties.get('metaDataProperty.GeocoderMetaData') || {};
        var components = (meta.Address && meta.Address.Components) || [];
        var area = '';
        components.forEach(function (item) { if (item.kind === 'locality' || item.kind === 'province') area = area || item.name; });
        parent.postMessage({type:'kioskvoda-map-suggestion',channel:channel,address:typeof object.getAddressLine==='function'?object.getAddressLine():'',area:area,metro:''},'*');
        ymaps.geocode(coords,{kind:'metro',results:1}).then(function(result){var metro=result.geoObjects.get(0);if(metro)parent.postMessage({type:'kioskvoda-map-suggestion',channel:channel,address:'',area:'',metro:metro.properties.get('name')||''},'*');});
    }
    function setCoordinates(coords) {
        var valid = validCoordinates(coords);
        if (!valid || !map || !marker) return;
        marker.geometry.setCoordinates(valid);
        map.setCenter(valid, 16);
        sendCoordinates(valid);
    }
    function createMap() {
        if (!apiReady || map) return;
        document.getElementById('map').replaceChildren();
        map = new ymaps.Map('map', {center: pendingCoordinates, zoom: 15, controls: ['zoomControl']});
        marker = new ymaps.Placemark(pendingCoordinates, {}, {draggable: true, preset: 'islands#blueWaterIcon'});
        map.geoObjects.add(marker);
        marker.events.add('dragend', function () { setCoordinates(marker.geometry.getCoordinates()); });
        map.events.add('click', function (event) { setCoordinates(event.get('coords')); });
    }
    window.addEventListener('message', function (event) {
        // Принимаем команды только от родительского окна и проверяем случайный channel.
        if (event.source !== parent || !event.data) return;
        var data = event.data;
        if (data.type === 'kioskvoda-map-init') {
            channel = typeof data.channel === 'string' ? data.channel : '';
            pendingCoordinates = validCoordinates(data.coordinates) || pendingCoordinates;
            createMap();
            if (map) setCoordinates(pendingCoordinates);
        } else if (data.type === 'kioskvoda-map-search' && data.channel === channel && apiReady) {
            var value = typeof data.address === 'string' ? data.address.trim().slice(0, 255) : '';
            if (!value) return;
            ymaps.geocode(value, {results: 1}).then(function (result) {
                var first = result.geoObjects.get(0);
                if (first) { var coords=first.geometry.getCoordinates(); setCoordinates(coords); sendAddressSuggestion(first,coords); }
            });
        }
    });
    parent.postMessage({type: 'kioskvoda-map-ready'}, '*');
    if (typeof ymaps === 'undefined') {
        document.getElementById('map').innerHTML = '<div class="message">Карта временно недоступна. Координаты можно заполнить вручную.</div>';
        return;
    }
    ymaps.ready(function () { apiReady = true; createMap(); });
})();
