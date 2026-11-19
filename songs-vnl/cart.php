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
    foreach (cart() as $it) {
        $a = $byId[(int)$it['album_id']] ?? null;
        if (!$a) continue;
        $type = $it['type'] === 'rent' ? 'rent' : 'purchase';
        $sum = $type === 'rent' ? (float)$a['rent_price'] * RENT_DAYS : (float)$a['price'];
        $items[] = [
            'album' => $a,
            'type'  => $type,
            'sum'   => $sum,
        ];
    }
}

$subtotal = array_sum(array_column($items, 'sum'));
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $items) {
    foreach ($items as $it) {
        if ((int)$it['album']['stock'] < 1) {
            $err = 'Альбом «' . $it['album']['title'] . '» закончился на складе';
            break;
        }
    }

    $delivery = ($_POST['delivery'] ?? 'pickup') === 'courier' ? 'courier' : 'pickup';
    $address = '';
    if ($err === '') {
        if ($delivery === 'pickup') {
            $st = $pdo->prepare('SELECT address FROM stores WHERE id = ?');
            $st->execute([(int)($_POST['store'] ?? 1)]);
            $address = (string)($st->fetchColumn() ?: 'Тверская, 12');
        } else {
            $address = trim($_POST['address'] ?? '');
            if ($address === '') {
                $err = 'Укажите адрес доставки';
            }
        }
    }

    if ($err === '') {
        $deliveryCost = $delivery === 'pickup' ? 0 : ($subtotal >= FREE_DELIVERY_FROM ? 0 : DELIVERY_COST);
        $total = $subtotal + $deliveryCost;
        $hasRent = in_array('rent', array_column($items, 'type'), true);

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO orders (user_id,status,delivery,address,subtotal,discount,delivery_cost,total) VALUES (?,?,?,?,?,0,?,?)');
            $st->execute([$user['id'], $hasRent ? 'issued' : 'paid', $delivery, $address, $subtotal, $deliveryCost, $total]);
            $orderId = (int)$pdo->lastInsertId();

            $from = date('Y-m-d');
            $to = date('Y-m-d', strtotime('+' . RENT_DAYS . ' days'));
            $insItem = $pdo->prepare('INSERT INTO order_items (order_id,album_id,type,price,qty,rent_from,rent_to) VALUES (?,?,?,?,1,?,?)');

            foreach ($items as $it) {
                $isRent = $it['type'] === 'rent';
                $unitOrDayPrice = $isRent ? (float)$it['album']['rent_price'] : (float)$it['album']['price'];
                $insItem->execute([
                    $orderId,
                    (int)$it['album']['id'],
                    $it['type'],
                    $unitOrDayPrice,
                    $isRent ? $from : null,
                    $isRent ? $to : null,
                ]);
            }

            $pdo->commit();
            cart_clear();
            flash_set('ok', 'Заказ успешно оформлен');
            header('Location: cabinet.php');
            exit;
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $err = 'Ошибка оформления заказа: ' . $ex->getMessage();
        }
    }
}

