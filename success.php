<?php
/* Страница после оплаты. Ссылку на курс показываем только тогда,
   когда банк подтвердил платёж — иначе адрес этой страницы разошёлся бы
   по чатам и курс забирали бы бесплатно. */
require __DIR__ . '/tbank.php';
$c = cfg();

$paymentId = preg_replace('~\D~', '', $_GET['PaymentId'] ?? $_GET['paymentId'] ?? '');
$orderId   = preg_replace('~[^a-z0-9\-]~i', '', $_GET['OrderId'] ?? $_GET['orderId'] ?? '');

$ok = false; $order = null;
if ($paymentId !== '') {
  $state = payment_state($paymentId);
  $ok = is_paid($state);
  if ($ok && $orderId === '') $orderId = (string)($state['OrderId'] ?? '');
  if ($ok && $orderId !== '') store($orderId, ['status' => $state['Status'], 'paid' => true, 'confirmed_at' => date('c')]);
}
if ($orderId !== '') $order = load_order($orderId);
if (!$ok && $order && !empty($order['paid'])) $ok = true;   // уведомление могло прийти раньше возврата

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $ok ? 'Оплата прошла' : 'Проверяем оплату' ?> · Клодовство</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Forum&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="pay.css">
</head>
<body>
<header class="topbar">
  <a href="index.html" class="brand"><span><b>Клодовство</b><s>курс по Claude</s></span></a>
  <a href="index.html" class="back">На главную</a>
</header>

<main>
  <div class="bg"></div>
  <div class="card">
  <?php if ($ok): ?>
    <h1>Врата <em>открыты</em></h1>
    <p class="lead">Оплата прошла, чек уже летит на&nbsp;почту. Заходи в&nbsp;закрытый канал, курс внутри.</p>
    <?php if ($orderId): ?><div class="order">Заказ <b><?= $h($orderId) ?></b></div><?php endif; ?>
    <div class="row">
      <a href="<?= $h($c['course_link']) ?>" class="btn" target="_blank" rel="noopener"><span>Войти в курс</span></a>
    </div>
    <p class="note">Ссылка не&nbsp;открылась? Напиши мне: <a href="<?= $h($c['support_tg']) ?>" target="_blank" rel="noopener">@dimasterrr</a>, добавлю руками.</p>
  <?php else: ?>
    <h1>Проверяем оплату</h1>
    <p class="lead">Банк ещё не&nbsp;подтвердил платёж. Обычно это занимает до&nbsp;минуты: обнови страницу чуть позже.</p>
    <?php if ($orderId): ?><div class="order">Заказ <b><?= $h($orderId) ?></b></div><?php endif; ?>
    <div class="row">
      <a href="" class="btn"><span>Обновить</span></a>
      <a href="<?= $h($c['support_tg']) ?>" target="_blank" rel="noopener" class="btn ghost"><span>Написать мне</span></a>
    </div>
    <p class="note">Если деньги списались, а&nbsp;страница так и&nbsp;пишет это, напиши мне номер заказа: разберусь вручную и&nbsp;выдам доступ.</p>
  <?php endif; ?>
  </div>
</main>

<footer>
  <span>© 2026 Клодовство · Дмитрий Барилко</span>
  <a href="<?= $h($c['support_tg']) ?>" target="_blank" rel="noopener">@dimasterrr</a>
</footer>
</body>
</html>
