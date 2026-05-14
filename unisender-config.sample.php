<?php
/**
 * Настройки Unisender
 *
 * Скопируйте этот файл в unisender-config.php и заполните реальные значения.
 * unisender-config.php НЕ коммитится в git (указан в .gitignore)
 */

return [
    // API-ключ из кабинета Unisender (Настройки → Интеграция и API)
    'api_key' => 'YOUR_UNISENDER_API_KEY',

    // ID списка подписчиков (создайте в разделе «Контакты → Списки»,
    // ID виден в URL: unisender.com/ru/contacts/lists/edit?id=XXXXX)
    'list_id' => 1,

    // Имя и email отправителя (должен быть подтверждён в Unisender)
    'sender_name' => 'Юрий Подымахин',
    'sender_email' => 'info@podymakhin.ru',
];
