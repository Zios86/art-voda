(function () {
    'use strict';

    // Расширенные фильтры таблицы работают локально и не отправляют данные на сервер.
    var table = document.getElementById('kiosk-table');
    if (!table) return;
    var search = document.getElementById('table-search');
    var area = document.getElementById('filter-area');
    var photo = document.getElementById('filter-photo');
    var issue = document.getElementById('filter-issue');
    var count = document.getElementById('visible-count');

    function update() {
        var query = search.value.trim().toLocaleLowerCase('ru');
        var visible = 0;
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var matches = (!query || row.textContent.toLocaleLowerCase('ru').indexOf(query) !== -1)
                && (!area.value || row.dataset.area === area.value)
                && (!photo.value || row.dataset.photo === photo.value)
                && (!issue.value || row.dataset.issue === issue.value);
            row.hidden = !matches;
            if (matches) visible += 1;
        });
        count.textContent = visible + ' показано';
    }
    [search, area, photo, issue].forEach(function (control) {
        control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', update);
    });

    // Простой редактор пересобирает выбранное фото в JPEG 4:3 до отправки на сервер.
    var photoInput = document.getElementById('photo');
    var editor = document.getElementById('photo-editor');
    var canvas = document.getElementById('photo-canvas');
    if (photoInput && editor && canvas) {
        var context = canvas.getContext('2d'), image = new Image(), angle = 0;
        var zoom = document.getElementById('photo-zoom');
        function draw() {
            if (!image.naturalWidth) return;
            context.clearRect(0,0,canvas.width,canvas.height);
            var rotated = angle % 180 !== 0;
            var width = rotated ? image.naturalHeight : image.naturalWidth;
            var height = rotated ? image.naturalWidth : image.naturalHeight;
            var scale = Math.max(canvas.width/width,canvas.height/height) * Number(zoom.value);
            context.save(); context.translate(canvas.width/2,canvas.height/2); context.rotate(angle*Math.PI/180);
            context.drawImage(image,-image.naturalWidth*scale/2,-image.naturalHeight*scale/2,image.naturalWidth*scale,image.naturalHeight*scale); context.restore();
        }
        photoInput.addEventListener('change',function(){var file=photoInput.files&&photoInput.files[0];if(!file)return;angle=0;zoom.value='1';image.onload=function(){editor.hidden=false;draw();};image.src=URL.createObjectURL(file);});
        zoom.addEventListener('input',draw);
        editor.querySelector('[data-photo-rotate]').addEventListener('click',function(){angle=(angle+90)%360;draw();});
        editor.querySelector('[data-photo-apply]').addEventListener('click',function(){canvas.toBlob(function(blob){if(!blob)return;var transfer=new DataTransfer();transfer.items.add(new File([blob],'kiosk-edited.jpg',{type:'image/jpeg'}));photoInput.files=transfer.files;var preview=document.getElementById('preview-photo');if(preview){preview.src=URL.createObjectURL(blob);preview.hidden=false;}editor.hidden=true;},'image/jpeg',.9);});
    }

    var form = document.querySelector('.admin-form[data-autosave-key]');
    if (form) {
        var storageKey = form.dataset.autosaveKey;
        var note = document.getElementById('autosave-note');
        var fields = Array.from(form.querySelectorAll('input:not([type=file]):not([type=hidden]),select,textarea'));
        try {
            var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (saved && saved.values && confirm('Найден несохранённый черновик формы. Восстановить его?')) fields.forEach(function(field){if(Object.prototype.hasOwnProperty.call(saved.values,field.name))field.value=saved.values[field.name];});
        } catch (error) {}
        function saveDraft(){var values={};fields.forEach(function(field){if(field.name)values[field.name]=field.value;});try{localStorage.setItem(storageKey,JSON.stringify({savedAt:Date.now(),values:values}));if(note)note.textContent='Черновик сохранён в этом браузере';}catch(error){}}
        form.addEventListener('input',saveDraft);form.addEventListener('submit',function(){try{localStorage.removeItem(storageKey);}catch(error){}});
        document.querySelectorAll('[data-preview-mode]').forEach(function(button){button.addEventListener('click',function(){var card=document.querySelector('[data-preview-card]');document.querySelectorAll('[data-preview-mode]').forEach(function(item){item.classList.toggle('is-active',item===button);});card.classList.remove('preview--mobile','preview--balloon');if(button.dataset.previewMode!=='desktop')card.classList.add('preview--'+button.dataset.previewMode);});});
        document.addEventListener('keydown',function(event){if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='s'){event.preventDefault();form.requestSubmit();}else if(event.key==='/'&&!event.ctrlKey&&!event.metaKey&&!/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)){event.preventDefault();search.focus();}else if(event.key==='Escape'){window.location.href='/admin/';}});
    }
})();
