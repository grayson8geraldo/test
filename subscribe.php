<?php
/**
 * subscribe.php
 *
 * Принимает email из формы лид-магнита, валидирует, сохраняет в список
 * подписчиков и отправляет PDF-фрагмент книги в письме (вложением).
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respondAndExit(false, 'Method Not Allowed');
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondAndExit(false, 'Введите корректный email');
}

// Простой rate-limit по IP — не более 5 подписок с одного IP за 10 минут.
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

// Сохраняем подписчика в CSV для последующих email-цепочек прогрева
$subscribersFile = __DIR__ . '/subscribers.csv';
$entry = sprintf(
    "\"%s\",\"%s\",\"%s\"\n",
    date('Y-m-d H:i:s'),
    str_replace('"', '""', $email),
    str_replace('"', '""', $ip)
);
@file_put_contents($subscribersFile, $entry, FILE_APPEND | LOCK_EX);

// Проверяем наличие PDF
$pdfPath = __DIR__ . '/bes_material.pdf';
if (!file_exists($pdfPath)) {
    respondAndExit(false, 'Файл временно недоступен. Напишите на info@podymakhin.ru');
}

// Готовим письмо с вложением PDF
$boundary = md5(uniqid('', true));
$subject = '=?UTF-8?B?' . base64_encode('Бесплатный фрагмент книги «Каркас над пропастью»') . '?=';
$fromHeader = '=?UTF-8?B?' . base64_encode('Юрий Подымахин') . '?= <info@podymakhin.ru>';

$headers = "From: $fromHeader\r\n"
         . "Reply-To: info@podymakhin.ru\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$plainText = "Здравствуйте!\r\n\r\n"
           . "Спасибо за интерес к книге «Каркас над пропастью: строю дом на болоте».\r\n\r\n"
           . "Во вложении — бесплатный фрагмент книги в формате PDF.\r\n\r\n"
           . "В полной книге 476 страниц практического опыта строительства каркасного дома "
           . "на болотистом грунте: от геологии участка до забора. 1 048 фотографий каждого этапа.\r\n\r\n"
           . "Заказать полное издание: https://podymakhin.ru/#pricing\r\n\r\n"
           . "С уважением,\r\nЮрий Подымахин\r\nhttps://podymakhin.ru\r\n";

$pdfContent = chunk_split(base64_encode((string)file_get_contents($pdfPath)));

$body = "--$boundary\r\n"
      . "Content-Type: text/plain; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: 8bit\r\n\r\n"
      . $plainText . "\r\n"
      . "--$boundary\r\n"
      . "Content-Type: application/pdf; name=\"karkas-fragment.pdf\"\r\n"
      . "Content-Transfer-Encoding: base64\r\n"
      . "Content-Disposition: attachment; filename=\"karkas-fragment.pdf\"\r\n\r\n"
      . $pdfContent . "\r\n"
      . "--$boundary--";

$sent = @mail($email, $subject, $body, $headers);

// Уведомляем администратора о новой подписке
$adminBody = "Новая подписка на PDF-фрагмент:\n\n"
           . "Email: $email\n"
           . "IP: $ip\n"
           . "Дата: " . date('Y-m-d H:i:s') . "\n";
$adminSubject = '=?UTF-8?B?' . base64_encode('Новая подписка на PDF') . '?=';
$adminHeaders = "From: noreply@podymakhin.ru\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n";
@mail('info@podymakhin.ru', $adminSubject, $adminBody, $adminHeaders);

if ($sent) {
    respondAndExit(true);
} else {
    respondAndExit(false, 'Не удалось отправить письмо. Напишите на info@podymakhin.ru — пришлём вручную.');
}
