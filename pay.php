<?php
/* Форма перед оплатой: собираем данные для чека и уводим на страницу банка. */
require __DIR__ . '/tbank.php';
$c = cfg();

$tariff = preg_replace('~\D~', '', $_REQUEST['t'] ?? '');
if (!isset($c['tariffs'][$tariff])) { header('Location: index.html#tiers'); exit; }
$t = $c['tariffs'][$tariff];

$err = '';
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $digits = preg_replace('~\D~', '', $phone);
  if (mb_strlen($name) < 2)                                   $err = 'Напиши, как тебя зовут.';
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))         $err = 'Проверь почту: на неё придут чек и доступ.';
  elseif (strlen($digits) < 11)                               $err = 'Телефон нужен полностью, с кодом страны.';
  elseif (empty($_POST['agree_offer']))                       $err = 'Нужно принять условия договора оферты.';
  elseif (empty($_POST['agree_pd']))                          $err = 'Нужно согласие на обработку персональных данных.';
  else {
    $phone = '+' . $digits;
    $r = create_payment($tariff, $email, $phone, $name);
    if ($r) {
      // банк не всегда возвращает номер заказа в адресе,
      // поэтому запоминаем его в браузере покупателя на сутки
      setcookie('kl_order', $r['order'], [
        'expires' => time() + 86400, 'path' => '/', 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
      ]);
      header('Location: ' . $r['url']); exit;
    }
    $err = 'Банк не принял платёж. Попробуй ещё раз или напиши мне.';
  }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Оплата · <?= $h($t['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Forum&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="pay.css">
<style>
form{max-width:420px;margin:0 auto;text-align:left}
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin-bottom:7px}
.field input[type=text],.field input[type=email],.field input[type=tel]{
  width:100%;background:rgba(255,244,226,.04);border:1px solid var(--line);color:var(--parchment);
  font-family:var(--f-ui);font-size:15px;padding:13px 15px;outline:none;transition:border-color .3s,background .3s}
.field input:focus{border-color:var(--lime);background:rgba(212,255,0,.05)}
.agree{display:flex;gap:11px;align-items:flex-start;font-size:12.5px;line-height:1.5;color:rgba(255,244,226,.6);margin:14px 0}
.agree:first-of-type{margin-top:20px}
.agree:last-of-type{margin-bottom:24px}
.agree input{margin-top:2px;accent-color:#d4ff00;width:16px;height:16px;flex:none}
.agree a{color:var(--gold-lt)}
.sum{display:flex;justify-content:space-between;align-items:baseline;padding:16px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin-bottom:22px}
.sum b{font-family:var(--f-display);font-size:1.8rem;color:var(--gold-lt)}
.sum span{font-size:13px;color:var(--muted)}
.err{border:1px solid rgba(255,209,220,.5);background:rgba(255,209,220,.07);color:#ffd1dc;
  padding:12px 15px;font-size:13.5px;line-height:1.5;margin-bottom:20px}
button.btn{width:100%;border:1px solid var(--gold);cursor:pointer;font-family:var(--f-ui)}
.tiny{font-size:11.5px;color:rgba(255,244,226,.4);line-height:1.5;margin-top:16px;text-align:center}
</style>
</head>
<body>
<header class="topbar">
  <a href="index.html" class="brand"><span><b>Клодовство</b><s>курс по Claude</s></span></a>
  <a href="index.html#tiers" class="back">К тарифам</a>
</header>

<main>
  <div class="bg"></div>
  <div class="card">
    <h1>Почти <em>у&nbsp;врат</em></h1>
    <p class="lead">Данные нужны для чека и&nbsp;доступа к&nbsp;курсу. Оплата пройдёт на&nbsp;защищённой странице банка.</p>

    <form method="post" novalidate>
      <input type="hidden" name="t" value="<?= $h($tariff) ?>">
      <div class="sum"><span><?= $h($t['name']) ?></span><b><?= number_format($t['price'], 0, '', ' ') ?> ₽</b></div>

      <?php if ($err): ?><div class="err"><?= $h($err) ?></div><?php endif; ?>

      <div class="field"><label>Имя</label>
        <input type="text" name="name" value="<?= $h($name) ?>" autocomplete="name" required></div>
      <div class="field"><label>Почта</label>
        <input type="email" name="email" value="<?= $h($email) ?>" autocomplete="email" required></div>
      <div class="field"><label>Телефон</label>
        <input type="tel" name="phone" value="<?= $h($phone) ?>" placeholder="+7 999 123-45-67" autocomplete="tel" required></div>

      <label class="agree">
        <input type="checkbox" name="agree_offer" value="1" <?= !empty($_POST['agree_offer']) ? 'checked' : '' ?>>
        <span>Принимаю условия
        <a href="https://disk.yandex.ru/i/0ASFtmQFfmXgKg" target="_blank" rel="noopener">договора оферты</a></span>
      </label>

      <label class="agree">
        <input type="checkbox" name="agree_pd" value="1" <?= !empty($_POST['agree_pd']) ? 'checked' : '' ?>>
        <span>Даю <a href="https://disk.yandex.ru/i/EfOxFurPLNch7g" target="_blank" rel="noopener">согласие на обработку персональных данных</a>
        и ознакомлен с <a href="https://disk.yandex.ru/i/R0ZyhCkG86cKQg" target="_blank" rel="noopener">политикой обработки данных</a></span>
      </label>

      <button type="submit" class="btn"><span>Перейти к оплате</span></button>
      <p class="tiny">Данные карты вводятся на стороне банка. Мы их не видим и не храним.</p>
    </form>
  </div>
</main>

<footer>
  <span>© 2026 Клодовство · Дмитрий Барилко</span>
  <a href="<?= $h($c['support_tg']) ?>" target="_blank" rel="noopener">Написать, если что-то не так</a>
</footer>
<script src="cookie.js"></script>
</body>
</html>
