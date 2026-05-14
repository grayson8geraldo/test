<?php
/**
 * subscribe.php
 *
 * Принимает email из формы лид-магнита, валидирует, добавляет подписчика
 * в список Unisender и отправляет письмо со ссылкой на PDF-фрагмент книги.
 * AJAX-запрос (X-Requested-With) → JSON, обычный POST → HTML-страница.
 */

declare(strict_types=1);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

function respondAndExit(bool $success, string $error = ''): void {
    global $isAjax;

    if ($isAjax) {
        echo $success
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    $title   = $success ? 'Материалы отправлены!' : 'Ошибка';
    $icon    = $success ? '&#10003;' : '&#9888;';
    $iconBg  = $success ? '#e8f5e9' : '#fff5e6';
    $iconClr = $success ? '#27ae60' : '#d4a843';
    $heading = $success
        ? 'Спасибо за интерес к книге!'
        : 'Не удалось отправить';
    $text    = $success
        ? 'Бесплатный фрагмент книги &laquo;Каркас над пропастью&raquo; отправлен на&nbsp;ваш email. Проверьте папку &laquo;Входящие&raquo; или &laquo;Спам&raquo; в&nbsp;течение пары минут.'
        : htmlspecialchars($error, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} — Podymakhin.ru</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#fafaf7;color:#2d3436;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{max-width:480px;width:100%;background:#fff;border-radius:16px;padding:40px 32px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.icon{width:64px;height:64px;margin:0 auto 20px;border-radius:50%;background:{$iconBg};display:flex;align-items:center;justify-content:center;font-size:28px;color:{$iconClr}}
h1{font-size:22px;color:#1a3c28;margin-bottom:12px}
p{font-size:15px;line-height:1.6;color:#555;margin-bottom:16px}
.btn{display:inline-block;padding:12px 28px;background:#1a3c28;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;margin-top:8px;transition:opacity .2s}
.btn:hover{opacity:.9}
.note{font-size:13px;color:#888;margin-top:20px;line-height:1.5}
</style></head><body>
<div class="card">
  <div class="icon">{$icon}</div>
  <h1>{$heading}</h1>
  <p>{$text}</p>
  <a href="/" class="btn">Вернуться на сайт</a>
  <a href="/#pricing" class="btn" style="background:#d4a843;color:#1a3c28;">Заказать полное издание</a>
  <p class="note">В полной книге 476 страниц, 1&nbsp;048 фотографий и 135 рисунков &mdash; от&nbsp;геологии участка до&nbsp;забора.</p>
</div></body></html>
HTML;
    exit;
}

// --- Валидация ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respondAndExit(false, 'Method Not Allowed');
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondAndExit(false, 'Введите корректный email');
}

// Rate-limit по IP — не более 5 подписок с одного IP за 10 минут.
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$rateFile = __DIR__ . '/subscribe-rate.log';
$now = time();
$window = 600;
$maxPerWindow = 5;
$attempts = [];
if (file_exists($rateFile)) {
    $lines = @file($rateFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $parts = explode(' ', $line, 2);
        if (count($parts) === 2 && (int)$parts[0] >= $now - $window) {
            $attempts[] = $parts;
        }
    }
}
$ipAttempts = array_filter($attempts, fn($p) => $p[1] === $ip);
if (count($ipAttempts) >= $maxPerWindow) {
    respondAndExit(false, 'Слишком много попыток. Попробуйте через несколько минут.');
}
$attempts[] = [$now, $ip];
$rateContent = '';
foreach ($attempts as $a) {
    $rateContent .= $a[0] . ' ' . $a[1] . "\n";
}
@file_put_contents($rateFile, $rateContent, LOCK_EX);

// Сохраняем подписчика в CSV (бэкап, независимо от Unisender)
$subscribersFile = __DIR__ . '/subscribers.csv';
$entry = sprintf(
    "\"%s\",\"%s\",\"%s\"\n",
    date('Y-m-d H:i:s'),
    str_replace('"', '""', $email),
    str_replace('"', '""', $ip)
);
@file_put_contents($subscribersFile, $entry, FILE_APPEND | LOCK_EX);

// --- Unisender API ---

$uniConfigPath = __DIR__ . '/unisender-config.php';
if (!file_exists($uniConfigPath)) {
    respondAndExit(false, 'Сервис рассылки временно недоступен. Напишите на info@podymakhin.ru');
}
$uniConfig = require $uniConfigPath;

function unisenderApi(string $method, array $params): ?array {
    $url = 'https://api.unisender.com/ru/api/' . $method . '?format=json';
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }
    return json_decode($response, true) ?: null;
}

// 1. Добавляем контакт в список Unisender
$subscribeResult = unisenderApi('subscribe', [
    'api_key'       => $uniConfig['api_key'],
    'list_ids'      => $uniConfig['list_id'],
    'fields[email]' => $email,
    'double_optin'  => 3,
    'overwrite'     => 2,
]);

if (!$subscribeResult || isset($subscribeResult['error'])) {
    $errMsg = $subscribeResult['error'] ?? 'Unisender API недоступен';
    @file_put_contents(
        __DIR__ . '/subscribe-errors.log',
        sprintf("[%s] subscribe error: %s | email=%s\n", date('Y-m-d H:i:s'), $errMsg, $email),
        FILE_APPEND | LOCK_EX
    );
    respondAndExit(false, 'Не удалось оформить подписку. Напишите на info@podymakhin.ru — пришлём вручную.');
}

// 2. Отправляем письмо со ссылкой на PDF через Unisender sendEmail
$templatePath = __DIR__ . '/email-templates/welcome-pdf-followup.html';
$htmlBody = @file_get_contents($templatePath);

if (!$htmlBody) {
    respondAndExit(false, 'Шаблон письма не найден. Напишите на info@podymakhin.ru');
}

$sendResult = unisenderApi('sendEmail', [
    'api_key'      => $uniConfig['api_key'],
    'email'        => $email,
    'sender_name'  => $uniConfig['sender_name'],
    'sender_email' => $uniConfig['sender_email'],
    'subject'      => 'Ваш фрагмент книги «Каркас над пропастью»',
    'body'         => $htmlBody,
    'list_id'      => $uniConfig['list_id'],
]);

if (!$sendResult || isset($sendResult['error'])) {
    $errMsg = $sendResult['error'] ?? 'sendEmail failed';
    @file_put_contents(
        __DIR__ . '/subscribe-errors.log',
        sprintf("[%s] sendEmail error: %s | email=%s\n", date('Y-m-d H:i:s'), $errMsg, $email),
        FILE_APPEND | LOCK_EX
    );
    respondAndExit(false, 'Подписка оформлена, но письмо не отправилось. Напишите на info@podymakhin.ru — пришлём вручную.');
}

// 3. Уведомляем администратора (через обычный mail — это серверное уведомление)
$adminBody = "Новая подписка на PDF-фрагмент (Unisender):\n\n"
           . "Email: $email\n"
           . "IP: $ip\n"
           . "Дата: " . date('Y-m-d H:i:s') . "\n";
$adminSubject = '=?UTF-8?B?' . base64_encode('Новая подписка на PDF') . '?=';
$adminHeaders = "From: noreply@podymakhin.ru\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n";
@mail('info@podymakhin.ru', $adminSubject, $adminBody, $adminHeaders);

respondAndExit(true);
