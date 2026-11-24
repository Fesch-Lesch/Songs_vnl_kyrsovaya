<?php
require 'config.php';
$user = current_user($pdo);
if (!$user) { header('Location: login.php'); exit; }

if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $fmt  = $_GET['fmt'] ?? 'json';
    if ($user['role'] !== 'admin') { http_response_code(403); exit('Доступ запрещён'); }

    $rows = match ($type) {
        'orders' => $pdo->query('SELECT id, user_id, status, delivery, delivery_status, courier, address, subtotal, discount, delivery_cost, total, created_at FROM orders ORDER BY id DESC')->fetchAll(),
        'users'  => array_map(fn($u) => [
            'id' => $u['id'], 'login' => $u['login'], 'email' => $u['email'], 'role' => $u['role'],
            'name' => decrypt_pii($u['name'] ?? ''), 'phone' => decrypt_pii($u['phone'] ?? ''), 'address' => decrypt_pii($u['address'] ?? '')
        ], $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll()),
        default  => $pdo->query('SELECT id, artist, title, year, country, format, genre, price, rent_price, popularity, stock FROM albums ORDER BY id')->fetchAll(),
    };

    log_action($pdo, (int)$user['id'], 'export_' . $type, $type, null, "Экспорт $type в $fmt");

    if ($fmt === 'csv' || $fmt === 'xls') {
        $ext = $fmt === 'xls' ? 'xls' : 'csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$type}_report.{$ext}\"");
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ';');
            foreach ($rows as $r) fputcsv($out, $r, ';');
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$type}_report.json\"");
    echo json_encode(['exported_at' => date('c'), 'items' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
$docKey  = $_GET['doc'] ?? 'invoice';
if (!in_array($docKey, ['rental_contract', 'invoice', 'return_act'], true)) $docKey = 'invoice';

$st = $pdo->prepare('SELECT o.*, u.login, u.email, u.name, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$st->execute([$orderId]);
$order = $st->fetch();
if (!$order || ((int)$order['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
    http_response_code(404);
    exit('Документ не найден');
}

$stItems = $pdo->prepare('SELECT oi.type, oi.qty, oi.price, oi.rent_from, oi.rent_to, a.artist, a.title
                        FROM order_items oi JOIN albums a ON a.id = oi.album_id WHERE oi.order_id = ?');
$stItems->execute([$orderId]);
$itemsList = array_map(fn($i) => $i['artist'] . ' — ' . $i['title'] . ' (' . $i['type'] . ' ×' . $i['qty'] . ')', $stItems->fetchAll());

$tpl = $pdo->prepare('SELECT * FROM doc_templates WHERE doc_key = ?');
$tpl->execute([$docKey]);
$template = $tpl->fetch() ?: ['title' => 'Документ по заказу №' . $orderId, 'body_html' => '<p>Заказ №{{order_id}} — {{total}} ₽</p>'];

$vars = [
    '{{order_id}}'   => (string)$order['id'],
    '{{user_name}}'  => e(decrypt_pii($order['name'] ?? '') ?: $order['login']),
    '{{user_email}}' => e($order['email'] ?? ''),
    '{{user_phone}}' => e(decrypt_pii($order['phone'] ?? '')),
    '{{address}}'    => e($order['address'] ?? ''),
    '{{delivery}}'   => e($order['delivery'] ?? ''),
    '{{status}}'     => e($order['status'] ?? ''),
    '{{items}}'      => e(implode('; ', $itemsList)),
    '{{total}}'      => e((string)$order['total']),
    '{{created_at}}' => e((string)$order['created_at']),
];
$rendered = strtr($template['body_html'], $vars);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title><?= e($template['title']) ?></title>
<style>
body{font-family:Arial,sans-serif;max-width:800px;margin:30px auto;padding:20px;color:#222}
.bar{margin-bottom:20px;display:flex;gap:10px}
button{padding:8px 16px;cursor:pointer}
@media print{.bar{display:none}}
</style>
</head>
<body>
<div class="bar">
  <button onclick="window.print()">Печать / Сохранить в PDF</button>
  <a href="cabinet.php">Назад в кабинет</a>
</div>
<?= $rendered ?>
</body>
</html>