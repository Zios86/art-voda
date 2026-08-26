<?php
/** Контакты и форма обращения: создаёт CSRF, но отправку выполняет include/send.php. */
require_once __DIR__ . '/include/security.php';
startSecureSession();

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$allowedStatuses = ['success', 'invalid', 'error', 'rate'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<title>Контакты и обращение — Киосквода</title>
<meta name="description" content="Контакты продавца, форма обращения по работе киоска и ссылки на документы." />
<meta property="og:title" content="Контакты — Киосквода" />
<meta property="og:description" content="Сообщить о проблеме с автоматом или задать вопрос продавцу." />
<meta property="og:type" content="website" />
<meta property="og:url" content="https://киоскводы.рф/contact.php" />
<?php require __DIR__ . '/include/metalink.php'; ?>
</head>
<body>
<?php require __DIR__ . '/include/header.php'; ?>

<main id="main-content" class="container block-padding-bottom block-padding-top">
    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-5 col-lg-5">
            <h1>Контакты</h1>
            <h2>Продавец</h2>
            <p>
                Индивидуальный предприниматель Иванов Дмитрий Валерьевич<br>
                ИНН 782576604244<br>
                ОГРНИП 316784700237222
            </p>
            <h3>Адрес</h3>
            <p>193230, Санкт-Петербург,<br>переулок Челиева, 13, офис 306</p>
            <h3>Связь</h3>
            <p>
                Телефон: <a href="tel:+78124099033">+7 (812) 409-90-33</a><br>
                E-mail: <a href="mailto:info@lifewater24.ru">info@lifewater24.ru</a><br>
                Пн–Пт: 10:00–19:00
            </p>
            <p><a href="/documents.php">Документы о воде</a><br><a href="/offer.php">Публичная оферта</a><br><a href="/privacy.php">Политика обработки данных</a></p>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-7 col-lg-7">
            <h2>Написать обращение</h2>
            <p>Если автомат принял деньги, но не выдал воду, укажите адрес или номер киоска, дату, время и сумму.</p>

            <?php if ($status === 'success'): ?>
                <div class="form-status form-status--success" role="status">Обращение принято системой. Мы ответим по указанному контакту.</div>
            <?php elseif ($status === 'invalid'): ?>
                <div class="form-status form-status--error" role="alert">Проверьте обязательные поля, контакт для ответа и согласие.</div>
            <?php elseif ($status === 'rate'): ?>
                <div class="form-status form-status--error" role="alert">С этого адреса поступило слишком много сообщений. Подождите до 10 минут или позвоните нам.</div>
            <?php elseif ($status === 'error'): ?>
                <div class="form-status form-status--error" role="alert">Не удалось отправить сообщение. Позвоните нам или напишите на e-mail.</div>
            <?php endif; ?>

            <form class="contact-form" action="/include/send.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['contact_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <label class="contact-form__honeypot" aria-hidden="true">
                    Сайт
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>

                <label>
                    Имя
                    <input type="text" name="name" maxlength="120" autocomplete="name" placeholder="Как к вам обращаться">
                </label>

                <label>
                    Телефон
                    <input type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="+7 ...">
                </label>

                <label>
                    E-mail
                    <input type="email" name="email" maxlength="160" autocomplete="email" placeholder="mail@example.ru">
                </label>

                <label>
                    Номер или адрес киоска
                    <input type="text" name="kiosk" maxlength="220" placeholder="Например: Киоск SPB-014 или адрес">
                </label>

                <label>
                    Сообщение <span aria-hidden="true">*</span>
                    <textarea name="question" maxlength="4000" required placeholder="Опишите, что произошло"></textarea>
                </label>

                <label class="consent-checkbox">
                    <input type="checkbox" name="personal_data_consent" value="yes" required>
                    <span>Я даю <a href="/consent.php" target="_blank" rel="noopener noreferrer">согласие на обработку персональных данных</a> для ответа на обращение. Галочка не установлена заранее.</span>
                </label>

                <p><small>Укажите хотя бы один контакт для ответа: телефон или e-mail.</small></p>
                <button type="submit">Отправить обращение</button>
            </form>
        </div>
    </div>

    <div class="row block-padding-top">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <h2>Офис на карте</h2>
            <div class="external-placeholder" data-yandex-constructor="https://api-maps.yandex.ru/services/constructor/1.0/js/?sid=whaQQ_hZcftSn-5lxZT8K2bwJuVUKcVJ&amp;width=100%25&amp;height=400&amp;lang=ru_RU&amp;sourceType=constructor&amp;scroll=true">
                <div class="external-placeholder__inner">
                    <p>Яндекс.Карты загрузятся только после нажатия и получат технические данные соединения.</p>
                    <button type="button" class="external-placeholder__button" data-load-external>Показать карту офиса</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/include/footer.php'; ?>
<?php require __DIR__ . '/include/script.php'; ?>
</body>
</html>
