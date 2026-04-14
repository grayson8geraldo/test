<?php
/**
 * result.php
 *
 * Принимает уведомление от Робокассы об успешной оплате.
 * Проверяет подпись (с Паролем #2), сохраняет заказ и шлёт email.
 *
 * В ответ ДОЛЖЕН вернуть "OK<InvId>", иначе Робокасса будет
 * повторять попытки уведомления.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// Загружаем конфиг
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Server configuration error');
}
$config = require $configPath;

// Робокасса шлёт POST, но иногда для Success URL может быть GET
$data = $_POST ?: $_GET;

$outSum         = $data['OutSum']         ?? '';
$invId          = $data['InvId']          ?? '';
$signatureValue = $data['SignatureValue'] ?? '';

if ($outSum === '' || $invId === '' || $signatureValue === '') {
    http_response_code(400);
    exit('Bad request');
}

// Выбираем Пароль #2 по режиму
$isTest    = !empty($config['is_test']);
$password2 = $isTest ? $config['test_password2'] : $config['password2'];

// Собираем Shp-параметры обратно (они пришли в запросе)
$shpParams = [];
foreach ($data as $key => $value) {
    if (strpos($key, 'Shp_') === 0) {
        $shpParams[$key] = $value;
    }
}
ksort($shpParams);

// Формируем строку для подписи:
// OutSum:InvId:Password#2:Shp_key1=value1:Shp_key2=value2...
$signatureParts = [$outSum, (string)$invId, $password2];
foreach ($shpParams as $k => $v) {
    $signatureParts[] = $k . '=' . $v;
}
$signatureString = implode(':', $signatureParts);
$algo = $config['hash_algo'] ?? 'md5';
$calculatedSignature = hash($algo, $signatureString);

// Проверяем подпись (регистронезависимо)
if (strcasecmp($calculatedSignature, $signatureValue) !== 0) {
    http_response_code(400);
    @file_put_contents(
        __DIR__ . '/result-errors.log',
        sprintf("[%s] Bad signature InvId=%s\n", date('Y-m-d H:i:s'), $invId),
        FILE_APPEND | LOCK_EX
    );
    exit('Bad signature');
}

// Подпись верна — обрабатываем заказ
$email   = $shpParams['Shp_email']        ?? '';
$phone   = $shpParams['Shp_phone']        ?? '';
$name    = $shpParams['Shp_name']         ?? '';
$address = $shpParams['Shp_cdek_address'] ?? '';

// Логируем подтверждённый заказ
$paidLog = sprintf(
    "[%s] PAID InvId=%s Sum=%s Email=%s Phone=%s Name=%s Address=%s\n",
    date('Y-m-d H:i:s'),
    $invId,
    $outSum,
    $email,
    $phone,
    $name,
    $address
);
@file_put_contents(__DIR__ . '/paid-orders.log', $paidLog, FILE_APPEND | LOCK_EX);

// Отправляем email с деталями заказа
$to      = $config['order_email'] ?? 'info@podymakhin.ru';
$subject = '=?UTF-8?B?' . base64_encode('Новый оплаченный заказ #' . $invId) . '?=';
$body    = "Новый оплаченный заказ\n\n"
         . "Номер: $invId\n"
         . "Сумма: $outSum ₽\n"
         . "Режим: " . ($isTest ? 'ТЕСТ' : 'БОЙ') . "\n\n"
         . "Покупатель: $name\n"
         . "Телефон: $phone\n"
         . "Email: $email\n\n"
         . "Адрес СДЭК: $address\n\n"
         . "Отправь книгу на указанный пункт выдачи.";
$headers = "From: robokassa@podymakhin.ru\r\n"
         . "Reply-To: $email\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";
@mail($to, $subject, $body, $headers);

// Отвечаем Робокассе: OK<InvId> — ТОЛЬКО этот формат принимается!
echo 'OK' . $invId;
