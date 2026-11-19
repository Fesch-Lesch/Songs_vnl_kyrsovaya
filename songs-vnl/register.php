<?php
require 'config.php';
$page = 'auth';
$error = '';
$loginVal = '';
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginVal = trim($_POST['login'] ?? '');
    $emailVal = trim($_POST['email'] ?? '');
    $pass     = $_POST['pass'] ?? '';
    $pass2    = $_POST['pass2'] ?? '';

    if (!captcha_check($_POST['captcha'] ?? null)) {
        $error = 'Пазл собран неверно. Попробуйте ещё раз';
    } elseif (($err = password_errors($pass))) {
        $error = 'Пароль не соответствует: ' . implode(', ', $err);
    } elseif ($pass !== $pass2) {
        $error = 'Пароли не совпадают';
    } else {
        $st = $pdo->prepare('SELECT id FROM users WHERE login = ? OR email = ?');
        $st->execute([$loginVal, $emailVal]);
        if ($st->fetch()) {
            $error = 'Логин или почта уже заняты';
        } else {
            $pdo->prepare('INSERT INTO users (login, email, password_hash, role) VALUES (?, ?, ?, "client")')
                ->execute([$loginVal, $emailVal, password_hash($pass, PASSWORD_DEFAULT)]);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            flash_set('ok', 'Вы успешно авторизовались');
            header('Location: cabinet.php');
            exit;
        }
    }
}

$show = captcha_init();
require 'header.php';
?>
<form class="auth" method="post">
  <div class="band"><h1>Зарегистрироваться</h1></div>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <label>логин</label>
  <input name="login" value="<?= e($loginVal) ?>" placeholder="something" required>
  <label>почта</label>
  <input type="email" name="email" value="<?= e($emailVal) ?>" placeholder="something@example.com" required>
  <label>пароль</label>
  <input type="password" name="pass" id="pass" placeholder="**********" required>
  <div class="req">
    <span id="r1">✓ ≥ 6 символов</span><span id="r2">✓ A–Z</span><span id="r3">✓ a–z</span>
    <span id="r4">✓ 0–9</span><span id="r5">спецсимвол</span>
  </div>
  <label>повторите пароль</label>
  <input type="password" name="pass2" placeholder="**********" required>
  <label>соберите паззл-капчу</label>
  <?= captcha_html($show) ?>
  <button class="submit" type="submit">Зарегистрироваться</button>
</form>
<script>
var rules = [[/^.{6,}$/s,'r1'],[/[A-Z]/,'r2'],[/[a-z]/,'r3'],[/[0-9]/,'r4'],[/[^A-Za-z0-9]/,'r5']];
var p = document.getElementById('pass');
p.oninput = function(){ rules.forEach(function(r){ document.getElementById(r[1]).classList.toggle('ok', r[0].test(p.value)); }); };
</script>
<?php require 'footer.php'; ?>