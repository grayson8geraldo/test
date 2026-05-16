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

$fio            = clean($_POST['fio']             ?? '', 100);
$phone          = clean($_POST['phone']          ?? '', 20);
$cdekAddress    = clean($_POST['cdek_address']   ?? '', 255);

$errors = [];
if (mb_strlen($fio, 'UTF-8') < 3) $errors[] = 'ФИО';
if (!preg_match('/^\+?\d[\d\s\(\)\-]{9,}$/', $phone)) $errors[] = 'Телефон';
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
    $moscowRe = '/москва|подольск|балаших|химки|реутов|мытищ|любер|королёв|королев|красногорск|одинцов|пушкино|щёлков|щелков|долгопруд|зеленоград|солнечногорск|павловский посад|серпухов|видное|лобн|раменск|истра|ногинск|электросталь|орехово-зуев|сергиев посад|дзержинский|чехов(?![а-яё])|наро-фоминск|можайск|ступин|клин(?!ц)|дмитров(?!град)|домодедов|апрелевк|бронниц|воскресенск|дедовск|зарайск|кашир|коломн|лосино-петровск|лыткарин|озёры|озеры|пересвет|протвин|пущин|рузск|серебряные пруды|талдом|фрязин|шатур|электрогорск|юбилейн|яхром|подмоск|московская обл/u';
    if (preg_match($moscowRe, $lower)) {
        return ['zone' => 'msk', 'name' => 'Москва и МО', 'cost' => 300];
    }

    // Санкт-Петербург и Ленинградская область — 400 ₽
    $spbRe = '/санкт-петербург|петербург|питер|спб|ленинградская обл|гатчин|колпин|петергоф|кронштадт|пушкин(?![ао])|сестрорецк|павловск(?![а-яё])|красное село|ломоносов|всеволожск|выборг|тосн|кириш|тихвин|волхов|луг(?:а|$|,)|кингисепп|сосновый бор|приозерск|бокситогорск/u';
    if (preg_match($spbRe, $lower)) {
        return ['zone' => 'spb', 'name' => 'Санкт-Петербург и ЛО', 'cost' => 400];
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

// Формируем Receipt с номенклатурой (требование 54-ФЗ).
// Без него Робокасса не выставит фискальный чек и часть способов оплаты пропадёт.
// tax обязателен для каждой позиции (none / vat0 / vat10 / vat20 / vat110 / vat120).
$taxRate = $config['tax_rate'] ?? 'none';

$receipt = [
    'items' => [
        [
            'name'           => 'Книга «Каркас над пропастью: строю дом на болоте»',
            'quantity'       => 1,
            'sum'            => round((float)$bookPrice, 2),
            'payment_method' => 'full_payment',
            'payment_object' => 'commodity',
            'tax'            => $taxRate,
        ],
        [
            'name'           => 'Доставка СДЭК (' . $delivery['name'] . ')',
            'quantity'       => 1,
            'sum'            => round((float)$delivery['cost'], 2),
            'payment_method' => 'full_payment',
            'payment_object' => 'service',
            'tax'            => $taxRate,
        ],
    ],
];

// СНО передаём ТОЛЬКО если явно задано в config — иначе Робокасса возьмёт
// значение по умолчанию из настроек ЛКК (это рекомендация документации).
if (!empty($config['tax_system'])) {
    $receipt = ['sno' => $config['tax_system']] + $receipt;
}

$receiptJson = json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Shp-параметры возвращаются обратно в Result URL вместе с оплатой.
// ВНИМАНИЕ: они должны быть отсортированы по алфавиту при формировании подписи.
$shpParams = [
    'Shp_book_price'    => number_format($bookPrice, 2, '.', ''),
    'Shp_cdek_address'  => $cdekAddress,
    'Shp_delivery_cost' => (string)$delivery['cost'],
    'Shp_delivery_zone' => $delivery['name'],
    'Shp_name'          => $fio,
    'Shp_phone'         => $phone,
];
ksort($shpParams);

// Формируем строку для подписи:
// MerchantLogin:OutSum:InvId:Receipt(сырой JSON!):Password#1:Shp_key1=value1:...
// ВАЖНО: Receipt в подписи идёт БЕЗ URL-кодирования.
// URL-кодирование — только при передаче параметра в форме.
$signatureParts = [$merchantLogin, $outSum, (string)$invId, $receiptJson, $password1];
foreach ($shpParams as $k => $v) {
    $signatureParts[] = $k . '=' . $v;
}
$signatureString = implode(':', $signatureParts);
$algo = $config['hash_algo'] ?? 'md5';
$signatureValue = hash($algo, $signatureString);

// Логируем заказ до оплаты (на случай, если Result URL не придёт)
$logEntry = sprintf(
    "[%s] InvId=%d Phone=%s Name=%s Address=%s\n",
    date('Y-m-d H:i:s'),
    $invId,
    $phone,
    $fio,
    $cdekAddress
);
@file_put_contents(__DIR__ . '/orders.log', $logEntry, FILE_APPEND | LOCK_EX);

// Собираем параметры для Робокассы. Receipt передаём как сырой JSON —
// браузер URL-кодирует его при отправке формы POST, и это даст ту же
// строку, которую мы использовали в подписи.
$params = array_merge([
    'MerchantLogin'  => $merchantLogin,
    'OutSum'         => $outSum,
    'InvId'          => $invId,
    'Description'    => $description,
    'Receipt'        => $receiptJson,
    'SignatureValue' => $signatureValue,
    'IsTest'         => $isTest ? '1' : '0',
], $shpParams);

// Отправляем через автосабмит POST-формы (Receipt с номенклатурой
// может быть большим, GET-редирект упирается в ограничения длины URL).
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8">
<title>Перенаправление на оплату...</title>
<style>
body{font-family:'Segoe UI',system-ui,sans-serif;background:#fafaf7;color:#2d3436;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;padding:20px}
.card{max-width:420px;text-align:center;background:#fff;border-radius:16px;padding:32px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.spinner{width:48px;height:48px;border:4px solid #f5f3ec;border-top-color:#d4a843;border-radius:50%;margin:0 auto 16px;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
h1{font-size:18px;color:#1a3c28;margin:0 0 8px}
p{font-size:14px;color:#666;margin:0 0 16px}
.btn{display:inline-block;padding:12px 24px;background:#1a3c28;color:#fff;border:0;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none}
</style></head><body>
<div class="card">
  <div class="spinner"></div>
  <h1>Перенаправляем на оплату...</h1>
  <p>Если страница не открылась автоматически — нажмите кнопку.</p>
  <form id="rk" method="POST" action="https://auth.robokassa.ru/Merchant/Index.aspx">
<?php foreach ($params as $k => $v): ?>
    <input type="hidden" name="<?= htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
    <button type="submit" class="btn">Перейти к оплате</button>
  </form>
</div>
<script>document.getElementById('rk').submit();</script>
</body></html>
<?php
exit;
