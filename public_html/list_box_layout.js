(function () {
    'use strict';

    // Публичная карта: получает автоматы из API, строит метки, фильтры, избранное и маршруты.
    var container = document.getElementById('map');
    if (!container || typeof ymaps === 'undefined') return;

    function escapeHtml(value) {
        // Любые строки из БД экранируются до вставки в balloonContent или карточку.
        return String(value || '').replace(/[&<>'"]/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char];
        });
    }
    function routeUrl(item) {
        return 'https://yandex.ru/maps/?rtext=~' + encodeURIComponent(item.latitude + ',' + item.longitude) + '&rtt=auto';
    }
    function distanceKm(a, b) {
        var rad = Math.PI / 180, dLat = (b[0] - a[0]) * rad, dLon = (b[1] - a[1]) * rad;
        var x = Math.sin(dLat / 2) ** 2 + Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin(dLon / 2) ** 2;
        return 6371 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
    }
    function distanceLabel(value) {
        return value < 1 ? Math.round(value * 1000) + ' м' : value.toFixed(1).replace('.', ',') + ' км';
    }
    var favoriteKey = 'kioskvoda-favorite-kiosks-v1';
    function favoriteIds() {
        try { var value=JSON.parse(localStorage.getItem(favoriteKey)||'[]'); return Array.isArray(value)?value.map(String):[]; } catch(error) { return []; }
    }
    function isFavorite(id) { return favoriteIds().indexOf(String(id)) !== -1; }
    function toggleFavorite(id) {
        var values=favoriteIds(), key=String(id), index=values.indexOf(key);
        if(index===-1) values.push(key); else values.splice(index,1);
        try { localStorage.setItem(favoriteKey,JSON.stringify(values)); } catch(error) {}
    }

    function render(data) {
        // Вся разметка карты создаётся только после успешной загрузки Яндекс API и данных.
        var kiosks = Array.isArray(data.kiosks) ? data.kiosks.map(function (item) {
            item.latitude = Number(item.latitude); item.longitude = Number(item.longitude); return item;
        }) : [];
        var userPoint = null;
        container.className = 'sales-map';
        container.innerHTML =
            '<div class="map-appbar"><div><span class="map-appbar__mark" aria-hidden="true">⌖</span><span><strong>Карта Киоскводы</strong><small>Выберите удобную точку</small></span></div><span class="map-appbar__live"><i aria-hidden="true"></i>Данные актуальны</span></div>' +
            '<div class="map-toolbar">' +
                '<label>Адрес, улица или номер<div class="map-search-field"><span aria-hidden="true">⌕</span><input type="search" id="map-search" placeholder="Например: улица Дыбенко" autocomplete="street-address"></div></label>' +
                '<label>Город или район<select id="map-area"><option value="">Все места</option></select></label>' +
                '<div class="map-toolbar__buttons"><button type="button" id="map-address-search" class="map-action map-action--secondary">Найти</button><button type="button" id="map-favorites" class="map-action map-action--secondary" aria-pressed="false">☆ Избранное</button><button type="button" id="map-nearest" class="map-action"><span aria-hidden="true">◎</span> Рядом со мной</button></div>' +
                '<span id="map-count" class="map-count" aria-live="polite"></span>' +
            '</div>' +
            '<div id="map-message" class="map-message" role="status" aria-live="polite" hidden></div>' +
            '<div class="map-explorer"><div id="map-canvas" class="map-canvas" aria-label="Карта автоматов с водой"></div><aside class="kiosk-panel" aria-label="Найденные автоматы"><div class="kiosk-panel__heading"><strong>Ближайшие точки</strong><small>Нажмите на карточку</small></div><div id="kiosk-list" class="kiosk-list"></div></aside></div>';

        var map = new ymaps.Map('map-canvas', {center:[59.94,30.32],zoom:9,controls:['zoomControl']}, {restrictMapArea:[[60.35,29.45],[59.45,30.95]]});
        var manager = new ymaps.ObjectManager({clusterize:true,gridSize:64,clusterDisableClickZoom:false});
        manager.objects.options.set({iconLayout:'default#image',iconImageSize:[38,46],iconImageOffset:[-19,-46]});
        manager.clusters.options.set({preset:'islands#blueClusterIcons'});
        map.geoObjects.add(manager);
        var userMarker = null, favoritesOnly = false;
        var search = document.getElementById('map-search'), area = document.getElementById('map-area'), count = document.getElementById('map-count'), list = document.getElementById('kiosk-list'), message = document.getElementById('map-message');
        Array.from(new Set(kiosks.map(function(item){return item.area;}).filter(Boolean))).sort().forEach(function(name){var option=document.createElement('option');option.value=name;option.textContent=name;area.appendChild(option);});

        function showMessage(text, isError) {
            message.hidden = !text; message.textContent = text || ''; message.classList.toggle('map-message--error', Boolean(isError));
        }
        function setUserPoint(coords, label) {
            userPoint = coords;
            if (userMarker) map.geoObjects.remove(userMarker);
            userMarker = new ymaps.Placemark(coords, {hintContent:label || 'Ваше местоположение'}, {preset:'islands#redCircleDotIcon'});
            map.geoObjects.add(userMarker);
        }
        function feature(item) {
            var number = item.machine_number ? 'Автомат №' + item.machine_number : 'Точка продажи';
            return {type:'Feature',id:item.id,geometry:{type:'Point',coordinates:[item.latitude,item.longitude]},properties:{hintContent:escapeHtml(number+': '+item.address),balloonContent:(item.photo_url?'<img class="map-balloon-photo" src="'+escapeHtml(item.photo_url)+'" alt="">':'')+'<strong>'+escapeHtml(number)+'</strong><br>'+escapeHtml(item.address)+(item.landmark?'<br>'+escapeHtml(item.landmark):'')+(item.metro?'<br>Метро: '+escapeHtml(item.metro):'')+(item.schedule?'<br>'+escapeHtml(item.schedule):'')+'<br><a href="'+routeUrl(item)+'" target="_blank" rel="noopener noreferrer">Построить маршрут</a>'},options:{iconImageHref:'/img/map-pin-active.svg'}};
        }
        function renderCards(items) {
            list.innerHTML = '';
            if (!items.length) { list.innerHTML = '<p class="kiosk-empty">Ничего не найдено. Попробуйте изменить адрес или район.</p>'; return; }
            items.slice(0, 8).forEach(function (item) {
                var card = document.createElement('article');
                card.className = 'kiosk-card'; card.tabIndex = 0;
                var number = item.machine_number ? 'Автомат №' + item.machine_number : 'Точка продажи';
                var favorite=isFavorite(item.id);
                card.innerHTML = (item.photo_url?'<img class="kiosk-card__photo" src="'+escapeHtml(item.photo_url)+'" alt="" loading="lazy">':'')+'<div class="kiosk-card__top"><strong>'+escapeHtml(number)+'</strong><button class="favorite-button" type="button" aria-pressed="'+favorite+'" aria-label="'+(favorite?'Убрать из избранного':'Добавить в избранное')+'">'+(favorite?'★':'☆')+'</button></div><p>'+escapeHtml(item.address)+'</p>'+(item.landmark?'<small>'+escapeHtml(item.landmark)+'</small>':'')+'<div class="kiosk-card__meta">'+(item.metro?'<span>Ⓜ '+escapeHtml(item.metro)+'</span>':'')+'</div><div class="kiosk-card__bottom">'+(typeof item.distance==='number'?'<strong>≈ '+distanceLabel(item.distance)+'</strong>':'<span>'+escapeHtml(item.area||'')+'</span>')+'<a class="kiosk-card__route" href="'+routeUrl(item)+'" target="_blank" rel="noopener noreferrer">Маршрут <span aria-hidden="true">→</span></a></div>';
                card.querySelector('.favorite-button').addEventListener('click',function(event){event.stopPropagation();toggleFavorite(item.id);update({fit:false});});
                function focusPoint(event) { if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return; if (event.target.closest('a')) return; event.preventDefault(); map.setCenter([item.latitude,item.longitude],16,{duration:350}); manager.objects.balloon.open(item.id); }
                card.addEventListener('click',focusPoint); card.addEventListener('keydown',focusPoint); list.appendChild(card);
            });
        }
        function update(options) {
            // Единая функция повторно применяет поиск, район, избранное, расстояния и сортировку.
            options = options || {};
            var query = search.value.trim().toLocaleLowerCase('ru'), selected = area.value;
            var visible = kiosks.filter(function(item){var text=[item.address,item.area,item.metro,item.landmark,item.machine_number||''].join(' ').toLocaleLowerCase('ru');return(!query||text.indexOf(query)!==-1)&&(!selected||item.area===selected)&&(!favoritesOnly||isFavorite(item.id));});
            var reference = options.reference || userPoint;
            if (reference) visible.forEach(function(item){item.distance=distanceKm(reference,[item.latitude,item.longitude]);});
            else visible.forEach(function(item){delete item.distance;});
            visible.sort(function(a,b){return reference?(a.distance-b.distance):((a.machine_number||99999)-(b.machine_number||99999));});
            manager.removeAll(); manager.add({type:'FeatureCollection',features:visible.map(feature)}); renderCards(visible);
            count.textContent='Найдено: '+visible.length;
            if (options.fit !== false) {
                if (visible.length===1) map.setCenter([visible[0].latitude,visible[0].longitude],16);
                else if (reference && visible.length) map.setCenter(reference,13);
                else if (visible.length>1) map.setBounds(manager.getBounds(),{checkZoomRange:true,zoomMargin:40});
            }
        }
        function findNearest() {
            showMessage('Определяем ваше местоположение…');
            if (!navigator.geolocation) { showMessage('Браузер не поддерживает определение местоположения.',true); return; }
            navigator.geolocation.getCurrentPosition(function(position){var point=[position.coords.latitude,position.coords.longitude];setUserPoint(point,'Вы находитесь здесь');search.value='';area.value='';showMessage('Показываем ближайшие автоматы. Расстояние указано по прямой.');update({reference:point});},function(){showMessage('Не удалось определить местоположение. Разрешите доступ или введите адрес.',true);},{enableHighAccuracy:false,timeout:10000,maximumAge:300000});
        }
        function findAddress() {
            var value = search.value.trim(); if (!value) { showMessage('Сначала введите улицу и дом.',true); search.focus(); return; }
            showMessage('Ищем адрес…');
            ymaps.geocode(value.indexOf('Санкт')===-1?'Санкт-Петербург, '+value:value,{results:1}).then(function(result){var first=result.geoObjects.get(0);if(!first){showMessage('Адрес не найден. Уточните улицу и номер дома.',true);return;}var point=first.geometry.getCoordinates();setUserPoint(point,'Искомый адрес');search.value='';area.value='';showMessage('Показываем ближайшие точки к найденному адресу. Расстояние указано по прямой.');update({reference:point});},function(){showMessage('Сервис поиска адреса временно недоступен.',true);});
        }
        search.addEventListener('input',function(){userPoint=null;if(userMarker){map.geoObjects.remove(userMarker);userMarker=null;}update({fit:false});}); area.addEventListener('change',function(){update();});
        document.getElementById('map-nearest').addEventListener('click',findNearest); document.getElementById('map-address-search').addEventListener('click',findAddress);
        document.getElementById('map-favorites').addEventListener('click',function(){favoritesOnly=!favoritesOnly;this.setAttribute('aria-pressed',String(favoritesOnly));this.textContent=favoritesOnly?'★ Все избранные':'☆ Избранное';update();});
        search.addEventListener('keydown',function(event){if(event.key==='Enter'){event.preventDefault();findAddress();}});
        update();
        window.kioskvodaMap = {findNearest:findNearest,findAddress:findAddress};
        document.dispatchEvent(new CustomEvent('kioskvoda-map-ready'));
    }

    ymaps.ready(function(){fetch('/api/kiosks.php',{headers:{Accept:'application/json'}}).then(function(response){if(!response.ok)throw new Error('HTTP '+response.status);return response.json();}).then(render).catch(function(){container.innerHTML='<div class="external-placeholder__inner"><p>Не удалось загрузить список автоматов. Попробуйте позже.</p></div>';});});
})();
