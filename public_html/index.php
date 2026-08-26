<!DOCTYPE html>
<!-- Главная страница: содержательные секции, публичная карта и быстрые действия PWA. -->
<html lang="ru">
<head>
<title>Киосквода — артезианская вода рядом с домом</title>
<meta name="description" content="Артезианская вода по 10 рублей за литр. Найдите ближайший автомат на карте Санкт-Петербурга и Ленинградской области." />
<meta property="og:title" content="Киосквода — артезианская вода рядом" />
<meta property="og:description" content="Карта точек продаж, цена, документы и понятная инструкция по покупке воды." />
<meta property="og:type" content="website" />
<meta property="og:url" content="https://киоскводы.рф/" />
<link rel="preload" href="/img/hero-water-v3.webp" as="image" type="image/webp" fetchpriority="high" />
<?php require __DIR__ . '/include/metalink.php'; ?>
</head>
<body>
<?php require __DIR__ . '/include/header.php'; ?>
<main id="main-content">

<section class="hero" aria-labelledby="hero-title">
    <div class="hero__glow" aria-hidden="true"></div>
    <div class="container hero__grid">
        <div class="hero__content" data-reveal>
            <p class="eyebrow hero__eyebrow"><span aria-hidden="true"></span>Свежая вода рядом с домом</p>
            <h1 id="hero-title">Артезианская вода <span>рядом с вами</span></h1>
            <p class="hero__lead">Из скважины глубиной 183 метра — в автоматы Санкт-Петербурга и Ленинградской области.</p>
            <div class="hero__actions">
                <a class="button button--primary" href="#marketplace">Найти ближайший автомат</a>
                <a class="button button--glass" href="/documents.php">Посмотреть документы</a>
            </div>
            <ul class="hero__trust" aria-label="Преимущества">
                <li><span aria-hidden="true">✓</span>Понятная цена</li>
                <li><span aria-hidden="true">✓</span>Карта и маршрут</li>
                <li><span aria-hidden="true">✓</span>Документы онлайн</li>
            </ul>
            <dl class="hero__facts" aria-label="Коротко о Киоскводе">
                <div><dt>10 ₽</dt><dd>за один литр</dd></div>
                <div><dt>183 м</dt><dd>глубина скважины</dd></div>
                <div><dt>3 дня</dt><dd>цикл обновления воды</dd></div>
            </dl>
        </div>
        <div class="hero__visual" data-reveal data-reveal-delay="1">
            <div class="hero__ring" aria-hidden="true"></div>
            <img src="/img/kiosk-cutout-v3.webp" width="360" height="826" alt="Автомат Киосквода для продажи артезианской воды" class="hero__kiosk" decoding="async" fetchpriority="high">
            <div class="hero__badge"><span>Работаем</span><strong>рядом с вами</strong></div>
            <div class="hero__orbit hero__orbit--price"><strong>10 ₽</strong><span>за литр</span></div>
            <div class="hero__orbit hero__orbit--depth"><strong>183 м</strong><span>глубина</span></div>
        </div>
    </div>
</section>

<section class="service-ribbon" aria-label="Быстрые возможности сайта">
    <div class="container service-ribbon__grid">
        <a href="#marketplace" data-reveal><span class="service-ribbon__icon" aria-hidden="true">⌖</span><span><strong>Найти автомат</strong><small>По адресу или рядом с вами</small></span><span aria-hidden="true">→</span></a>
        <a href="#water-journey" data-reveal data-reveal-delay="1"><span class="service-ribbon__icon" aria-hidden="true">≈</span><span><strong>Узнать о воде</strong><small>Путь от скважины до бутылки</small></span><span aria-hidden="true">→</span></a>
        <a href="/documents.php" data-reveal data-reveal-delay="2"><span class="service-ribbon__icon" aria-hidden="true">✓</span><span><strong>Проверить документы</strong><small>Оригиналы опубликованных материалов</small></span><span aria-hidden="true">→</span></a>
    </div>
</section>

