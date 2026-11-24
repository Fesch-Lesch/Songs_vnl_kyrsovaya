<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['album_id'] ?? 0);
    $type   = ($_POST['type'] ?? 'purchase') === 'rent' ? 'rent' : 'purchase';
    $action = $_POST['action'] ?? 'add';

    $st = $pdo->prepare('SELECT id, title, stock FROM albums WHERE id = ?');
    $st->execute([$id]);
    $album = $st->fetch();

    if ($album) {
        if ($action === 'remove') {
            cart_remove($id, $type);
            flash_set('ok', 'Позиция удалена из корзины');
        } elseif ($action === 'set_type') {
            if ($type === 'rent' && (int)$album['stock'] < 1) {
                flash_set('err', 'Альбом «' . $album['title'] . '» закончился на складе для аренды');
            } else {
                cart_set_type($id, $type);
            }
        } else {
            if ((int)$album['stock'] < 1) {
                flash_set('err', 'Альбом «' . $album['title'] . '» закончился на складе');
            } else {
                cart_add($id, $type);
                flash_set('ok', 'Альбом «' . $album['title'] . '» добавлен в корзину');
            }
        }
    }
}

$ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$parts = parse_url($ref);
$target = 'index.php';
if (!empty($parts['path']) && empty($parts['host'])) {
    $target = ltrim($parts['path'], '/') . (!empty($parts['query']) ? '?' . $parts['query'] : '');
}
header('Location: ' . $target);
exit;
