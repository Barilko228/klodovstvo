<?php
/* Диагностика: открой этот адрес после выгрузки на хостинг.
   Покажет, что настроено, а что нет. Удали файл, когда всё зелёное. */
header('Content-Type: text/html; charset=utf-8');
$rows = [];
$add = function ($name, $ok, $note = '') use (&$rows) { $rows[] = [$name, $ok, $note]; };

$add('Версия PHP', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);
$add('Расширение cURL', function_exists('curl_init'), function_exists('curl_init') ? 'есть' : 'нет — оплата работать не будет');
$add('Функция hash sha256', in_array('sha256', hash_algos(), true), 'нужна для подписи');

$cfgFile = __DIR__ . '/config.php';
$hasCfg = is_file($cfgFile);
$add('Файл config.php', $hasCfg, $hasCfg ? 'на месте' : 'скопируй config.sample.php в config.php');

$c = $hasCfg ? require $cfgFile : [];
$keyOk = $hasCfg && !empty($c['terminal_key']) && $c['terminal_key'] !== 'СЮДА_TERMINAL_KEY';
$pwOk  = $hasCfg && !empty($c['password'])     && $c['password']     !== 'СЮДА_ПАРОЛЬ';
$add('Terminal Key заполнен', $keyOk, $keyOk ? substr((string)$c['terminal_key'], 0, 6) . '…' : 'вставь из кабинета банка');
$add('Пароль заполнен', $pwOk, $pwOk ? 'скрыт' : 'вставь из кабинета банка');

$base = $c['base_url'] ?? '';
$guess = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$add('Адрес сайта в настройках', $base !== '' && rtrim($base, '/') === $guess, "в конфиге: {$base} · фактический: {$guess}");

$dir = __DIR__ . '/orders';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$w = is_dir($dir) && is_writable($dir);
$add('Папка orders доступна для записи', $w, $w ? 'да' : 'выстави права 775 на папку orders');

$net = false; $netNote = 'не проверялось';
if (function_exists('curl_init')) {
  $ch = curl_init('https://securepay.tinkoff.ru/v2/GetState');
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                          CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12]);
  $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
  $net = $r !== false; $netNote = $net ? 'банк отвечает' : "нет связи: $e";
}
$add('Связь с банком', $net, $netNote);

$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$add('HTTPS включён', $https, $https ? 'да' : 'выпусти сертификат в панели: банк требует https');

$bad = count(array_filter($rows, fn($r) => !$r[1]));
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Проверка платёжного модуля</title>
<style>
body{background:#060907;color:#fff4e2;font:15px/1.5 system-ui,sans-serif;padding:34px 20px;max-width:760px;margin:0 auto}
h1{font-size:22px;margin-bottom:6px}
.sub{color:#9a9a9a;font-size:14px;margin-bottom:24px}
table{width:100%;border-collapse:collapse}
td{padding:11px 8px;border-bottom:1px solid rgba(182,148,81,.25);vertical-align:top}
td.s{width:34px;font-size:18px}
td.n{color:rgba(255,244,226,.55);font-size:13px}
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
      <td><?= htmlspecialchars($n) ?></td>
      <td class="n"><?= htmlspecialchars($note) ?></td></tr>
<?php endforeach; ?>
</table>
<?php if ($bad === 0): ?>
  <div class="done"><b>Всё готово.</b> Проведи тестовую оплату, затем удали файл <code>check.php</code> с хостинга.</div>
<?php else: ?>
  <div class="warn"><b>Осталось починить пунктов: <?= $bad ?>.</b> Строки с ✕ выше подсказывают, что сделать.</div>
<?php endif; ?>
</body></html>
