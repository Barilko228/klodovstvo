<?php
/* Диагностика платёжного модуля. Секреты не показывает.
   Удали файл, когда всё заработает. */
require __DIR__ . '/tbank.php';
$c = cfg();
$rows = [];
$add = function ($name, $ok, $note = '') use (&$rows) { $rows[] = [$name, $ok, $note]; };

$add('Версия PHP', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);
$add('Расширение cURL', function_exists('curl_init'), 'есть');
$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$add('HTTPS включён', $https, $https ? 'да' : 'открой страницу по https');

$rawCfg = require __DIR__ . '/config.php';
foreach ([['terminal_key','Terminal Key'], ['password','Пароль']] as [$k, $label]) {
  $raw = (string)($rawCfg[$k] ?? '');
  $clean = trim($raw, " \t\n\r\0\x0B\xC2\xA0");
  $filled = $clean !== '' && !str_starts_with($clean, 'СЮДА');
  $note = $filled ? 'символов: ' . strlen($clean) : 'не заполнен';
  if ($filled && $raw !== $clean) $note .= ' · были лишние пробелы, вычищены';
  $add("$label заполнен", $filled, $note);
}
$add('Папка orders закрыта снаружи', is_file(__DIR__ . '/orders/.htaccess'), 'да');

/* ── 1. подпись ── */
$st = api('GetState', ['PaymentId' => '1']);
$signOk = !empty($st['Success']) || in_array((string)($st['ErrorCode'] ?? ''), ['7','9'], true);
$add('Подпись принята банком', $signOk, $signOk ? 'да' : 'ключ и пароль не из одной пары');

/* ── 2. платёж БЕЗ чека ── */
$kop = 100;
$base = [
  'Amount' => $kop, 'OrderId' => 'diag-' . date('His') . '-a', 'Description' => 'Проверка модуля',
  'SuccessURL' => $c['base_url'] . '/success.php',
  'FailURL' => $c['base_url'] . '/fail.html',
  'NotificationURL' => $c['base_url'] . '/notify.php',
];
$r1 = api('Init', $base);
$ok1 = !empty($r1['Success']) && !empty($r1['PaymentURL']);
$add('Платёж без чека создаётся', $ok1,
  $ok1 ? 'да, банк вернул ссылку на оплату'
       : 'отказ ' . ($r1['ErrorCode'] ?? '?') . ': ' . trim(($r1['Message'] ?? '') . ' ' . ($r1['Details'] ?? '')));

/* ── 3. платёж С чеком (как в боевой форме) ── */
$withReceipt = $base;
$withReceipt['OrderId'] = 'diag-' . date('His') . '-b';
$withReceipt['DATA'] = ['Email' => 'test@example.com', 'Phone' => '+79001234567', 'Name' => 'Проверка'];
$withReceipt['Receipt'] = [
  'Email' => 'test@example.com', 'Phone' => '+79001234567', 'Taxation' => $c['taxation'],
  'Items' => [['Name' => 'Проверка модуля', 'Price' => $kop, 'Quantity' => 1, 'Amount' => $kop,
               'Tax' => 'none', 'PaymentMethod' => 'full_prepayment', 'PaymentObject' => 'service']],
];
$r2 = api('Init', $withReceipt);
$ok2 = !empty($r2['Success']) && !empty($r2['PaymentURL']);
$add('Платёж с чеком создаётся', $ok2,
  $ok2 ? 'да' : 'отказ ' . ($r2['ErrorCode'] ?? '?') . ': ' . trim(($r2['Message'] ?? '') . ' ' . ($r2['Details'] ?? '')));
$add('Система налогообложения', true, $c['taxation'] . ' (меняется в config.php)');

$bad = count(array_filter($rows, fn($r) => !$r[1]));
$log = @file(__DIR__ . '/orders/log.txt', FILE_IGNORE_NEW_LINES) ?: [];
$log = array_slice($log, -14);
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Проверка платёжного модуля</title>
<style>
body{background:#060907;color:#fff4e2;font:15px/1.5 system-ui,sans-serif;padding:34px 20px;max-width:880px;margin:0 auto}
h1{font-size:22px;margin-bottom:6px}.sub{color:#9a9a9a;font-size:14px;margin-bottom:24px}
table{width:100%;border-collapse:collapse}
td{padding:11px 8px;border-bottom:1px solid rgba(182,148,81,.25);vertical-align:top}
td.s{width:34px;font-size:18px}td.n{color:rgba(255,244,226,.6);font-size:13px}
.ok{color:#d4ff00}.no{color:#ffd1dc}
.done{margin-top:26px;padding:16px;border:1px solid rgba(212,255,0,.4);background:rgba(212,255,0,.06)}
.warn{margin-top:26px;padding:16px;border:1px solid rgba(255,209,220,.4);background:rgba(255,209,220,.06)}
pre{margin-top:22px;padding:14px;background:rgba(255,255,255,.05);border:1px solid rgba(182,148,81,.25);
    font-size:12px;line-height:1.5;overflow-x:auto;color:rgba(255,244,226,.7)}
code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:3px;font-size:13px}
</style></head><body>
<h1>Проверка платёжного модуля</h1>
<div class="sub">Клодовство · страница только для тебя, удали после настройки</div>
<table>
<?php foreach ($rows as [$n, $ok, $note]): ?>
  <tr><td class="s <?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✓' : '✕' ?></td>
      <td><?= htmlspecialchars($n) ?></td><td class="n"><?= htmlspecialchars($note) ?></td></tr>
<?php endforeach; ?>
</table>
<?php if ($bad === 0): ?>
  <div class="done"><b>Всё готово.</b> Проведи тестовую оплату, потом удали <code>check.php</code>.</div>
<?php else: ?>
  <div class="warn"><b>Осталось починить: <?= $bad ?>.</b> Смотри строки с ✕ и лог ниже.</div>
<?php endif; ?>
<?php if ($log): ?><pre><?= htmlspecialchars(implode("\n", $log)) ?></pre><?php endif; ?>
</body></html>
