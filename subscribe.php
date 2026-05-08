<?php
/**
 * subscribe.php
 *
 * Принимает email из формы лид-магнита, валидирует, сохраняет в список
 * подписчиков и отправляет PDF-фрагмент книги в письме (вложением).
 * Возвращает JSON: {success: bool, error?: string}.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Введите корректный email']);
    exit;
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
    echo json_encode(['success' => false, 'error' => 'Слишком много попыток. Попробуйте через несколько минут.']);
    exit;
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
    echo json_encode(['success' => false, 'error' => 'Файл временно недоступен. Напишите на info@podymakhin.ru']);
    exit;
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
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Не удалось отправить письмо. Напишите на info@podymakhin.ru — пришлём вручную.'
    ]);
}
