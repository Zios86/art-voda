<?php
declare(strict_types=1);

/** Обработчик формы: антибот, rate limit, CSRF, согласие, валидация и письмо. */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/request_security.php';

function redirectToContact(string $status): void
{
    header('Cache-Control: no-store');
    header('Location: /contact.php?status=' . rawurlencode($status), true, 303);
    exit;
}

function cleanLine(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function cleanText(string $value, int $maxLength): string
{
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

startSecureSession();

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirectToContact('success');
}

$clientAddress = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
try {
    if (app_rate_limit('contact', $clientAddress, 5, 600)) redirectToContact('rate');
} catch (Throwable $error) {
    error_log('kioskvoda_contact security_storage_unavailable');
    redirectToContact('error');
}

$sentToken = (string) ($_POST['csrf_token'] ?? '');
$sessionToken = (string) ($_SESSION['contact_csrf'] ?? '');
if ($sentToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $sentToken)) {
    redirectToContact('invalid');
}

if (($_POST['personal_data_consent'] ?? '') !== 'yes') {
    redirectToContact('invalid');
}

$name = cleanLine((string) ($_POST['name'] ?? ''), 120);
$phone = cleanLine((string) ($_POST['phone'] ?? ''), 40);
$emailRaw = cleanLine((string) ($_POST['email'] ?? ''), 160);
$kiosk = cleanLine((string) ($_POST['kiosk'] ?? ''), 220);
$question = cleanText((string) ($_POST['question'] ?? ''), 4000);

$email = $emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
$phoneValid = $phone !== '' && preg_match('/^[0-9+()\-\s]{5,40}$/u', $phone) === 1;

if ($question === '' || (!$phoneValid && $email === '')) {
    redirectToContact('invalid');
}

$requestId = bin2hex(random_bytes(8));
$subject = 'Киосквода: обращение с сайта';
$message = "Получено обращение через форму киоскводы.рф\n\n"
    . 'Номер обращения: ' . $requestId . "\n"
    . 'Имя: ' . ($name !== '' ? $name : 'не указано') . "\n"
    . 'Телефон: ' . ($phoneValid ? $phone : 'не указан') . "\n"
    . 'E-mail: ' . ($email !== '' ? $email : 'не указан') . "\n"
    . 'Киоск: ' . ($kiosk !== '' ? $kiosk : 'не указан') . "\n\n"
    . "Сообщение:\n" . $question . "\n\n"
    . "Согласие на обработку данных: получено через форму\n"
    . 'Дата сервера: ' . date('c') . "\n";

$mailConfig = app_config()['mail'] ?? [];
$configuredRecipient = trim((string) (getenv('CONTACT_RECIPIENT') ?: ($mailConfig['recipient'] ?? '')));
$recipient = filter_var($configuredRecipient, FILTER_VALIDATE_EMAIL) ? $configuredRecipient : 'info@lifewater24.ru';
$configuredSender = trim((string) (getenv('CONTACT_FROM') ?: ($mailConfig['from'] ?? '')));
$sender = filter_var($configuredSender, FILTER_VALIDATE_EMAIL) ? $configuredSender : 'info@lifewater24.ru';
$senderName = function_exists('mb_encode_mimeheader')
    ? mb_encode_mimeheader('Киосквода', 'UTF-8', 'B', "\r\n")
    : 'Kioskvoda';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $senderName . ' <' . $sender . '>',
    'Reply-To: ' . ($email !== '' ? $email : $sender),
    'X-Request-ID: ' . $requestId,
    'X-Content-Type-Options: nosniff',
];

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$sent = mail($recipient, $encodedSubject, $message, implode("\r\n", $headers));

if (!$sent) {
    error_log('kioskvoda_contact mail_transport_rejected request_id=' . $requestId);
}

unset($_SESSION['contact_csrf']);
redirectToContact($sent ? 'success' : 'error');
