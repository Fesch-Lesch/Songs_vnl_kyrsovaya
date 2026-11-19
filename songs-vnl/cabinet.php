<?php
require 'config.php';
$page = 'cabinet';
$user = current_user($pdo);
if (!$user) {
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?')
            ->execute([trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''), $user['id']]);
        $msg = 'Профиль сохранён';
    } elseif (isset($_POST['change_pass'])) {
        if (!password_verify($_POST['old'] ?? '', $user['password_hash'])) {
            $err = 'Старый пароль неверен';
        } elseif (($errp = password_errors($_POST['new'] ?? ''))) {
            $err = 'Новый пароль слабый: ' . implode(', ', $errp);
        } elseif (($_POST['new'] ?? '') !== ($_POST['confirm'] ?? '')) {
            $err = 'Пароли не совпадают';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($_POST['new'], PASSWORD_DEFAULT), $user['id']]);
            $msg = 'Пароль изменён';
        }
    } elseif (isset($_POST['extend'])) {
        $itemId = (int)$_POST['extend'];
        $st = $pdo->prepare("UPDATE order_items oi
                             JOIN orders o ON o.id = oi.order_id
                             SET oi.rent_to = DATE_ADD(oi.rent_to, INTERVAL ? DAY)
                             WHERE oi.id = ? AND o.user_id = ? AND oi.type = 'rent' AND o.status = 'issued'");
        $st->execute([RENT_DAYS, $itemId, $user['id']]);
        if ($st->rowCount() > 0) $msg = 'Аренда продлена на ' . RENT_DAYS . ' дн.';
        else $err = 'Не удалось продлить аренду';
    } elseif (isset($_POST['pay_fine'])) {
        $orderId = (int)$_POST['pay_fine'];
        $st = $pdo->prepare("UPDATE orders SET status = 'done' WHERE id = ? AND user_id = ?");
        $st->execute([$orderId, $user['id']]);
        if ($st->rowCount() > 0) $msg = 'Штраф оплачен, аренда завершена';
        else $err = 'Заказ не найден';
    } elseif (isset($_POST['cancel'])) {
        $orderId = (int)$_POST['cancel'];
        $st = $pdo->prepare("SELECT o.id, o.status,
                                    MAX(CASE WHEN oi.type = 'rent' AND o.status = 'issued' AND oi.rent_to < ? THEN 1 ELSE 0 END) AS has_overdue
                             FROM orders o
                             LEFT JOIN order_items oi ON oi.order_id = o.id
                             WHERE o.id = ? AND o.user_id = ?
                             GROUP BY o.id, o.status");
        $st->execute([$today, $orderId, $user['id']]);
        $ord = $st->fetch();

        if (!$ord) {
            $err = 'Заказ не найден';
        } elseif ((int)$ord['has_overdue'] === 1) {
            $err = 'Сначала оплатите штраф за просроченную аренду';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM orders WHERE id = ? AND user_id = ?')->execute([$orderId, $user['id']]);
                $pdo->commit();
                $msg = 'Заказ удалён';
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $err = 'Не удалось удалить заказ: ' . $ex->getMessage();
            }
        }
    }
    $user = current_user($pdo);
}

