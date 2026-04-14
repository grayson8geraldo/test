<?php
/**
 * payment.php
 *
 * Принимает данные из формы заказа, формирует подпись Робокассы
 * (с Паролем #1 — он хранится на сервере и НЕ доступен клиенту)
 * и перенаправляет покупателя на страницу оплаты.
 */

declare(strict_types=1);

// Принимаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Загружаем конфиг
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Server configuration error');
}
$config = require $configPath;

// Валидация входных данных
function clean(string $value, int $max = 255): string {
    $value = trim($value);
    $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '';
    return mb_substr($value, 0, $max, 'UTF-8');
}

$surname        = clean($_POST['surname']        ?? '', 50);
$name           = clean($_POST['name']           ?? '', 50);
$phone          = clean($_POST['phone']          ?? '', 20);
$email          = clean($_POST['email']          ?? '', 100);
$cdekAddress    = clean($_POST['cdek_address']   ?? '', 255);

$errors = [];
if (mb_strlen($surname, 'UTF-8') < 2) $errors[] = 'Фамилия';
if (mb_strlen($name, 'UTF-8') < 2)    $errors[] = 'Имя';
if (!preg_match('/^\+?\d[\d\s\(\)\-]{9,}$/', $phone)) $errors[] = 'Телефон';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email';
if (mb_strlen($cdekAddress, 'UTF-8') < 5) $errors[] = 'Адрес СДЭК';

if ($errors) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Проверьте поля: ' . implode(', ', $errors));
}

// Выбираем пароли в зависимости от режима
$isTest   = !empty($config['is_test']);
$password1 = $isTest ? $config['test_password1'] : $config['password1'];

$merchantLogin = $config['merchant_login'];
$outSum        = number_format((float)$config['price'], 2, '.', '');
$invId         = (int)(microtime(true) * 1000); // уникальный номер заказа
$description   = $config['description'];

// Shp-параметры возвращаются обратно в Result URL вместе с оплатой.
// ВНИМАНИЕ: они должны быть отсортированы по алфавиту при формировании подписи.
$shpParams = [
    'Shp_cdek_address' => $cdekAddress,
    'Shp_email'        => $email,
    'Shp_name'         => $surname . ' ' . $name,
    'Shp_phone'        => $phone,
];
ksort($shpParams);

// Формируем строку для подписи:
// MerchantLogin:OutSum:InvId:Password#1:Shp_key1=value1:Shp_key2=value2...
$signatureParts = [$merchantLogin, $outSum, (string)$invId, $password1];
foreach ($shpParams as $k => $v) {
    $signatureParts[] = $k . '=' . $v;
}
$signatureString = implode(':', $signatureParts);
$algo = $config['hash_algo'] ?? 'md5';
$signatureValue = hash($algo, $signatureString);

// Логируем заказ до оплаты (на случай, если Result URL не придёт)
$logEntry = sprintf(
    "[%s] InvId=%d Email=%s Phone=%s Name=%s Address=%s\n",
    date('Y-m-d H:i:s'),
    $invId,
    $email,
    $phone,
    $surname . ' ' . $name,
    $cdekAddress
);
@file_put_contents(__DIR__ . '/orders.log', $logEntry, FILE_APPEND | LOCK_EX);

// Собираем параметры для Робокассы
$params = array_merge([
    'MerchantLogin'  => $merchantLogin,
    'OutSum'         => $outSum,
    'InvId'          => $invId,
    'Description'    => $description,
    'SignatureValue' => $signatureValue,
    'Email'          => $email,
    'IsTest'         => $isTest ? '1' : '0',
], $shpParams);

$url = 'https://auth.robokassa.ru/Merchant/Index.aspx?' . http_build_query($params);

header('Location: ' . $url);
exit;
