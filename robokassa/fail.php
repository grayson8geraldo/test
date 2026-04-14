<?php
/**
 * fail.php — страница «Оплата не прошла».
 * На неё возвращается покупатель из Робокассы при отмене/ошибке оплаты.
 */
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата не прошла — Podymakhin.ru</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:#fafaf7;color:#2d3436;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{max-width:520px;width:100%;background:#fff;border-radius:16px;padding:40px 32px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08)}
        .icon{width:72px;height:72px;margin:0 auto 20px;border-radius:50%;background:#fdecea;display:flex;align-items:center;justify-content:center}
        .icon svg{width:36px;height:36px;color:#c0392b}
        h1{font-size:24px;color:#1a3c28;margin-bottom:12px}
        p{font-size:15px;line-height:1.6;color:#555;margin-bottom:16px}
        .btn{display:inline-block;padding:12px 28px;background:#1a3c28;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;margin-top:12px;transition:opacity .2s}
        .btn--outline{background:transparent;color:#1a3c28;border:2px solid #1a3c28;margin-left:8px}
        .btn:hover{opacity:.9}
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        </div>
        <h1>Оплата не&nbsp;прошла</h1>
        <p>Транзакция была отменена или&nbsp;произошла ошибка. Деньги с&nbsp;вашей карты не&nbsp;списаны.</p>
        <p>Попробуйте оформить заказ ещё&nbsp;раз или&nbsp;напишите нам на&nbsp;<a href="mailto:info@podymakhin.ru">info@podymakhin.ru</a>.</p>
        <a href="/#pricing" class="btn">Попробовать снова</a>
        <a href="/" class="btn btn--outline">На главную</a>
    </div>
</body>
</html>
