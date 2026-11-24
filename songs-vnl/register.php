<?php
require 'config.php';
$page = 'auth';
$error = '';
$loginVal = '';
$emailVal = '';
$nameVal = '';
$phoneVal = '';
$addressVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginVal   = trim($_POST['login'] ?? '');
    $emailVal   = trim($_POST['email'] ?? '');
    $nameVal    = trim($_POST['name'] ?? '');
    $phoneVal   = trim($_POST['phone'] ?? '');
    $addressVal = trim($_POST['address'] ?? '');
    $pass       = $_POST['password'] ?? '';

    if ($loginVal === '' || $emailVal === '') {
        $error = 'Укажите логин и почту';
    } elseif (!filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный формат электронной почты';
    } elseif ($errs = password_errors($pass)) {
        $error = 'Пароль не соответствует требованиям: ' . implode(', ', $errs);
    } elseif (!captcha_check($_POST['captcha'] ?? null)) {
        $error = 'Паззл собран неверно. Попробуйте ещё раз';
    } else {
        $st = $pdo->prepare('SELECT id FROM users WHERE login = ? OR email = ?');
        $st->execute([$loginVal, $emailVal]);
        if ($st->fetch()) {
            $error = 'Пользователь с таким логином или почтой уже зарегистрирован';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (login, email, password_hash, role, name, phone, address, is_blocked, failed_attempts, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())')
                ->execute([
                    $loginVal,
                    $emailVal,
                    $hash,
                    'client',
                    encrypt_pii($nameVal),
                    encrypt_pii($phoneVal),
                    encrypt_pii($addressVal),
                ]);
            $newId = (int)$pdo->lastInsertId();
            record_password_history($pdo, $newId, $hash);
            $_SESSION['user_id'] = $newId;
            log_action($pdo, $newId, 'register', 'user', (string)$newId, 'Регистрация нового пользователя');
            flash_set('ok', 'Регистрация прошла успешно');
            header('Location: cabinet.php');
            exit;
        }
    }
}

$show = captcha_init();
require 'header.php';
?>
<form class="auth" method="post">
  <div class="band"><h1>Создайте аккаунт</h1></div>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <label>логин</label>
  <input name="login" value="<?= e($loginVal) ?>" placeholder="vinyl_lover" required>
  <label>почта</label>
  <input type="email" name="email" value="<?= e($emailVal) ?>" placeholder="something@example.com" required>
  <label>имя</label>
  <input name="name" value="<?= e($nameVal) ?>" placeholder="Иван Иванов">
  <label>телефон</label>
  <input name="phone" value="<?= e($phoneVal) ?>" placeholder="+7 (999) 123-45-67">
  <label>адрес</label>
  <input name="address" value="<?= e($addressVal) ?>" placeholder="Город, улица, дом">
  <label>пароль</label>
  <input type="password" name="password" id="regPassword" placeholder="**********" required>
  <div class="req">
    <span id="rr1">✓ ≥ 6 символов</span>
    <span id="rr2">✓ A–Z</span>
    <span id="rr3">✓ a–z</span>
    <span id="rr4">✓ 0–9</span>
    <span id="rr5">✓ спецсимвол</span>
  </div>
  <label>соберите паззл-капчу</label>
  <?= captcha_html($show) ?>
  <button class="submit" type="submit">Зарегистрироваться</button>
  <a class="alt" href="login.php">Уже есть аккаунт? Войти</a>
</form>
<script>
(function () {
  var rules = [
    [/^.{6,}$/s, 'rr1'],
    [/[A-Z]/, 'rr2'],
    [/[a-z]/, 'rr3'],
    [/[0-9]/, 'rr4'],
    [/[^A-Za-z0-9]/, 'rr5']
  ];
  var inp = document.getElementById('regPassword');
  if (!inp) return;
  function checkRules() {
    rules.forEach(function (r) {
      var el = document.getElementById(r[1]);
      if (el) el.classList.toggle('ok', r[0].test(inp.value));
    });
  }
  inp.addEventListener('input', checkRules);
  checkRules();
})();
</script>
<?php require 'footer.php'; ?>
