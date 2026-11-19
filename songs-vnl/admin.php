<?php
require 'config.php';
$page = 'admin';
$user = current_user($pdo);
if (!$user || $user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$tab = $_GET['tab'] ?? 'albums';
$allowedTabs = ['albums', 'orders', 'users', 'stores', 'cost', 'sql'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'albums';

$msg = '';
$err = '';
$sqlRows = null;

$COST_SQL = 'SELECT o.id AS order_id, u.login,
       SUM(oi.qty * (COALESCE(m.mat_cost, 0) + COALESCE(op.op_cost, 0))) AS full_cost
FROM orders o
JOIN users u ON u.id = o.user_id
JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN (SELECT pm.album_id, SUM(pm.norm * m.price) AS mat_cost
             FROM product_materials pm JOIN materials m ON m.id = pm.material_id
            GROUP BY pm.album_id) m ON m.album_id = oi.album_id
LEFT JOIN (SELECT po.album_id, SUM(po.norm * o2.rate) AS op_cost
             FROM product_operations po JOIN operations o2 ON o2.id = po.operation_id
            GROUP BY po.album_id) op ON op.album_id = oi.album_id
GROUP BY o.id, u.login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    try {
        switch ($act) {
            case 'album_save':
                $id    = (int)($_POST['id'] ?? 0);
                $cover = trim($_POST['cover_old'] ?? '');
                $audio = trim($_POST['audio_old'] ?? '');
                $price = max(1.0, (float)($_POST['price'] ?? 3600));
                $rentPrice = max(1.0, (float)($_POST['rent_price'] ?? 199));
                $stock = max(0, (int)($_POST['stock'] ?? 1));
                $warnCover = '';
                $warnAudio = '';

                if (!empty($_FILES['cover']['name'])) {
                    if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
                        $warnCover = 'обложка не принята (код ' . (int)$_FILES['cover']['error'] . ')';
                    } else {
                        $dir = 'images/covers';
                        if (!is_dir($dir)) mkdir($dir, 0777, true);
                        $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                            $warnCover = 'обложка: допустимы png / jpg / jpeg / webp / gif';
                        } else {
                            $name = 'album_' . time() . '_' . random_int(100, 999) . '.' . $ext;
                            if (!move_uploaded_file($_FILES['cover']['tmp_name'], $dir . '/' . $name)) {
                                $warnCover = 'обложка: ошибка записи в ' . $dir;
                            } else {
                                $cover = $dir . '/' . $name;
                            }
                        }
                    }
                }

                if (!empty($_FILES['audio']['name'])) {
                    if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                        $warnAudio = 'трек не принят (код ' . (int)$_FILES['audio']['error'] . ')';
                    } else {
                        $dir = AUDIO_DIR;
                        if (!is_dir($dir)) mkdir($dir, 0777, true);
                        $ext = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
                        if ($ext !== 'mp3') {
                            $warnAudio = 'трек: принимается только mp3';
                        } else {
                            $name = 'track_' . time() . '_' . random_int(100, 999) . '.mp3';
                            if (!move_uploaded_file($_FILES['audio']['tmp_name'], $dir . '/' . $name)) {
                                $warnAudio = 'трек: ошибка записи в ' . $dir;
                            } else {
                                $audio = $dir . '/' . $name;
                            }
                        }
                    }
                }

                $format = in_array($_POST['format'] ?? '', ['vinil', 'cd', 'dvd'], true) ? $_POST['format'] : 'vinil';
                if ($id > 0) {
                    $pdo->prepare('UPDATE albums SET artist=?,title=?,year=?,country=?,format=?,genre=?,price=?,rent_price=?,stock=?,popularity=?,about=?,cover=?,audio=? WHERE id=?')
                        ->execute([
                            trim($_POST['artist'] ?? ''),
                            trim($_POST['title'] ?? ''),
                            (int)($_POST['year'] ?? 2000),
                            trim($_POST['country'] ?? ''),
                            $format,
                            trim($_POST['genre'] ?? 'rock'),
                            $price,
                            $rentPrice,
                            $stock,
                            (int)($_POST['popularity'] ?? 0),
                            trim($_POST['about'] ?? ''),
                            $cover !== '' ? $cover : null,
                            $audio !== '' ? $audio : null,
                            $id,
                        ]);
                } else {
                    $pdo->prepare('INSERT INTO albums (artist,title,year,country,format,genre,price,rent_price,stock,popularity,about,cover,audio) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([
                            trim($_POST['artist'] ?? ''),
                            trim($_POST['title'] ?? ''),
                            (int)($_POST['year'] ?? 2000),
                            trim($_POST['country'] ?? ''),
                            $format,
                            trim($_POST['genre'] ?? 'rock'),
                            $price,
                            $rentPrice,
                            $stock,
                            (int)($_POST['popularity'] ?? 0),
                            trim($_POST['about'] ?? ''),
                            $cover !== '' ? $cover : null,
                            $audio !== '' ? $audio : null,
                        ]);
                    $id = (int)$pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM songs WHERE album_id=?')->execute([$id]);
                $ins = $pdo->prepare('INSERT INTO songs (album_id,side,position,title) VALUES (?,?,?,?)');
                foreach (preg_split('/\R/', trim($_POST['tracks'] ?? '')) as $ln) {
                    if (preg_match('/^([AB])\s*(\d+)\s*[-–—]?\s*(.+)$/u', trim($ln), $m)) {
                        $ins->execute([$id, strtoupper($m[1]), (int)$m[2], trim($m[3])]);
                    }
                }

                $warns = array_filter([$warnCover, $warnAudio]);
                $msg = 'Альбом сохранён. Трек в БД: ' . ($audio !== '' ? $audio : 'НЕ привязан')
                     . ($warns ? '. Внимание: ' . implode('; ', $warns) : '');
                break;

            case 'album_del':
                $pdo->prepare('DELETE FROM albums WHERE id=?')->execute([(int)$_POST['id']]);
                $msg = 'Альбом удалён';
                break;

            case 'order_status':
                $stt = in_array($_POST['status'] ?? '', ['paid', 'issued', 'done', 'cancelled'], true) ? $_POST['status'] : 'paid';
                $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$stt, (int)$_POST['id']]);
                $msg = 'Статус заказа обновлён';
                break;

            case 'order_del':
                $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([(int)$_POST['id']]);
                $msg = 'Заказ удалён';
                break;

            case 'user_role':
                if ((int)$_POST['id'] === (int)$user['id']) {
                    $err = 'Нельзя изменить собственную роль';
                } else {
                    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'client';
                    $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, (int)$_POST['id']]);
                    $msg = 'Роль обновлена';
                }
                break;

            case 'user_unblock':
                $pdo->prepare('UPDATE users SET is_blocked = 0, failed_attempts = 0 WHERE id = ?')->execute([(int)$_POST['id']]);
                $msg = 'Пользователь разблокирован';
                break;

            case 'user_del':
                if ((int)$_POST['id'] === (int)$user['id']) {
                    $err = 'Нельзя удалить самого себя';
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([(int)$_POST['id']]);
                    $msg = 'Пользователь удалён';
                }
                break;

            case 'users_import':
                $json = null;
                if (!empty($_FILES['json']['name']) && $_FILES['json']['error'] === UPLOAD_ERR_OK) {
                    $json = file_get_contents($_FILES['json']['tmp_name']);
                } elseif (is_file(__DIR__ . '/Заказчики.json')) {
                    $json = file_get_contents(__DIR__ . '/Заказчики.json');
                }
                if ($json === null) {
                    $err = 'Файл Заказчики.json не найден: приложите файл или положите его в корень проекта';
                } else {
                    $rows = json_decode($json, true);
                    if (!is_array($rows)) {
                        $err = 'Некорректный JSON: ожидается массив заказчиков';
                    } else {
                        $n = 0;
                        $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                        $ins = $pdo->prepare('INSERT INTO users (login,email,password_hash,role,name,phone,address) VALUES (?,?,?,?,?,?,?)');
                        foreach ($rows as $r) {
                            $email = trim($r['email'] ?? '');
                            if ($email === '') continue;
                            $chk->execute([$email]);
                            if ($chk->fetch()) continue;
                            $login = trim($r['login'] ?? '') ?: explode('@', $email)[0];
                            $ins->execute([
                                $login,
                                $email,
                                password_hash('Client1!', PASSWORD_DEFAULT),
                                'client',
                                trim($r['name'] ?? ''),
                                trim($r['phone'] ?? ''),
                                trim($r['address'] ?? ''),
                            ]);
                            $n++;
                        }
                        $msg = 'Импортировано заказчиков: ' . $n;
                    }
                }
                break;

            case 'store_save':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $pdo->prepare('UPDATE stores SET address=?,hours=? WHERE id=?')
                        ->execute([trim($_POST['address'] ?? ''), trim($_POST['hours'] ?? ''), $id]);
                } else {
                    $pdo->prepare('INSERT INTO stores (address,hours) VALUES (?,?)')
                        ->execute([trim($_POST['address'] ?? ''), trim($_POST['hours'] ?? '')]);
                }
                $msg = 'Магазин сохранён';
                break;

            case 'store_del':
                $pdo->prepare('DELETE FROM stores WHERE id=?')->execute([(int)$_POST['id']]);
                $msg = 'Магазин удалён';
                break;

            case 'sql':
                $q = trim($_POST['sql'] ?? '');
                if ($q === '') {
                    $err = 'Пустой запрос';
                } else {
                    $st = $pdo->query($q);
                    $sqlRows = $st->columnCount() > 0 ? $st->fetchAll() : [];
                    $msg = 'Выполнено: строк в выборке ' . count($sqlRows) . ', затронуто строк ' . $st->rowCount();
                }
                break;
        }
    } catch (PDOException $ex) {
        $err = 'Ошибка БД: ' . $ex->getMessage();
    }
}