<section id="facts" class="section section--light">
    <div class="container">
        <div class="section-heading" data-reveal>
            <p class="eyebrow eyebrow--blue">Почему выбирают нас</p>
            <h2>Просто о главном</h2>
            <p>Вода, понятная цена и документы, которые можно открыть прямо на сайте.</p>
        </div>
        <div class="bento-grid">
            <article class="bento-card bento-card--source" data-reveal>
                <div class="feature-card__icon"><img src="/img/Life_Water_icon_1.svg" width="200" height="200" alt="" decoding="async"></div>
                <div><p class="bento-card__label">Источник</p><h3>183 метра до артезианского горизонта</h3><p>Скважина № 51 И расположена на Карельском перешейке.</p></div>
            </article>
            <article class="bento-card bento-card--price" data-reveal data-reveal-delay="1"><p class="bento-card__label">Цена</p><strong>10 ₽</strong><span>за один литр</span></article>
            <article class="bento-card" data-reveal data-reveal-delay="2"><div class="feature-card__icon"><img src="/img/Life_Water_icon_3.svg" width="200" height="200" alt="" loading="lazy" decoding="async"></div><p class="bento-card__label">Открытость</p><h3>Документы доступны онлайн</h3><a href="/documents.php">Посмотреть материалы →</a></article>
            <article class="bento-card bento-card--map" data-reveal><p class="bento-card__label">География</p><strong>136</strong><span>точек в исходном списке</span><a href="#marketplace">Открыть карту →</a></article>
            <article class="bento-card bento-card--fresh" data-reveal data-reveal-delay="1"><div class="feature-card__icon"><img src="/img/Life_Water_icon_2.svg" width="200" height="200" alt="" loading="lazy" decoding="async"></div><div><p class="bento-card__label">Свежесть</p><h3>Обновление воды каждые три дня</h3><p>Короткий цикл доставки от источника до точек продаж.</p></div></article>
        </div>
    </div>
</section>

<section class="section source-section">
    <div class="container source-grid">
        <div class="source-copy" data-reveal>
            <p class="eyebrow">Откуда приходит вода</p>
            <h2>Заповедная территория «Алакюль»</h2>
            <p>Источник расположен на Карельском перешейке. Воду добывают из артезианской скважины № 51 И глубиной 183 метра.</p>
            <div class="source-note">
                <span class="source-note__drop" aria-hidden="true"></span>
                <p><strong>Короткий путь до покупателя.</strong><br>Доставляем воду в точки продаж Санкт-Петербурга и Ленинградской области.</p>
            </div>
        </div>
        <div class="video-card" data-reveal data-reveal-delay="1">
            <div class="external-placeholder external-placeholder--video" data-external-iframe="https://rutube.ru/play/embed/015800813434411c7cb9313e3b8085a5/" data-title="Видео о добыче артезианской воды" data-allow="clipboard-write; autoplay; fullscreen">
                <div class="external-placeholder__inner">
                    <span class="play-button" aria-hidden="true">▶</span>
                    <h3>Посмотрите, где добывают воду</h3>
                    <p>Видео загрузится с Rutube только после нажатия.</p>
                    <button type="button" class="external-placeholder__button" data-load-external>Показать видео</button>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section journey-section" id="water-journey" aria-labelledby="journey-title">
    <div class="container journey-grid">
        <div class="bottle-scene" data-water-bottle data-reveal aria-hidden="true">
            <div class="water-bubble water-bubble--one"></div><div class="water-bubble water-bubble--two"></div>
            <div class="bottle"><div class="bottle__neck"></div><div class="bottle__water"><span></span><span></span><span></span></div><div class="bottle__shine"></div></div>
        </div>
        <div>
            <div class="journey-heading" data-reveal><p class="eyebrow eyebrow--blue">Путь воды</p><h2 id="journey-title">От скважины до вашей бутылки</h2><p>Четыре понятных этапа, которые проходит вода.</p></div>
            <ol class="journey-timeline">
                <li data-reveal><span>01</span><div><h3>Артезианская скважина</h3><p>Подъём воды из скважины № 51 И глубиной 183 метра.</p></div></li>
                <li data-reveal data-reveal-delay="1"><span>02</span><div><h3>Контроль и подготовка</h3><p>Проверка оборудования и подготовка воды к доставке.</p></div></li>
                <li data-reveal data-reveal-delay="2"><span>03</span><div><h3>Доставка в точки</h3><p>Баки автоматов пополняются по внутреннему графику.</p></div></li>
                <li data-reveal data-reveal-delay="3"><span>04</span><div><h3>Покупка рядом с домом</h3><p>Вы выбираете объём и набираете воду в чистую тару.</p></div></li>
            </ol>
        </div>
    </div>
