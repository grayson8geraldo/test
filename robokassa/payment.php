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
    header('Content-Type: text/html; charset=utf-8');
    $msg = htmlspecialchars('Проверьте поля: ' . implode(', ', $errors), ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ошибка оформления заказа</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#fafaf7;color:#2d3436;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{max-width:480px;width:100%;background:#fff;border-radius:16px;padding:40px 32px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.icon{width:64px;height:64px;margin:0 auto 16px;border-radius:50%;background:#fff5e6;display:flex;align-items:center;justify-content:center;font-size:32px;color:#d4a843}
h1{font-size:22px;color:#1a3c28;margin-bottom:12px}
p{font-size:15px;line-height:1.6;color:#555;margin-bottom:8px}
.hint{font-size:13px;color:#888;background:#f5f3ec;padding:10px 14px;border-radius:8px;margin:16px 0}
.btn{display:inline-block;padding:12px 28px;background:#1a3c28;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;margin-top:12px}
.btn:hover{opacity:.9}
</style></head><body>
<div class="card">
  <div class="icon">⚠️</div>
  <h1>Не удалось оформить заказ</h1>
  <p>{$msg}</p>
  <div class="hint">💡 Для СДЭК: введите ваш город и кликните на&nbsp;маркер пункта выдачи на&nbsp;карте. Адрес должен отобразиться в&nbsp;зелёной плашке.</div>
  <a href="/#pricing" class="btn">Вернуться к заказу</a>
</div></body></html>
HTML;
    exit;
}

// Выбираем пароли в зависимости от режима
$isTest   = !empty($config['is_test']);
$password1 = $isTest ? $config['test_password1'] : $config['password1'];

$merchantLogin = $config['merchant_login'];
$bookPrice     = (float)$config['price'];
$invId         = (int)(microtime(true) * 1000); // уникальный номер заказа
$description   = $config['description'];

/**
 * Определяем тариф доставки СДЭК по адресу пункта выдачи.
 * Регионы определяются регулярными выражениями по названию города/области.
 * ВАЖНО: эта же логика дублируется в JS (script.js) — синхронизируйте при правках.
 */
function detectDeliveryCost(string $address): array {
    $lower = mb_strtolower($address, 'UTF-8');

    // Москва и Московская область — 300 ₽
    $moscowRe = '/моск(?!овск.*обл.*(?!московск))|подольск|балаших|химки|реутов|мытищ|любер|королёв|королев|красногорск|одинцов|жуков|пушкин|щёлков|щелков|долгопруд|зеленоград|солнечногорск|павлов посад|серпухов|видн|лобн|раменск|истр|ногинск|электросталь|орехово-зуев|сергиев посад|дзержинский|чехов|наро-фоминск|можайск|ступин|клин|дмитров|домодедов|апрелевк|бронниц|воскресенск|дедовск|жуковск|зарайск|кашир|коломн|красноарм|лосино-петровск|лыткарин|озёр|озер|пересвет|протвин|пущин|реш|рузск|серебряные пруды|солнечногорск|талдом|фрязин|шатур|щёлков|электрогорск|юбилейн|яхром|подмоск|московская обл/u';
    if (preg_match($moscowRe, $lower)) {
        return ['zone' => 'msk', 'name' => 'Москва и МО', 'cost' => 300];
    }

    // Сибирь и Дальний Восток — 700 ₽
    $siberiaRe = '/новосибирск|омск|томск|красноярск|иркутск|якут|саха респ|хабаровск|владивосток|магадан|сахалин|камчат|чукот|петропавловск-камчат|благовещенск|чит(?!к)|улан-удэ|кемеров|барнаул|новокузнецк|ангарск|братск|комсомольск-на-амуре|находк|уссурийск|биробиджан|анадырь|норильск|абакан|горно-алтайск|приморск(?:ий)? край|хабаровск(?:ий)? край|бурят|тыв|тува|хакас|чукотск|еврейск|сибирск|дальневосточн|алтайск(?:ий)? край|забайкальск|южно-сахалинск|нерюнгри|мирный|алдан|тында|свободный|зея|шимановск|райчихинск|белогорск|сковородино|холмск|корсаков|охотск|оха|северо-курильск|елизово|вилюч|ессо/u';
    if (preg_match($siberiaRe, $lower)) {
        return ['zone' => 'sib', 'name' => 'Сибирь и Дальний Восток', 'cost' => 700];
    }

    // Все остальные регионы — Европейская часть России (включая Урал и Юг)
    return ['zone' => 'eu', 'name' => 'Европейская часть России', 'cost' => 500];
}

$delivery       = detectDeliveryCost($cdekAddress);
$totalAmount    = $bookPrice + $delivery['cost'];
$outSum         = number_format($totalAmount, 2, '.', '');

// Shp-параметры возвращаются обратно в Result URL вместе с оплатой.
// ВНИМАНИЕ: они должны быть отсортированы по алфавиту при формировании подписи.
$shpParams = [
    'Shp_book_price'    => number_format($bookPrice, 2, '.', ''),
    'Shp_cdek_address'  => $cdekAddress,
    'Shp_delivery_cost' => (string)$delivery['cost'],
    'Shp_delivery_zone' => $delivery['name'],
    'Shp_email'         => $email,
    'Shp_name'          => $surname . ' ' . $name,
    'Shp_phone'         => $phone,
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
