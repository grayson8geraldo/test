<?php
/**
 * Настройки Робокассы
 *
 * Скопируй этот файл в config.php и заполни реальные значения.
 * config.php НЕ коммитится в git (указан в .gitignore)
 */

return [
    // Идентификатор магазина (MerchantLogin)
    'merchant_login' => 'YOUR_MERCHANT_LOGIN',

    // Тестовый режим? true = тест, false = бой
    'is_test' => true,

    // Пароли для БОЕВОГО режима
    'password1' => 'YOUR_PROD_PASSWORD_1',
    'password2' => 'YOUR_PROD_PASSWORD_2',

    // Пароли для ТЕСТОВОГО режима
    'test_password1' => 'YOUR_TEST_PASSWORD_1',
    'test_password2' => 'YOUR_TEST_PASSWORD_2',

    // Цена книги в рублях (без доставки — её платят в СДЭК при получении)
    'price' => '2990.00',

    // Описание заказа (видит покупатель на странице оплаты)
    'description' => 'Книга «Каркас над пропастью: строю дом на болоте»',

    // Email для получения уведомлений о заказах
    'order_email' => 'info@podymakhin.ru',

    // URL сайта (для редиректов)
    'site_url' => 'https://podymakhin.ru',

    // Алгоритм подписи. На reg.ru проще всего MD5
    'hash_algo' => 'md5',

    // Система налогообложения для фискального чека (требование 54-ФЗ).
    // Возможные значения:
    //   osn                 — общая
    //   usn_income          — УСН «доходы» (6%)
    //   usn_income_outcome  — УСН «доходы минус расходы» (15%)
    //   patent              — патент
    //   esn                 — ЕСХН
    // Самозанятые (НПД) — указывают 'usn_income' и tax_rate='none'.
    'tax_system' => 'usn_income',

    // НДС в чеке. Возможные значения:
    //   none    — без НДС (для УСН и НПД)
    //   vat0    — НДС 0%
    //   vat10   — НДС 10%
    //   vat20   — НДС 20%
    //   vat110  — НДС 10/110
    //   vat120  — НДС 20/120
    'tax_rate' => 'none',
];