require 'header.php';
$rentFrom = date('d.m.Y');
$rentTo = date('d.m.Y', strtotime('+' . RENT_DAYS . ' days'));
?>
<main class="wrap-cart">
  <div>
    <?php if ($err !== ''): ?>
      <p class="err"><?= e($err) ?></p>
    <?php endif; ?>

    <?php if (!$items): ?>
      <p class="empty">Корзина пуста. <a href="index.php" style="color:var(--teal)">В каталог</a>.</p>
    <?php else: foreach ($items as $i => $it): $cv = cover_src($it['album']); ?>
      <div class="line">
        <?php if ($cv): ?><img class="thumb" src="<?= e($cv) ?>" alt="" style="object-fit:cover">
        <?php else: ?><div class="thumb g<?= $i % 4 ?>"></div><?php endif; ?>
        <div class="mid">
          <form method="post" action="cart_action.php" class="typeswitch" title="сменить тип">
            <input type="hidden" name="album_id" value="<?= (int)$it['album']['id'] ?>">
            <input type="hidden" name="action" value="set_type">
            <button class="chip<?= $it['type'] === 'purchase' ? ' on' : '' ?>" name="type" value="purchase">покупка</button>
            <button class="chip<?= $it['type'] === 'rent' ? ' on' : '' ?>" name="type" value="rent">аренда</button>
          </form>
          <b><?= e($it['album']['artist']) ?> - <?= e($it['album']['title']) ?></b>
        </div>
        <?php if ($it['type'] === 'rent'): ?><span class="dates">аренда с <?= $rentFrom ?> по <?= $rentTo ?></span><?php endif; ?>
        <span class="price"><?= money($it['sum']) ?></span>
        <form method="post" action="cart_action.php">
          <input type="hidden" name="album_id" value="<?= (int)$it['album']['id'] ?>">
          <input type="hidden" name="type" value="<?= e($it['type']) ?>">
          <input type="hidden" name="action" value="remove">
          <button class="del" title="убрать"><img src="images/cross.png" alt="×"></button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <aside class="summary">
    <h1>Оформление заказа</h1>
    <?php if ($items): ?>
    <form method="post" id="checkout">
      <div class="switch">
        <label><input type="radio" name="delivery" value="pickup" checked onchange="toggleDeliver()">Самовывоз</label>
        <label><input type="radio" name="delivery" value="courier" onchange="toggleDeliver()">Курьер</label>
      </div>
      <div class="sumlabel ruled">Адрес магазина</div>
      <div id="pickupBox">
        <div class="selwrap">
          <select name="store" onchange="showAddr(this)">
            <?php foreach ($pdo->query('SELECT * FROM stores ORDER BY id') as $s): ?>
              <option value="<?= (int)$s['id'] ?>" data-addr="<?= e($s['address']) ?>">Магазин “Songs.vnl”</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="addr" id="storeAddr"></div>
        <div class="hint">Выберите магазин самовывоза — адрес подставится автоматически</div>
      </div>
      <div id="courierBox" hidden>
        <input class="addr" name="address" placeholder="Москва, ул. Пролетарская, 14">
        <div class="hint">Город, улица, дом, квартира — куда привезти заказ</div>
      </div>
      <div class="sumlabel">Оплата</div>
      <div class="sumrow"><span>Подытог</span><b><?= money($subtotal) ?></b></div>
      <div class="sumrow"><span>Скидка</span><b>0 ₽</b></div>
      <div class="sumrow"><span>Доставка</span><b id="delivLabel">Бесплатно</b></div>
      <div class="sumlabel">Итог</div>
      <div class="sumtotal"><span>К оплате</span><b id="totalLabel"><?= money($subtotal) ?></b></div>
      <?php if ($user): ?><button class="paybtn" type="submit">Оплатить</button>
      <?php else: ?><a class="paybtn" href="login.php">Оплатить</a><?php endif; ?>
    </form>
    <?php endif; ?>
  </aside>
</main>
<script>
function showAddr(sel){ var el = document.getElementById('storeAddr'); if (el && sel.selectedOptions[0]) el.textContent = sel.selectedOptions[0].dataset.addr; }
var storeSel = document.querySelector('select[name=store]');
if (storeSel) showAddr(storeSel);
function toggleDeliver(){
  var c = document.querySelector('input[name=delivery]:checked').value === 'courier';
  document.getElementById('pickupBox').hidden = c;
  document.getElementById('courierBox').hidden = !c;
  var cost = (!c || <?= (int)$subtotal ?> >= <?= FREE_DELIVERY_FROM ?>) ? 0 : <?= DELIVERY_COST ?>;
  document.getElementById('delivLabel').textContent = cost ? cost + ' ₽' : 'Бесплатно';
  document.getElementById('totalLabel').textContent = (<?= (int)$subtotal ?> + cost).toLocaleString('ru-RU') + ' ₽';
}
</script>
<?php require 'footer.php'; ?>