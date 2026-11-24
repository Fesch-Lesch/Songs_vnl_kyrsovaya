<?php
require 'config.php';
$user = current_user($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    exit('Доступ запрещён');
}

/* Запрос 1: расчёт стоимости позиций и заказов (подытог, скидка, доставка, итог) */
$q1 = $pdo->query("
    SELECT o.id AS order_id,
           u.login,
           o.status,
           o.subtotal,
           o.discount,
           o.delivery_cost,
           o.total,
           COUNT(oi.id) AS items_count
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY o.id, u.login, o.status, o.subtotal, o.discount, o.delivery_cost, o.total
    ORDER BY o.id DESC
")->fetchAll();

/* Запрос 2: группировка и расчёт выручки и количества позиций по форматам и типам операций */
$q2 = $pdo->query("
    SELECT a.format,
           oi.type AS operation_type,
           COUNT(oi.id) AS operations_count,
           COALESCE(SUM(oi.qty), 0) AS total_qty,
           COALESCE(SUM(oi.price * oi.qty), 0) AS total_amount
    FROM order_items oi
    JOIN albums a ON a.id = oi.album_id
    GROUP BY a.format, oi.type
    ORDER BY a.format, oi.type
")->fetchAll();

/* Запрос 3: складские остатки и объём поставок по альбомам */
$q3 = $pdo->query("
    SELECT a.id,
           a.artist,
           a.title,
           a.format,
           a.stock AS current_stock,
           COALESCE(SUM(s.qty), 0) AS supplied_qty
    FROM albums a
    LEFT JOIN supplies s ON s.album_id = a.id
    GROUP BY a.id, a.artist, a.title, a.format, a.stock
    ORDER BY a.id
")->fetchAll();

$format = $_GET['format'] ?? 'pdf';
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_songs_vnl.json"');
    echo json_encode([
        'generated_at' => date('c'),
        'system' => 'SONGS.VNL',
        'query_1_orders_cost' => $q1,
        'query_2_sales_and_rent_by_format' => $q2,
        'query_3_stock_and_supplies' => $q3,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Аналитический отчёт SONGS.VNL (PDF / Печать)</title>
<style>
  body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
  h1, h2 { margin: 18px 0 8px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
  th, td { border: 1px solid #888; padding: 6px 8px; text-align: left; }
  th { background: #efefef; }
  .toolbar { margin-bottom: 18px; display: flex; gap: 10px; }
  @media print { .toolbar { display: none; } }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">Сохранить в PDF / Печать</button>
    <a href="report_export.php?format=json">Скачать JSON</a>
    <a href="admin.php?tab=docs">Назад в админку</a>
  </div>

  <h1>Аналитический отчёт информационной системы SONGS.VNL</h1>
  <p>Дата формирования: <?= e(date('d.m.Y H:i')) ?></p>

  <h2>1. Расчёт стоимости заказов</h2>
  <table>
    <tr><th>ID заказа</th><th>Пользователь</th><th>Статус</th><th>Позиций</th><th>Подытог</th><th>Скидка</th><th>Доставка</th><th>Итого</th></tr>
    <?php foreach ($q1 as $r): ?>
      <tr>
        <td><?= (int)$r['order_id'] ?></td>
        <td><?= e($r['login']) ?></td>
        <td><?= e($r['status']) ?></td>
        <td><?= (int)$r['items_count'] ?></td>
        <td><?= (float)$r['subtotal'] ?></td>
        <td><?= (float)$r['discount'] ?></td>
        <td><?= (float)$r['delivery_cost'] ?></td>
        <td><?= (float)$r['total'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>2. Выручка и операции по форматам носителей и типам (покупка / аренда)</h2>
  <table>
    <tr><th>Формат</th><th>Тип операции</th><th>Операций</th><th>Количество</th><th>Сумма</th></tr>
    <?php foreach ($q2 as $r): ?>
      <tr>
        <td><?= e($r['format']) ?></td>
        <td><?= e($r['operation_type']) ?></td>
        <td><?= (int)$r['operations_count'] ?></td>
        <td><?= (int)$r['total_qty'] ?></td>
        <td><?= (float)$r['total_amount'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>3. Остатки на складе и учёт поставок</h2>
  <table>
    <tr><th>ID</th><th>Исполнитель</th><th>Альбом</th><th>Формат</th><th>Остаток на складе</th><th>Всего поставлено</th></tr>
    <?php foreach ($q3 as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['artist']) ?></td>
        <td><?= e($r['title']) ?></td>
        <td><?= e($r['format']) ?></td>
        <td><?= (int)$r['current_stock'] ?></td>
        <td><?= (int)$r['supplied_qty'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>