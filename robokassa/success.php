<?php
/**
 * success.php — страница «Спасибо за оплату».
 * На неё возвращается покупатель из Робокассы после успешной оплаты.
 * Здесь тоже можно проверить подпись (с Паролем #1), но главное —
 * финальное подтверждение приходит на result.php.
 */
declare(strict_types=1);

$invId = htmlspecialchars((string)($_REQUEST['InvId'] ?? ''), ENT_QUOTES, 'UTF-8');
$outSum = htmlspecialchars((string)($_REQUEST['OutSum'] ?? ''), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Спасибо за заказ! — Podymakhin.ru</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:#fafaf7;color:#2d3436;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{max-width:520px;width:100%;background:#fff;border-radius:16px;padding:40px 32px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08)}
        .icon{width:72px;height:72px;margin:0 auto 20px;border-radius:50%;background:#e8f5e9;display:flex;align-items:center;justify-content:center}
        .icon svg{width:36px;height:36px;color:#27ae60}
        h1{font-size:24px;color:#1a3c28;margin-bottom:12px}
        p{font-size:15px;line-height:1.6;color:#555;margin-bottom:16px}
        .order{background:#f5f3ec;padding:12px 16px;border-radius:8px;font-size:14px;color:#444;margin:20px 0}
        .btn{display:inline-block;padding:12px 28px;background:#1a3c28;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;margin-top:12px;transition:opacity .2s}
        .btn:hover{opacity:.9}
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h1>Спасибо за заказ!</h1>
        <p>Оплата прошла успешно. Мы&nbsp;получили ваш заказ и&nbsp;отправим книгу в&nbsp;СДЭК в&nbsp;течение 2&ndash;3 рабочих дней.</p>
        <?php if ($invId !== ''): ?>
        <div class="order">
            Номер заказа: <strong>#<?= $invId ?></strong>
            <?php if ($outSum !== ''): ?><br>Сумма: <strong><?= $outSum ?>&nbsp;₽</strong><?php endif; ?>
        </div>
        <?php endif; ?>
        <p>Реквизиты для получения придут в&nbsp;СМС от&nbsp;СДЭК, когда книга поступит в&nbsp;пункт выдачи.</p>
        <a href="/" class="btn">Вернуться на главную</a>
    </div>
</body>
</html>
