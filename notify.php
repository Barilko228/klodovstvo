<?php
/* Уведомление от банка о смене статуса платежа.
   Банк ждёт в ответ ровно строку OK, иначе будет повторять запрос. */
require __DIR__ . '/tbank.php';

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) { log_line('NOTIFY мусор: ' . substr($raw, 0, 300)); http_response_code(400); exit('BAD'); }
if (!notification_valid($body)) { log_line('NOTIFY плохая подпись: ' . substr($raw, 0, 300)); http_response_code(403); exit('BAD SIGN'); }

$order  = (string)($body['OrderId'] ?? '');
$status = (string)($body['Status'] ?? '');
$paid   = in_array($status, ['CONFIRMED', 'AUTHORIZED'], true);

if ($order !== '') {
  store($order, [
    'status'     => $status,
    'payment_id' => (string)($body['PaymentId'] ?? ''),
    'paid'       => $paid,
    'updated'    => date('c'),
  ]);
}
log_line("NOTIFY $order → $status" . ($paid ? ' · ОПЛАЧЕНО' : ''));

echo 'OK';
