<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

define('RENT_DAYS', 7);
define('FINE_PER_DAY', 50);
define('DELIVERY_COST', 390);
define('FREE_DELIVERY_FROM', 3000);
define('MAX_FAILED_ATTEMPTS', 3);

define('DB_HOST', 'MySQL-8.0');
define('DB_NAME', 'songs_vnl');
define('DB_USER', 'root');
define('DB_PASS', '');
define('AUDIO_DIR', 'audio');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $ex) {
    exit('<h2>Нет подключения к MySQL</h2><pre>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>');
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float|int|string $v): string {
    return str_replace(' ', '&nbsp;', number_format((float)$v, 0, '.', ' ')) . '&nbsp;₽';
}

function current_user(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}

function flash_set(string $type, string $text): void {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function flash_get(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function cart(): array {
    return $_SESSION['cart'] ?? [];
}

function in_cart(int $id): bool {
    foreach (cart() as $it) {
        if ((int)$it['album_id'] === $id) return true;
    }
    return false;
}

function in_cart_type(int $id, string $type): bool {
    foreach (cart() as $it) {
        if ((int)$it['album_id'] === $id && $it['type'] === $type) return true;
    }
    return false;
}

function cart_add(int $id, string $type): void {
    if (in_cart_type($id, $type)) return;
    $_SESSION['cart'][] = ['album_id' => $id, 'type' => $type];
}

function cart_remove(int $id, string $type): void {
    $_SESSION['cart'] = array_values(array_filter(
        cart(),
        fn($it) => !((int)$it['album_id'] === $id && $it['type'] === $type)
    ));
}

function cart_set_type(int $id, string $type): void {
    foreach ($_SESSION['cart'] ?? [] as &$it) {
        if ((int)$it['album_id'] === $id) $it['type'] = $type;
    }
    unset($it);
}

function cart_clear(): void {
    $_SESSION['cart'] = [];
}

function cover_src(array $a): ?string {
    if (!empty($a['cover']) && is_string($a['cover']) && is_file($a['cover'])) return $a['cover'];
    return null;
}

function audio_src(array $a): ?string {
    if (!empty($a['audio']) && is_string($a['audio']) && is_file($a['audio'])) return $a['audio'];
    return null;
}

function captcha_image(): ?string {
    foreach (['png', 'jpg', 'jpeg'] as $ext) {
        $f = 'images/captcha.' . $ext;
        if (is_file($f)) return $f;
    }
    return null;
}

function captcha_init(): array {
    $show = [1, 2, 3, 4];
    shuffle($show);
    $_SESSION['captcha'] = $show;
    return $show;
}

function captcha_html(array $show): string {
    $w = 88; $h = 89;
    $tilePos = [[50, 36], [150, 48], [50, 137], [150, 149]];
    $slotPos = [[262, 36], [362, 36], [262, 137], [362, 137]];
    $img = captcha_image();
    $bg = [];
    for ($p = 1; $p <= 4; $p++) {
        if ($img) {
            $x = -(($p - 1) % 2) * $w;
            $y = -intdiv($p - 1, 2) * $h;
            $bg[$p] = 'background-image:url(' . $img . ');background-size:' . ($w * 2) . 'px ' . ($h * 2) .
                      'px;background-position:' . $x . 'px ' . $y . 'px;background-repeat:no-repeat';
        } else {
            $bg[$p] = 'background:' . [
                1 => 'linear-gradient(153deg,#51D4C9 0%,#6C7D54 100%)',
                2 => 'linear-gradient(147deg,#51D4C9 0%,#AD473F 100%)',
                3 => 'linear-gradient(207deg,#6C7D54 0%,#AD473F 100%)',
                4 => 'linear-gradient(123deg,#6C7D54 0%,#51D4C9 100%)'][$p];
        }
    }
    $out = '<div class="captchabox">';
    foreach ($show as $i => $part) {
        $out .= '<button type="button" class="pz" data-part="' . $part .
                '" style="left:' . $tilePos[$i][0] . 'px;top:' . $tilePos[$i][1] . 'px;' . $bg[$part] . '"></button>';
    }
    foreach ($slotPos as $i => $p) {
        $out .= '<div class="slot" data-slot="' . $i . '" style="left:' . $p[0] . 'px;top:' . $p[1] . 'px"></div>';
    }
    $out .= '<button type="button" class="capbtn">проверить</button>';
    $out .= '<input type="hidden" name="captcha" value=""></div>';
    $out .= <<<JS
<script>
(function () {
  document.querySelectorAll(".captchabox").forEach(function (box) {
    var tiles  = Array.prototype.slice.call(box.querySelectorAll(".pz"));
    var slots  = Array.prototype.slice.call(box.querySelectorAll(".slot"));
    var input  = box.querySelector("input[name=captcha]");
    var btn    = box.querySelector(".capbtn");
    var placed = [null, null, null, null];
    var locked = false;

    function tileOf(p) { return box.querySelector('.pz[data-part="' + p + '"]'); }
    function sync() {
      var full = placed.every(function (p) { return p !== null; });
      input.value = full ? placed.join(",") : "";
      return full;
    }
    function reset() {
      placed = [null, null, null, null];
      slots.forEach(function (s) { s.innerHTML = ""; });
      tiles.forEach(function (t) { t.classList.remove("used"); });
      sync();
    }
    function flash(text) {
      btn.classList.add("bad");
      btn.textContent = text;
      setTimeout(function () {
        if (locked) return;
        btn.classList.remove("bad");
        btn.textContent = "проверить";
      }, 1200);
    }

    tiles.forEach(function (t) {
      t.addEventListener("click", function () {
        if (locked || t.classList.contains("used")) return;
        var i = placed.indexOf(null);
        if (i === -1) return;
        placed[i] = t.getAttribute("data-part");
        t.classList.add("used");
        var f = document.createElement("div");
        f.className = "pzfill";
        f.style.backgroundImage = t.style.backgroundImage;
        f.style.backgroundSize = t.style.backgroundSize;
        f.style.backgroundPosition = t.style.backgroundPosition;
        f.style.backgroundRepeat = "no-repeat";
        slots[i].appendChild(f);
        sync();
      });
    });

    slots.forEach(function (s, i) {
      s.addEventListener("click", function () {
        if (locked || placed[i] === null) return;
        var p = placed[i];
        placed[i] = null;
        s.innerHTML = "";
        tileOf(p).classList.remove("used");
        sync();
      });
    });

    btn.addEventListener("click", function () {
      if (locked) return;
      if (!sync()) { flash("собери все 4"); return; }
      if (placed.join(",") === "1,2,3,4") {
        locked = true;
        box.classList.add("locked");
        btn.classList.remove("bad");
        btn.classList.add("ok");
        btn.textContent = "проверено";
        input.value = "1,2,3,4";
      } else {
        flash("неверно, собери снова");
        setTimeout(reset, 900);
      }
    });
  });
})();
</script>
JS;
    return $out;
}

function captcha_check(?string $answer): bool {
    if (empty($_SESSION['captcha'])) return false;
    unset($_SESSION['captcha']);
    return explode(',', trim($answer ?? '')) === ['1', '2', '3', '4'];
}

function password_errors(string $p): array {
    $err = [];
    if (mb_strlen($p) < 6)                 $err[] = '≥ 6 символов';
    if (!preg_match('/[A-Z]/', $p))        $err[] = 'A–Z';
    if (!preg_match('/[a-z]/', $p))        $err[] = 'a–z';
    if (!preg_match('/[0-9]/', $p))        $err[] = '0–9';
    if (!preg_match('/[^A-Za-z0-9]/', $p)) $err[] = 'спецсимвол';
    return $err;
}