</section>

<section id="marketplace" class="section map-section">
    <div class="container map-section__heading" data-reveal>
        <div>
            <p class="eyebrow eyebrow--blue">Удобный поиск по карте</p>
            <h2>Найдите автомат рядом</h2>
        </div>
        <div class="map-section__intro"><p>Введите улицу или номер автомата. На карте можно построить маршрут.</p><div><span>⌖ Поиск рядом</span><span>☆ Избранное</span><span>→ Маршрут</span></div></div>
    </div>
    <div class="map-shell" data-reveal>
        <div id="map" class="external-placeholder" data-yandex-map-loader="https://api-maps.yandex.ru/2.1/?lang=ru_RU&amp;csp=202512&amp;apikey=53339f0b-4eb7-462c-b883-141779b77ade" data-map-code="/list_box_layout.js?v=<?= $assetVersion ?>">
            <div class="external-placeholder__inner">
                <span class="map-pin" aria-hidden="true"></span>
                <h3>Интерактивная карта точек</h3>
                <p>Яндекс.Карты загрузятся только после нажатия и получат технические данные соединения.</p>
                <button type="button" class="external-placeholder__button" data-load-external>Открыть карту</button>
            </div>
        </div>
    </div>
</section>

<section class="section section--soft" aria-labelledby="how-title">
    <div class="container">
        <div class="section-heading" data-reveal>
            <p class="eyebrow eyebrow--blue">Покупка за пару минут</p>
            <h2 id="how-title">Как набрать воду</h2>
        </div>
        <ol class="steps-grid">
            <li data-reveal><span class="step-number">1</span><h3>Поставьте бутыль</h3><p>Установите чистую тару в обозначенное место.</p></li>
            <li data-reveal data-reveal-delay="1"><span class="step-number">2</span><h3>Внесите оплату</h3><p>Проверьте цену на автомате и внесите необходимую сумму.</p></li>
            <li data-reveal data-reveal-delay="2"><span class="step-number">3</span><h3>Нажмите «Пуск»</h3><p>За одно нажатие автомат выдаёт не более пяти литров.</p></li>
            <li data-reveal data-reveal-delay="3"><span class="step-number">4</span><h3>Завершите покупку</h3><p>Остановите выдачу, заберите бутыль и сдачу из лотка.</p></li>
        </ol>
    </div>
</section>

<section class="section calculator-section" aria-labelledby="calculator-title">
    <div class="container calculator-grid">
        <div class="calculator-copy" data-reveal><p class="eyebrow eyebrow--blue">Посчитайте сами</p><h2 id="calculator-title">Сколько можно сэкономить</h2><p>Укажите своё потребление и цену воды, с которой хотите сравнить. Расчёт примерный и не учитывает стоимость тары или доставки.</p><div class="volume-picker" aria-label="Стоимость выбранного объёма"><span>Быстрый выбор объёма:</span><div><button type="button" data-volume="1.5">1,5 л</button><button type="button" data-volume="5">5 л</button><button type="button" data-volume="10">10 л</button><button type="button" data-volume="19">19 л</button></div><p><strong id="volume-price">Объём 5 л = 50 ₽</strong></p></div></div>
        <form class="savings-card" id="savings-calculator" data-reveal data-reveal-delay="1">
            <label>Сколько литров в день<input id="daily-litres" type="number" min="0.5" max="100" step="0.5" value="5" inputmode="decimal"></label>
            <label>Цена воды для сравнения, ₽/л<input id="comparison-price" type="number" min="10" max="500" step="1" value="40" inputmode="decimal"></label>
            <div class="savings-result"><p>Киосквода за месяц<strong id="kiosk-monthly">1 500 ₽</strong></p><p>Выбранный вариант<strong id="other-monthly">6 000 ₽</strong></p><p class="savings-result__accent">Возможная экономия<strong id="monthly-saving">4 500 ₽/мес.</strong></p></div>
        </form>
    </div>
</section>

