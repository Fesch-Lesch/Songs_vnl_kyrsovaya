<?php
/**
 * Документация: см. api_docs.md (Задания 5–6 КИМ 09.02.07-5-2027).
 * Данные НЕ отдаются в сыром виде — перед отправкой проходят обработку.
 */
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
$method = $_GET['method'] ?? 'albums';
$user = current_user($pdo);

if ($method === 'albums') {
    $rows = $pdo->query('SELECT id, artist, title, genre, format, year, price, rent_price, stock FROM albums')->fetchAll();
    $out = array_map(fn($a) => [
        'id'        => (int)$a['id'],
        'name'      => $a['artist'] . ' — ' . $a['title'],
        'category'  => mb_strtoupper($a['genre'], 'UTF-8'),
        'media'     => $a['format'],
        'year'      => (int)$a['year'],
        'buy_price' => (float)$a['price'],
        'rent'      => ['per_day' => (float)$a['rent_price'], 'week' => (float)$a['rent_price'] * RENT_DAYS],
        'available' => (int)$a['stock'] > 0,
    ], $rows);
    echo json_encode(['ok' => true, 'data' => $out], JSON_UNESCAPED_UNICODE);

} elseif ($method === 'album') {
    $st = $pdo->prepare('SELECT id, artist, title, genre, format, country, year, price, rent_price FROM albums WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $a = $st->fetch();
    if (!$a) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'not found']); exit; }
    $st = $pdo->prepare('SELECT side, position, title FROM songs WHERE album_id = ? ORDER BY side, position');
    $st->execute([$a['id']]);
    echo json_encode(['ok' => true, 'data' => ['album' => $a, 'tracks' => $st->fetchAll()]], JSON_UNESCAPED_UNICODE);

} elseif ($method === 'notes' || $method === 'note') {
    if ($method === 'notes') {
        $rows = $pdo->query('SELECT n.id, n.title, n.body, n.created_at, u.login
                             FROM notes n JOIN users u ON u.id = n.user_id ORDER BY n.id')->fetchAll();
    } else {
        $st = $pdo->prepare('SELECT n.id, n.title, n.body, n.created_at, u.login
                             FROM notes n JOIN users u ON u.id = n.user_id WHERE n.id = ?');
        $st->execute([(int)($_GET['id'] ?? 0)]);
        $rows = $st->fetchAll();
        if (!$rows) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'not found']); exit; }
    }
    /* обработка перед выдачей: title_user и formatted_date по шаблону */
    $out = array_map(fn($n) => [
        'id'             => (int)$n['id'],
        'title_user'     => $n['title'] . ' (' . $n['login'] . ')',
        'formatted_date' => date('d.m.Y H:i', strtotime($n['created_at'])),
        'body'           => (string)$n['body'],
    ], $rows);
    echo json_encode(['ok' => true, 'data' => $method === 'note' ? $out[0] : $out], JSON_UNESCAPED_UNICODE);

} elseif ($method === 'note_add') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$user) {
        http_response_code(403); echo json_encode(['ok' => false, 'error' => 'forbidden']); exit;
    }
    $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $title = trim($in['title'] ?? '');
    if ($title === '') { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'title required']); exit; }
    $pdo->prepare('INSERT INTO notes (user_id, title, body) VALUES (?,?,?)')
        ->execute([$user['id'], $title, trim($in['body'] ?? '')]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unknown method']);
}