$edit = null;
$tracksText = '';
if ($tab === 'albums' && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM albums WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
    if ($edit) {
        $st = $pdo->prepare('SELECT * FROM songs WHERE album_id=? ORDER BY side,position');
        $st->execute([(int)$edit['id']]);
        foreach ($st->fetchAll() as $s) {
            $tracksText .= $s['side'] . $s['position'] . ' - ' . $s['title'] . "\n";
        }
    }
}

$editStore = null;
if ($tab === 'stores' && isset($_GET['editstore'])) {
    $st = $pdo->prepare('SELECT * FROM stores WHERE id=?');
    $st->execute([(int)$_GET['editstore']]);
    $editStore = $st->fetch() ?: null;
}

require 'header.php';
?>
<style>
.admin .tabs{display:flex;gap:10px;margin:16px 0 22px;flex-wrap:wrap}
.admin .tabs a{display:inline-flex;border:none;border-radius:30px;padding:0;
  background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%)}
.admin .tabs a:not(.on):hover{filter:brightness(1.08)}
.admin .tabs a:not(.on)>span{display:inline-flex;align-items:center;height:45px;padding:0 24px;border-radius:30px;
  background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%)}
.admin .tabs a:not(.on)>span>b{font:400 25px 'Alumni Sans SC',Arial,sans-serif;color:#38201D}
.admin .tabs a.on{padding:2px}
.admin .tabs a.on>span{display:inline-flex;align-items:center;height:41px;padding:0 22px;border-radius:28px;background:#38201D}
.admin .tabs a.on>span>b{font:400 25px 'Alumni Sans SC',Arial,sans-serif;
  background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.admin .btn.rent{width:auto;height:51px;padding:2px;border:none;border-radius:10px;display:inline-flex;
  background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%)}
.admin .btn.rent>span{display:inline-flex;align-items:center;justify-content:center;height:47px;padding:0 24px;border-radius:8px;background:#423227}
.admin .btn.rent>span>b{font:400 30px 'Alumni Sans SC',Arial,sans-serif;
  background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.admin table{width:100%;border-collapse:separate;border-spacing:0;background:#423227;border-radius:16px;overflow:hidden;margin:14px 0}
.admin th,.admin td{padding:10px 12px;font-size:22px;text-align:left;border-bottom:1px solid #55402c;vertical-align:middle}
.admin th{color:#51D4C9;font-weight:400;font-size:20px}
.admin tr:last-child td{border-bottom:none}
.admin .thumbadm{width:56px;height:56px;object-fit:cover;border-radius:8px;display:block;margin:2px 0}
.admin .thumbadm.big{width:120px;height:120px}
.admin .no{color:#997C68;font-size:18px}
.admin .items{font-size:16px;color:#997C68;max-width:420px}
.admin .acts2{display:flex;gap:10px;align-items:center}
.admin .acts2 a{color:#51D4C9;font-size:20px}
.admin .acts2 button,.admin .delbtn{background:#AD473F;border:0;color:#F6EFE3;border-radius:8px;padding:4px 12px;font-size:18px;cursor:pointer}
.admin .inline{display:inline-flex}
.admin .chip.blocked{background:#AD473F;color:#F6EFE3}
.admin select,.admin input,.admin textarea{background:#31261F;border:0;border-radius:8px;color:#F6EFE3;font:400 20px 'Alumni Sans SC',Arial,sans-serif;padding:6px 10px}
.admin .aform{background:#423227;border-radius:16px;padding:20px;display:grid;gap:12px;max-width:760px;margin:16px 0}
.admin .aform label{display:grid;gap:6px;font-size:20px;color:#51D4C9}
.admin .aform .row2{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.admin .aform textarea{resize:vertical}
.admin .btn.small{width:auto;height:40px;padding:0 20px;font-size:22px}
.admin .sqlpre{background:#31261F;border-radius:12px;padding:14px;font-size:16px;color:#9fe8d8;overflow:auto;white-space:pre-wrap;margin:10px 0}
.admin .aform .btn:not(.rent){
  width:auto;
  min-width:144px;
  height:47px;
  padding:0 24px;
  font-size:24px;
  white-space:nowrap;
}
</style>

<main class="wrap admin">
<h1>Админ-панель</h1>

<nav class="tabs">
  <a class="<?= $tab === 'albums' ? 'on' : '' ?>" href="?tab=albums"><span><b>альбомы</b></span></a>
  <a class="<?= $tab === 'orders' ? 'on' : '' ?>" href="?tab=orders"><span><b>заказы</b></span></a>
  <a class="<?= $tab === 'users' ? 'on' : '' ?>" href="?tab=users"><span><b>пользователи</b></span></a>
  <a class="<?= $tab === 'stores' ? 'on' : '' ?>" href="?tab=stores"><span><b>магазины</b></span></a>
  <a class="<?= $tab === 'cost' ? 'on' : '' ?>" href="?tab=cost"><span><b>стоимость заказов</b></span></a>
  <a class="<?= $tab === 'sql' ? 'on' : '' ?>" href="?tab=sql"><span><b>SQL-консоль</b></span></a>
</nav>

<?php if ($tab === 'albums'): ?>
  <p><a class="btn small" href="?tab=albums&edit=0">+ добавить альбом</a></p>
  <table>
    <tr><th>id</th><th>обложка</th><th>трек</th><th>исполнитель</th><th>альбом</th><th>формат</th><th>жанр</th><th>цена</th><th>аренда</th><th>остаток</th><th></th></tr>
    <?php foreach ($pdo->query('SELECT * FROM albums ORDER BY id') as $a): ?>
      <tr>
        <td><?= (int)$a['id'] ?></td>
        <td><?php if ($c = cover_src($a)): ?><img class="thumbadm" src="<?= e($c) ?>" alt=""><?php else: ?><span class="no">градиент</span><?php endif; ?></td>
        <td><?php if ($au = audio_src($a)): ?><a href="<?= e($au) ?>" target="_blank" style="color:#51D4C9">▶</a><?php else: ?><span class="no">—</span><?php endif; ?></td>
        <td><?= e($a['artist']) ?></td>
        <td><?= e($a['title']) ?></td>
        <td><?= e($a['format']) ?></td>
        <td><?= e($a['genre']) ?></td>
        <td><?= money($a['price']) ?></td>
        <td><?= money($a['rent_price']) ?></td>
        <td><?= (int)$a['stock'] ?></td>
        <td class="acts2">
          <a href="?tab=albums&edit=<?= (int)$a['id'] ?>">изменить</a>
          <form method="post" onsubmit="return confirm('Удалить альбом вместе с треками?')">
            <input type="hidden" name="act" value="album_del">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button>удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if (isset($_GET['edit'])): ?>
  <form class="aform" method="post" enctype="multipart/form-data">
    <input type="hidden" name="act" value="album_save">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <input type="hidden" name="cover_old" value="<?= e($edit['cover'] ?? '') ?>">
    <input type="hidden" name="audio_old" value="<?= e($edit['audio'] ?? '') ?>">
    <label>исполнитель<input name="artist" required value="<?= e($edit['artist'] ?? '') ?>"></label>
    <label>название<input name="title" required value="<?= e($edit['title'] ?? '') ?>"></label>
    <div class="row2">
      <label>год<input type="number" name="year" value="<?= (int)($edit['year'] ?? 2000) ?>"></label>
      <label>страна<input name="country" value="<?= e($edit['country'] ?? '') ?>"></label>
      <label>формат<select name="format"><?php foreach (['vinil', 'cd', 'dvd'] as $f): ?><option <?= ($edit['format'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></label>
      <label>жанр<input name="genre" value="<?= e($edit['genre'] ?? 'rock') ?>"></label>
    </div>
    <div class="row2">
      <label>цена<input type="number" min="1" name="price" value="<?= (int)($edit['price'] ?? 3600) ?>"></label>
      <label>аренда/сутки<input type="number" min="1" name="rent_price" value="<?= (int)($edit['rent_price'] ?? 199) ?>"></label>
      <label>остаток<input type="number" min="0" name="stock" value="<?= (int)($edit['stock'] ?? 1) ?>"></label>
      <label>популярность<input type="number" name="popularity" value="<?= (int)($edit['popularity'] ?? 0) ?>"></label>
    </div>
    <label>обложка (png/jpg — заменит градиент)<input type="file" name="cover" accept="image/*"></label>
    <?php if ($c = cover_src($edit ?? [])): ?><img class="thumbadm big" src="<?= e($c) ?>" alt=""><?php endif; ?>
    <label>трек (mp3 — уйдёт в папку <?= e(AUDIO_DIR) ?>/, в БД сохранится ссылка)<input type="file" name="audio" accept=".mp3,audio/mpeg"></label>
    <?php if ($au = audio_src($edit ?? [])): ?><a style="color:#51D4C9;font-size:18px" href="<?= e($au) ?>" target="_blank">▶ текущий трек: <?= e($au) ?></a><?php endif; ?>
    <label>описание<textarea name="about" rows="3"><?= e($edit['about'] ?? '') ?></textarea></label>
    <label>треклист (по строке: A1 - название)<textarea name="tracks" rows="8"><?= e($tracksText) ?></textarea></label>
    <div class="row2">
      <button class="btn">сохранить</button>
      <a class="btn rent" href="?tab=albums"><span><b>отмена</b></span></a>
    </div>
  </form>
  <?php endif; ?>

<?php elseif ($tab === 'orders'): ?>
  <table>
    <tr><th>id</th><th>user</th><th>статус</th><th>состав</th><th>сумма</th><th>дата</th><th></th></tr>
    <?php
    $orders = $pdo->query('SELECT o.*, u.login,
        (SELECT GROUP_CONCAT(CONCAT(a.artist, " - ", a.title) SEPARATOR "; ")
           FROM order_items oi JOIN albums a ON a.id = oi.album_id
          WHERE oi.order_id = o.id) AS items
        FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC')->fetchAll();
    foreach ($orders as $o): ?>
      <tr>
        <td><?= (int)$o['id'] ?></td>
        <td><?= e($o['login']) ?></td>
        <td>
          <form method="post" class="inline">
            <input type="hidden" name="act" value="order_status">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <select name="status" onchange="this.form.submit()">
              <?php foreach (['paid', 'issued', 'done', 'cancelled'] as $s): ?>
                <option <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td class="items"><?= e($o['items'] ?? '') ?></td>
        <td><?= money($o['total']) ?></td>
        <td><?= e($o['created_at']) ?></td>
        <td>
          <form method="post" class="inline" onsubmit="return confirm('Удалить заказ?')">
            <input type="hidden" name="act" value="order_del">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <button class="delbtn">удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

<?php elseif ($tab === 'users'): ?>
  <form class="aform" method="post" enctype="multipart/form-data">
    <input type="hidden" name="act" value="users_import">
    <label>импорт заказчиков (Заказчики.json)<input type="file" name="json" accept=".json,application/json"></label>
    <button class="btn">импортировать</button>
  </form>
  <table>
    <tr><th>id</th><th>login</th><th>email</th><th>имя</th><th>роль</th><th>статус</th><th></th></tr>
    <?php foreach ($pdo->query('SELECT * FROM users ORDER BY id') as $u2): ?>
      <tr>
        <td><?= (int)$u2['id'] ?></td>
        <td><?= e($u2['login']) ?></td>
        <td><?= e($u2['email']) ?></td>
        <td><?= e($u2['name']) ?></td>
        <td>
          <?php if ((int)$u2['id'] === (int)$user['id']): ?><span class="chip"><?= e($u2['role']) ?></span>
          <?php else: ?>
            <form method="post" class="inline">
              <input type="hidden" name="act" value="user_role">
              <input type="hidden" name="id" value="<?= (int)$u2['id'] ?>">
              <select name="role" onchange="this.form.submit()">
                <option <?= $u2['role'] === 'client' ? 'selected' : '' ?>>client</option>
                <option <?= $u2['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
              </select>
            </form>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($u2['is_blocked']): ?>
            <span class="chip blocked">заблокирован (<?= (int)$u2['failed_attempts'] ?>)</span>
            <?php if ((int)$u2['id'] !== (int)$user['id']): ?>
              <form method="post" class="inline">
                <input type="hidden" name="act" value="user_unblock">
                <input type="hidden" name="id" value="<?= (int)$u2['id'] ?>">
                <button class="obtn">разблокировать</button>
              </form>
            <?php endif; ?>
          <?php else: ?><span class="chip">активен</span><?php endif; ?>
        </td>
        <td>
          <?php if ((int)$u2['id'] !== (int)$user['id']): ?>
            <form method="post" class="inline" onsubmit="return confirm('Удалить пользователя?')">
              <input type="hidden" name="act" value="user_del">
              <input type="hidden" name="id" value="<?= (int)$u2['id'] ?>">
              <button class="delbtn">удалить</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

<?php elseif ($tab === 'stores'): ?>
  <table>
    <tr><th>id</th><th>адрес</th><th>часы</th><th></th></tr>
    <?php foreach ($pdo->query('SELECT * FROM stores ORDER BY id') as $s): ?>
      <tr>
        <td><?= (int)$s['id'] ?></td>
        <td><?= e($s['address']) ?></td>
        <td><?= e($s['hours']) ?></td>
        <td class="acts2">
          <a href="?tab=stores&editstore=<?= (int)$s['id'] ?>">изменить</a>
          <form method="post" class="inline" onsubmit="return confirm('Удалить магазин?')">
            <input type="hidden" name="act" value="store_del">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="delbtn">удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <form class="aform" method="post">
    <input type="hidden" name="act" value="store_save">
    <input type="hidden" name="id" value="<?= (int)($editStore['id'] ?? 0) ?>">
    <div class="row2">
      <label>адрес<input name="address" required value="<?= e($editStore['address'] ?? '') ?>"></label>
      <label>часы<input name="hours" required value="<?= e($editStore['hours'] ?? '') ?>"></label>
    </div>
    <div class="row2">
      <button class="btn"><?= $editStore ? 'сохранить' : 'добавить магазин' ?></button>
      <?php if ($editStore): ?><a class="btn rent" href="?tab=stores"><span><b>отмена</b></span></a><?php endif; ?>
    </div>
  </form>

<?php elseif ($tab === 'cost'): ?>
  <p style="font-size:22px;color:#997C68;margin:0 0 10px">Полная стоимость заказа = количество × (материалы по нормам расхода + технологические операции по нормам).</p>
  <pre class="sqlpre"><?= e($COST_SQL) ?></pre>
  <table>
    <tr><th>order_id</th><th>user</th><th>полная стоимость</th></tr>
    <?php foreach ($pdo->query($COST_SQL) as $r): ?>
      <tr><td><?= (int)$r['order_id'] ?></td><td><?= e($r['login']) ?></td><td><?= money($r['full_cost']) ?></td></tr>
    <?php endforeach; ?>
  </table>

<?php elseif ($tab === 'sql'): ?>
  <form class="aform" method="post">
    <input type="hidden" name="act" value="sql">
    <label>SQL-запрос<textarea name="sql" rows="6" placeholder="SELECT * FROM albums"><?= e($_POST['sql'] ?? '') ?></textarea></label>
    <button class="btn">выполнить</button>
  </form>
  <?php if ($sqlRows !== null): ?>
    <table>
      <?php foreach ($sqlRows as $i => $r): ?>
        <?php if ($i === 0): ?><tr><?php foreach (array_keys($r) as $k): ?><th><?= e($k) ?></th><?php endforeach; ?></tr><?php endif; ?>
        <tr><?php foreach ($r as $v): ?><td><?= e(is_scalar($v) ? (string)$v : '') ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (!$sqlRows): ?><tr><td>пустой результат</td></tr><?php endif; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>
</main>
<?php require 'footer.php'; ?>