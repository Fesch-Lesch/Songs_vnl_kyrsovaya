<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['album_id'] ?? 0);
    $type   = ($_POST['type'] ?? 'purchase') === 'rent' ? 'rent' : 'purchase';
    $action = $_POST['action'] ?? 'add';

    $st = $pdo->prepare('SELECT id FROM albums WHERE id = ?');
    $st->execute([$id]);
    if ($st->fetch()) {
        if ($action === 'remove')       cart_remove($id, $type);
        elseif ($action === 'set_type') cart_set_type($id, $type);
        else                            cart_add($id, $type);
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