$st = $pdo->prepare('SELECT o.id AS order_id, o.status, o.created_at,
                            oi.id AS item_id, oi.type, oi.price, oi.rent_from, oi.rent_to,
                            a.artist, a.title
                     FROM orders o
                     JOIN order_items oi ON oi.order_id = o.id
                     JOIN albums a ON a.id = oi.album_id
                     WHERE o.user_id = ?
                     ORDER BY o.created_at DESC, oi.id');
$st->execute([$user['id']]);
$cards = $st->fetchAll();

require 'header.php';
?>
<style>
.obtn.ghost{background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%);border:none;padding:2px;height:35px;border-radius:10px;display:inline-flex}
.obtn.ghost>span{display:flex;align-items:center;justify-content:center;height:31px;padding:0 24px;border-radius:8px;background:#423227}
.obtn.ghost>span>b{font:400 20px 'Alumni Sans SC',Arial,sans-serif;
  background:linear-gradient(180deg,#51D4C9 0%,#6C7D54 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
</style>
<main class="cab">
  <section class="profile">
    <h1>Профиль</h1>
    <div class="sub"><?= e($user['email']) ?> · <?= e($user['role']) ?></div>
    <form method="post">
      <input name="name" placeholder="Имя" value="<?= e($user['name']) ?>">
      <input name="phone" placeholder="+7 (___) ___-__-__" value="<?= e($user['phone']) ?>">
      <input name="address" placeholder="Адрес" value="<?= e($user['address']) ?>">
      <button class="midbtn" name="save_profile" value="1">сохранить</button>
    </form>
    <h2>изменение пароля</h2>
    <form method="post">
      <input type="password" name="old" placeholder="старый пароль" required>
      <input type="password" name="new" placeholder="новый пароль" required>
      <input type="password" name="confirm" placeholder="подтвердить" required>
      <button class="midbtn" name="change_pass" value="1">подтвердить</button>
    </form>
  </section>

  <section>
    <h1 class="orders-head">Мои заказы · <b><?= count($cards) ?></b></h1>
    <?php foreach ($cards as $c):
      $overDays = 0;
      if ($c['type'] === 'rent' && $c['status'] === 'issued' && !empty($c['rent_to']) && $c['rent_to'] < $today) {
          $overDays = (int)floor((strtotime($today) - strtotime($c['rent_to'])) / 86400);
      }
      $status = $overDays > 0 ? 'overdue' : $c['status'];
      $label = [
          'issued'    => 'выдан / в аренде',
          'paid'      => 'оплачен',
          'overdue'   => 'просрочен',
          'done'      => 'завершён',
          'cancelled' => 'отменён',
      ][$status] ?? $status;
    ?>
      <div class="order">
        <div class="top">
          <span class="status <?= e($status) ?>"><?= e($label) ?></span>
          <span class="chip"><?= $c['type'] === 'rent' ? 'аренда' : 'покупка' ?></span>
          <span class="date"><?= date('d.m.Y · H:i', strtotime($c['created_at'])) ?></span>
          <?php if ($c['type'] === 'rent' && $c['rent_from'] && $c['rent_to']): ?>
            <span class="rent">аренда с <?= date('d.m.Y', strtotime($c['rent_from'])) ?> по <?= date('d.m.Y', strtotime($c['rent_to'])) ?></span>
          <?php endif; ?>
        </div>
        <div class="titlerow">
          <b><?= e($c['artist']) ?> - <?= e($c['title']) ?></b>
          <span class="price"><?= money($c['price']) ?></span>
        </div>
        <div class="acts">
          <?php if ($status === 'issued' && $c['type'] === 'rent'): ?>
            <form method="post"><button class="obtn" name="extend" value="<?= (int)$c['item_id'] ?>">Продлить</button></form>
            <form method="post" onsubmit="return confirm('Удалить заказ аренды?')">
              <button class="obtn ghost" name="cancel" value="<?= (int)$c['order_id'] ?>"><span><b>Удалить</b></span></button>
            </form>
          <?php elseif ($status === 'paid'): ?>
            <form method="post" onsubmit="return confirm('Отменить и удалить заказ?')">
              <button class="obtn ghost" name="cancel" value="<?= (int)$c['order_id'] ?>"><span><b>Отменить</b></span></button>
            </form>
          <?php elseif ($status === 'overdue'): ?>
            <form method="post"><button class="obtn fine" name="pay_fine" value="<?= (int)$c['order_id'] ?>">Оплатить штраф</button></form>
            <span class="fine-note">штраф за <?= $overDays ?> дн. · <?= money($overDays * FINE_PER_DAY) ?></span>
          <?php else: ?>
            <form method="post" onsubmit="return confirm('Удалить заказ?')">
              <button class="obtn ghost" name="cancel" value="<?= (int)$c['order_id'] ?>"><span><b>Удалить</b></span></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$cards): ?><p class="empty">Заказов пока нет.</p><?php endif; ?>
  </section>
</main>
<?php require 'footer.php'; ?>