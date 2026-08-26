(function () {
    'use strict';
    // Изолированный iframe общей карты: создаёт метки один раз и сообщает только новые координаты.
    var channel = '', map = null, pending = [], apiReady = false, initialized = false;
    function valid(point) {
        var lat = Number(point && point[0]), lng = Number(point && point[1]);
        return Number.isFinite(lat) && Number.isFinite(lng) && lat >= 59 && lat <= 61 && lng >= 29 && lng <= 32 ? [lat, lng] : null;
    }
    function initialize() {
        if (!apiReady || initialized || !pending.length) return;
        initialized = true;
        map = new ymaps.Map('map', {center:[59.94,30.32],zoom:9,controls:['zoomControl']});
        pending.forEach(function (item) {
            var coords = valid([item.latitude,item.longitude]); if (!coords) return;
            var marker = new ymaps.Placemark(coords,{hintContent:'№'+(item.machine_number||'')+' '+item.address},{draggable:true,preset:'islands#blueWaterIcon'});
            marker.events.add('dragend',function(){parent.postMessage({type:'bulk-moved',channel:channel,id:String(item.id),coordinates:marker.geometry.getCoordinates()},'*');});
            map.geoObjects.add(marker);
        });
        if (pending.length > 1) map.setBounds(map.geoObjects.getBounds(),{checkZoomRange:true,zoomMargin:35});
    }
    window.addEventListener('message',function(event){
        if(event.source!==parent||!event.data||event.data.type!=='bulk-init')return;
        channel=String(event.data.channel||''); pending=Array.isArray(event.data.points)?event.data.points:[]; initialize();
    });
    parent.postMessage({type:'bulk-ready'},'*');
    if(typeof ymaps!=='undefined')ymaps.ready(function(){apiReady=true;initialize();});
})();
