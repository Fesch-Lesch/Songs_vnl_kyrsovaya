<?php
require 'config.php';
$page = 'cart';
$user = current_user($pdo);

$items = [];
if (cart()) {
    $ids = array_values(array_unique(array_map(fn($it) => (int)$it['album_id'], cart())));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM albums WHERE id IN ($in)");
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll() as $a) {
        $byId[(int)$a['id']] = $a;
    }
    foreach (cart() as $idx => $it) {
        $a = $byId[(int)$it['album_id']] ?? null;
        if (!$a) continue;
        $type = $it['type'] === 'rent' ? 'rent' : 'purchase';
        $sum = $type === 'rent' ? (float)$a['rent_price'] * RENT_DAYS : (float)$a['price'];
        $items[] = [
            'idx'   => $idx,
            'album' => $a,
            'type'  => $type,
            'sum'   => $sum,
        ];
    }
}

$subtotal = array_sum(array_column($items, 'sum'));
$discount = 0.0;
$err = '';
$deliveryMode = $_POST['delivery'] ?? 'pickup';
$selectedStore = (int)($_POST['store'] ?? 1);
$courierAddress = trim($_POST['address'] ?? '');
$promoCode = strtoupper(trim($_POST['promo'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        $err = 'Для оформления заказа необходимо войти в аккаунт';
    } elseif (!$items) {
        $err = 'Корзина пуста';
    } else {
        foreach ($items as $it) {
            if ((int)$it['album']['stock'] < 1) {
                $err = 'Альбом «' . $it['album']['title'] . '» закончился на складе';
                break;
            }
        }

        $deliveryMode = $deliveryMode === 'courier' ? 'courier' : 'pickup';
        $address = '';
        if ($err === '') {
            if ($deliveryMode === 'pickup') {
                $st = $pdo->prepare('SELECT address FROM stores WHERE id = ?');
                $st->execute([$selectedStore]);
                $address = (string)($st->fetchColumn() ?: 'Тверская, 12');
            } else {
                $address = $courierAddress;
                if ($address === '') {
                    $err = 'Укажите адрес доставки';
                }
            }
        }

        if ($err === '') {
            if ($promoCode !== '') {
                $stP = $pdo->prepare('SELECT * FROM promos WHERE UPPER(code) = ? AND active = 1');
                $stP->execute([$promoCode]);
                $promo = $stP->fetch();
                if ($promo) {
                    $discount = round($subtotal * ((float)$promo['discount_percent'] / 100), 2);
                } else {
                    $err = 'Промокод не найден или неактивен';
                }
            }
        }

        if ($err === '') {
            $afterDiscount = max(0.0, $subtotal - $discount);
            $deliveryCost = ($deliveryMode === 'courier' && $afterDiscount < FREE_DELIVERY_FROM) ? DELIVERY_COST : 0.0;
            $total = $afterDiscount + $deliveryCost;
            $today = date('Y-m-d');
            $rentTo = date('Y-m-d', strtotime('+' . RENT_DAYS . ' days'));

            $pdo->beginTransaction();
            try {
                $stOrd = $pdo->prepare("INSERT INTO orders (user_id, status, delivery, delivery_status, address, subtotal, discount, delivery_cost, total, created_at)
                                      VALUES (?, 'paid', ?, 'pending', ?, ?, ?, ?, ?, NOW())");
                $stOrd->execute([(int)$user['id'], $deliveryMode, $address, $subtotal, $discount, $deliveryCost, $total]);
                $orderId = (int)$pdo->lastInsertId();

                $stItem = $pdo->prepare("INSERT INTO order_items (order_id, album_id, type, qty, price, rent_from, rent_to)
                                       VALUES (?, ?, ?, 1, ?, ?, ?)");
                $stStock = $pdo->prepare("UPDATE albums SET stock = GREATEST(0, stock - 1) WHERE id = ? AND stock >= 1");

                foreach ($items as $it) {
                    $aid = (int)$it['album']['id'];
                    $stStock->execute([$aid]);
                    $isRent = $it['type'] === 'rent';
                    $stItem->execute([
                        $orderId,
                        $aid,
                        $it['type'],
                        $it['sum'],
                        $isRent ? $today : null,
                        $isRent ? $rentTo : null,
                    ]);
                }

                $stSum = $pdo->prepare('SELECT COALESCE(SUM(price), 0) FROM order_items WHERE order_id = ?');
                $stSum->execute([$orderId]);
                $actualSubtotal = (float)$stSum->fetchColumn();
                $actualAfterDiscount = max(0.0, $actualSubtotal - $discount);
                $actualDelivery = ($deliveryMode === 'courier' && $actualAfterDiscount < FREE_DELIVERY_FROM) ? DELIVERY_COST : 0.0;
                $actualTotal = $actualAfterDiscount + $actualDelivery;

                $pdo->prepare('UPDATE orders SET subtotal = ?, delivery_cost = ?, total = ? WHERE id = ?')
                    ->execute([$actualSubtotal, $actualDelivery, $actualTotal, $orderId]);

                $pdo->commit();
                cart_clear();
                log_action($pdo, (int)$user['id'], 'order_create', 'order', (string)$orderId, 'Оформлен заказ #' . $orderId . ' на сумму ' . $actualTotal . ' ₽');
                flash_set('ok', 'Заказ #' . $orderId . ' успешно оформлен');
                header('Location: cabinet.php');
                exit;
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $err = 'Ошибка оформления заказа: ' . $ex->getMessage();
            }
        }
    }
}

$afterDiscountPreview = max(0.0, $subtotal - $discount);
$deliveryPreview = ($deliveryMode === 'courier' && $afterDiscountPreview < FREE_DELIVERY_FROM) ? DELIVERY_COST : 0.0;
$totalPreview = $afterDiscountPreview + $deliveryPreview;

$stores = $pdo->query('SELECT * FROM stores ORDER BY id')->fetchAll();
require 'header.php';
?>
<main class="wrap-cart">
  <div>
    <?php if ($err !== ''): ?>
      <div class="err"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <p class="empty">Ваша корзина пуста. <a href="index.php">Перейти в каталог</a></p>
    <?php else: ?>
      <?php foreach ($items as $it): $a = $it['album']; $grad = 'g' . ($it['idx'] % 4); ?>
        <div class="line">
          <div class="thumb <?= $grad ?>"></div>
          <div class="mid">
            <div class="typeswitch">
              <form method="post" action="cart_action.php" class="inline">
                <input type="hidden" name="album_id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="action" value="set_type">
                <input type="hidden" name="type" value="purchase">
                <button type="submit" class="chip <?= $it['type'] === 'purchase' ? 'on' : '' ?>">Покупка</button>
              </form>
              <form method="post" action="cart_action.php" class="inline">
                <input type="hidden" name="album_id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="action" value="set_type">
                <input type="hidden" name="type" value="rent">
                <button type="submit" class="chip <?= $it['type'] === 'rent' ? 'on' : '' ?>">Аренда <?= RENT_DAYS ?> дн.</button>
              </form>
            </div>
            <b title="<?= e($a['artist'] . ' — ' . $a['title']) ?>"><?= e($a['artist']) ?> — <?= e($a['title']) ?></b>
          </div>
          <span class="dates">в наличии: <?= (int)$a['stock'] ?> шт.</span>
          <span class="price"><?= money($it['sum']) ?></span>
          <form method="post" action="cart_action.php" class="inline">
            <input type="hidden" name="album_id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="type" value="<?= e($it['type']) ?>">
            <input type="hidden" name="action" value="remove">
            <button type="submit" class="del" title="Удалить">×</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($items): ?>
    <form method="post" class="summary">
      <h1>Корзина</h1>

      <div class="switch">
        <label>
          <input type="radio" name="delivery" value="pickup" <?= $deliveryMode === 'pickup' ? 'checked' : '' ?>>
          <span>Самовывоз</span>
        </label>
        <label>
          <input type="radio" name="delivery" value="courier" <?= $deliveryMode === 'courier' ? 'checked' : '' ?>>
          <span>Курьер</span>
        </label>
      </div>

      <div id="pickupBox" style="<?= $deliveryMode === 'courier' ? 'display:none' : '' ?>">
        <div class="sumlabel ruled">Пункт выдачи</div>
        <div class="selwrap">
          <select name="store">
            <?php foreach ($stores as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $selectedStore === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['address']) ?> (<?= e($s['hours']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="courierBox" style="<?= $deliveryMode === 'courier' ? '' : 'display:none' ?>">
        <div class="sumlabel ruled">Адрес доставки</div>
        <input class="addr" name="address" value="<?= e($courierAddress) ?>" placeholder="Город, улица, дом, квартира">
      </div>

      <div class="sumlabel ruled">Промокод</div>
      <input class="addr" name="promo" value="<?= e($promoCode) ?>" placeholder="Например, VINYL10">

      <div class="sumrow"><span>Подытог</span><b><?= money($subtotal) ?></b></div>
      <?php if ($discount > 0): ?>
        <div class="sumrow"><span>Скидка</span><b>−<?= money($discount) ?></b></div>
      <?php endif; ?>
      <div class="sumrow"><span>Доставка</span><b><?= $deliveryPreview > 0 ? money($deliveryPreview) : '0 ₽' ?></b></div>
      <div class="sumtotal"><span>Итого</span><b><?= money($totalPreview) ?></b></div>

      <?php if ($user): ?>
        <button class="paybtn" type="submit">Оплатить заказ</button>
      <?php else: ?>
        <a class="paybtn" href="login.php">Войти для заказа</a>
      <?php endif; ?>
    </form>

    <script>
      (function () {
        var radios = document.querySelectorAll('input[name="delivery"]');
        var pBox = document.getElementById('pickupBox');
        var cBox = document.getElementById('courierBox');
        if (!radios.length || !pBox || !cBox) return;
        function toggleBoxes() {
          var checked = document.querySelector('input[name="delivery"]:checked');
          var isCourier = checked && checked.value === 'courier';
          pBox.style.display = isCourier ? 'none' : '';
          cBox.style.display = isCourier ? '' : 'none';
        }
        radios.forEach(function (r) { r.addEventListener('change', toggleBoxes); });
        toggleBoxes();
      })();
    </script>
  <?php endif; ?>
</main>
<?php require 'footer.php'; ?>