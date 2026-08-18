<?php
/* ============================================================
   КЛОДОВСТВО · работа с эквайрингом
   Подпись, создание платежа, проверка статуса, приём уведомлений.
   Пароль живёт только здесь, на сервере, и наружу не отдаётся.
   ============================================================ */

const API = 'https://securepay.tinkoff.ru/v2/';

function cfg(): array {
  static $c = null;
  if ($c === null) {
    $f = __DIR__ . '/config.php';
    if (!is_file($f)) fail_hard('Нет файла config.php. Скопируй config.sample.php и заполни доступы.');
    $c = require $f;
  }
  return $c;
}

function fail_hard(string $msg): void {
  log_line('FATAL ' . $msg);
  http_response_code(500);
  exit('Платёжный модуль не настроен. Напишите нам, мы починим за пять минут.');
}

function log_line(string $s): void {
  $d = __DIR__ . '/orders';
  if (!is_dir($d)) @mkdir($d, 0775, true);
  @file_put_contents($d . '/log.txt', date('Y-m-d H:i:s') . ' ' . $s . "\n", FILE_APPEND | LOCK_EX);
}

/* Подпись запроса: только корневые скалярные поля + пароль,
   отсортированные по имени ключа, значения склеены, SHA-256. */
function sign(array $p): string {
  $p['Password'] = cfg()['password'];
  $flat = [];
  foreach ($p as $k => $v) {
    if (is_array($v)) continue;                      // DATA и Receipt в подпись не входят
    if (is_bool($v)) $v = $v ? 'true' : 'false';
    $flat[$k] = (string)$v;
  }
  ksort($flat);
  return hash('sha256', implode('', $flat));
}

function api(string $method, array $payload): array {
  $payload['TerminalKey'] = cfg()['terminal_key'];
  $payload['Token'] = sign($payload);

  $ch = curl_init(API . $method);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
  ]);
  $raw  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($raw === false) { log_line("HTTP-ошибка $method: $err"); return ['Success' => false, 'Message' => 'Банк не ответил']; }
  $res = json_decode($raw, true);
  if (!is_array($res)) { log_line("Плохой ответ $method (HTTP $code): $raw"); return ['Success' => false, 'Message' => 'Банк ответил непонятно']; }
  if (empty($res['Success'])) log_line("Отказ $method: " . ($res['ErrorCode'] ?? '?') . ' ' . ($res['Message'] ?? '') . ' ' . ($res['Details'] ?? ''));
  return $res;
}

/* Создание платежа. Возвращает ссылку на оплату либо null. */
function create_payment(string $tariff, string $email, string $phone, string $name): ?array {
  $c = cfg();
  if (!isset($c['tariffs'][$tariff])) return null;
  $t = $c['tariffs'][$tariff];
  $kop = (int)round($t['price'] * 100);
  $order = date('ymd-His') . '-' . $tariff . '-' . substr(bin2hex(random_bytes(3)), 0, 4);

  $res = api('Init', [
    'Amount'          => $kop,
    'OrderId'         => $order,
    'Description'     => $t['name'],
    'SuccessURL'      => $c['base_url'] . '/success.php',
    'FailURL'         => $c['base_url'] . '/fail.html',
    'NotificationURL' => $c['base_url'] . '/notify.php',
    'DATA' => [
      'Email'    => $email,
      'Phone'    => $phone,
      'Name'     => $name,
    ],
    'Receipt' => [
      'Email'    => $email,
      'Phone'    => $phone,
      'Taxation' => $c['taxation'],
      'Items'    => [[
        'Name'          => mb_substr($t['name'], 0, 128),
        'Price'         => $kop,
        'Quantity'      => 1,
        'Amount'        => $kop,
        'Tax'           => 'none',
        'PaymentMethod' => 'full_prepayment',
        'PaymentObject' => 'service',
      ]],
    ],
  ]);

  if (empty($res['Success']) || empty($res['PaymentURL'])) return null;

  store($order, [
    'order' => $order, 'tariff' => $tariff, 'name' => $name,
    'email' => $email, 'phone' => $phone, 'amount' => $t['price'],
    'payment_id' => $res['PaymentId'] ?? '', 'status' => $res['Status'] ?? 'NEW',
    'created' => date('c'),
  ]);
  log_line("Создан заказ $order · {$t['name']} · $email");
  return ['url' => $res['PaymentURL'], 'order' => $order];
}

/* Статус платежа прямо у банка — на него опираемся при выдаче доступа. */
function payment_state(string $payment_id): array {
  return api('GetState', ['PaymentId' => $payment_id]);
}
function is_paid(array $state): bool {
  return !empty($state['Success']) && in_array($state['Status'] ?? '', ['CONFIRMED', 'AUTHORIZED'], true);
}

/* Проверка подписи входящего уведомления от банка. */
function notification_valid(array $body): bool {
  $got = (string)($body['Token'] ?? '');
  unset($body['Token']);
  return $got !== '' && hash_equals(sign($body), $got);
}

/* Простое файловое хранилище заказов: без базы, но всё видно. */
function store(string $order, array $data): void {
  $d = __DIR__ . '/orders';
  if (!is_dir($d)) @mkdir($d, 0775, true);
  $f = $d . '/' . preg_replace('~[^a-z0-9\-]~i', '', $order) . '.json';
  $old = is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
  file_put_contents($f, json_encode(array_merge($old, $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function load_order(string $order): ?array {
  $f = __DIR__ . '/orders/' . preg_replace('~[^a-z0-9\-]~i', '', $order) . '.json';
  return is_file($f) ? (json_decode(file_get_contents($f), true) ?: null) : null;
}