<section class="section kiosk-gallery-section" aria-labelledby="gallery-title">
    <div class="container">
        <div class="section-heading" data-reveal><p class="eyebrow eyebrow--blue">Наше оборудование</p><h2 id="gallery-title">Реальные модели автоматов</h2><p>Внешний вид конкретной точки может отличаться — на сайте показаны модели из исходных материалов компании.</p></div>
        <div class="kiosk-gallery">
            <figure data-reveal><div><img src="/img/kiosk-model-1.webp" width="420" height="629" alt="Модель автомата Киосквода с большим верхним баком" loading="lazy" decoding="async"></div><figcaption>Модель с оформлением «Природная артезианская вода»</figcaption></figure>
            <figure data-reveal data-reveal-delay="1"><div><img src="/img/kiosk-model-2.webp" width="418" height="630" alt="Компактная модель автомата Киосквода" loading="lazy" decoding="async"></div><figcaption>Компактная модель точки продажи</figcaption></figure>
            <figure data-reveal data-reveal-delay="2"><div><img src="/img/kiosk-model-3.webp" width="418" height="630" alt="Модель автомата Киосквода с местом для бутылей" loading="lazy" decoding="async"></div><figcaption>Модель с витриной тары</figcaption></figure>
        </div>
    </div>
</section>

<section id="cost" class="price-section">
    <div class="container price-card" data-reveal>
        <div><p class="eyebrow">Понятная стоимость</p><h2>10 рублей за литр</h2><p>Набирайте столько, сколько нужно вашей семье.</p></div>
        <div class="price-card__actions"><a class="button button--white" href="#marketplace">Найти автомат</a><a class="price-phone" href="tel:+78124099033">+7 (812) 409-90-33</a></div>
    </div>
</section>

<section class="section documents-section">
    <div class="container">
        <div class="section-heading" data-reveal><p class="eyebrow eyebrow--blue">Контроль и открытость</p><h2>Документы о воде</h2><p>Откройте оригиналы опубликованных материалов в полном размере.</p></div>
        <div class="documents-grid">
            <a class="document-card" href="/pdf/Sertifikat-1.jpg" target="_blank" rel="noopener noreferrer" data-reveal><span class="document-card__image"><img src="/img/document-1-640.webp" width="640" height="905" alt="Первая опубликованная страница протокола № 32612" loading="lazy" decoding="async"></span><span class="document-card__body"><strong>Протокол № 32612</strong><small>Страница 1 из 3</small><span>Открыть оригинал →</span></span></a>
            <a class="document-card" href="/pdf/Sertifikat-2.jpg" target="_blank" rel="noopener noreferrer" data-reveal data-reveal-delay="1"><span class="document-card__image"><img src="/img/document-2-640.webp" width="640" height="905" alt="Вторая опубликованная страница протокола № 32612" loading="lazy" decoding="async"></span><span class="document-card__body"><strong>Протокол № 32612</strong><small>Страница 2 из 3</small><span>Открыть оригинал →</span></span></a>
            <a class="document-card" href="/pdf/Declaration_13.05.2025_13-30.pdf" target="_blank" rel="noopener noreferrer" data-reveal data-reveal-delay="2"><span class="document-card__image"><img src="/img/declaration-640.webp" width="640" height="912" alt="Декларация о соответствии" loading="lazy" decoding="async"></span><span class="document-card__body"><strong>Декларация</strong><small>Опубликованный PDF</small><span>Открыть документ →</span></span></a>
        </div>
        <div class="document-note" data-reveal><p>На сайте опубликованы две из трёх страниц протокола. Мы не называем комплект полным до получения третьей страницы.</p><a class="button button--outline" href="/documents.php">Подробнее о документах</a></div>
    </div>
</section>

</main>
<nav class="mobile-quickbar" aria-label="Быстрые действия">
    <a href="#marketplace"><span aria-hidden="true">⌖</span>Карта</a>
    <button type="button" data-nearest-kiosk><span aria-hidden="true">◎</span>Рядом</button>
    <a href="tel:+78124099033"><span aria-hidden="true">☎</span>Позвонить</a>
    <button type="button" data-install-app hidden><span aria-hidden="true">⇩</span>Установить</button>
</nav>
<dialog id="install-help" class="install-dialog"><form method="dialog"><button class="install-dialog__close" aria-label="Закрыть">×</button><h2>Добавить сайт на экран iPhone</h2><ol><li>Нажмите кнопку «Поделиться» внизу Safari.</li><li>Выберите «На экран Домой».</li><li>Нажмите «Добавить».</li></ol><button class="button button--primary">Понятно</button></form></dialog>
<?php require __DIR__ . '/include/footer.php'; ?>
<?php require __DIR__ . '/include/script.php'; ?>
</body>
</html>
