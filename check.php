<?php
/* Диагностика платёжного модуля. Открой этот адрес после выгрузки.
   Секреты не показывает: только длину и признаки мусора по краям.
   Удали файл, когда всё заработает. */
require __DIR__ . '/tbank.php';
$c = cfg();

$rows = [];
$add = function ($name, $ok, $note = '') use (&$rows) { $rows[] = [$name, $ok, $note]; };

$add('Версия PHP', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);
$add('Расширение cURL', function_exists('curl_init'), function_exists('curl_init') ? 'есть' : 'нет');
$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$add('HTTPS включён', $https, $https ? 'да' : 'банк требует https');

/* ── доступы: показываем длину и мусор, но не сами значения ── */
$rawCfg = require __DIR__ . '/config.php';
foreach ([['terminal_key','Terminal Key'], ['password','Пароль']] as [$k, $label]) {
  $raw = (string)($rawCfg[$k] ?? '');
  $clean = trim($raw, " \t\n\r\0\x0B\xC2\xA0");
  $filled = $clean !== '' && !str_starts_with($clean, 'СЮДА');
  $note = $filled ? 'символов: ' . strlen($clean) : 'не заполнен';
  if ($filled && $raw !== $clean) $note .= ' · были лишние пробелы по краям, вычищены';
  if ($filled && preg_match('~[^\x20-\x7E]~', $clean)) $note .= ' · ВНИМАНИЕ: есть нелатинские символы, проверь копипаст';
  $add("$label заполнен", $filled, $note);
}

$add('Папка orders закрыта снаружи', is_file(__DIR__ . '/orders/.htaccess'),
     is_file(__DIR__ . '/orders/.htaccess') ? 'да' : 'ЗАГРУЗИ файл orders/.htaccess — там данные покупателей');

/* ── боевая проверка: пробуем подписать запрос и спросить банк ── */
$probe = 'не проверялось';
$probeOk = false;
if (function_exists('curl_init')) {
  $res = api('GetState', ['PaymentId' => '1']);
  if (!empty($res['Success'])) { $probeOk = true; $probe = 'банк принял подпись'; }
  else {
    $code = (string)($res['ErrorCode'] ?? '');
    $msg  = trim(($res['Message'] ?? '') . ' ' . ($res['Details'] ?? ''));
    if ($code === '204' || stripos($msg, 'токен') !== false) {
      $probe = 'ПОДПИСЬ НЕ ПРИНЯТА (' . $code . '): ключ и пароль не из одной пары либо скопированы с ошибкой';
    } elseif (in_array($code, ['7', '9'], true) || stripos($msg, 'не найден') !== false) {
      $probeOk = true; $probe = 'подпись принята (платёж №1 не найден — так и должно быть)';
    } else {
      $probe = 'ответ банка: ' . $code . ' ' . $msg;
    }
  }
}
$add('Ключ и пароль приняты банком', $probeOk, $probe);

$base = $c['base_url'] ?? '';
$guess = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$add('Адрес сайта в настройках', rtrim($base, '/') === $guess, "в конфиге: {$base} · фактический: {$guess}");

$bad = count(array_filter($rows, fn($r) => !$r[1]));
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Проверка платёжного модуля</title>
<style>
body{background:#060907;color:#fff4e2;font:15px/1.5 system-ui,sans-serif;padding:34px 20px;max-width:820px;margin:0 auto}
h1{font-size:22px;margin-bottom:6px}.sub{color:#9a9a9a;font-size:14px;margin-bottom:24px}
table{width:100%;border-collapse:collapse}
td{padding:11px 8px;border-bottom:1px solid rgba(182,148,81,.25);vertical-align:top}
td.s{width:34px;font-size:18px}td.n{color:rgba(255,244,226,.55);font-size:13px}
.ok{color:#d4ff00}.no{color:#ffd1dc}
.done{margin-top:26px;padding:16px;border:1px solid rgba(212,255,0,.4);background:rgba(212,255,0,.06)}
.warn{margin-top:26px;padding:16px;border:1px solid rgba(255,209,220,.4);background:rgba(255,209,220,.06)}
code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:3px;font-size:13px}
</style></head><body>
<h1>Проверка платёжного модуля</h1>
<div class="sub">Клодовство · страница только для тебя, удали её после настройки</div>
<table>
<?php foreach ($rows as [$n, $ok, $note]): ?>
  <tr><td class="s <?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✓' : '✕' ?></td>
      <td><?= htmlspecialchars($n) ?></td><td class="n"><?= htmlspecialchars($note) ?></td></tr>
<?php endforeach; ?>
</table>
<?php if ($bad === 0): ?>
  <div class="done"><b>Всё готово.</b> Проведи тестовую оплату, потом удали <code>check.php</code>.</div>
<?php else: ?>
  <div class="warn"><b>Осталось починить: <?= $bad ?>.</b> Строки с ✕ подсказывают, что делать.</div>
<?php endif; ?>
</body></html>
