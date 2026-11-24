<?php
require 'config.php';
$page = 'auth';
$error = '';
$loginVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginVal = trim($_POST['login'] ?? '');
    $st = $pdo->prepare('SELECT * FROM users WHERE login = ? OR email = ?');
    $st->execute([$loginVal, $loginVal]);
    $u = $st->fetch();

    if ($u && (int)$u['is_blocked'] === 1) {
        $error = 'Вы заблокированы. Обратитесь к администратору';
        log_action($pdo, (int)$u['id'], 'login_blocked', 'user', (string)$u['id'], 'Попытка входа в заблокированный аккаунт');
    } else {
        $captchaOk = captcha_check($_POST['captcha'] ?? null);
        $passOk    = $u && password_verify($_POST['password'] ?? '', $u['password_hash']);

        if ($captchaOk && $passOk) {
            $pdo->prepare('UPDATE users SET failed_attempts = 0 WHERE id = ?')->execute([(int)$u['id']]);
            $_SESSION['user_id'] = (int)$u['id'];
            log_action($pdo, (int)$u['id'], 'login_success', 'user', (string)$u['id'], 'Успешный вход');
            flash_set('ok', 'Вы успешно авторизовались');
            header('Location: cabinet.php');
            exit;
        }

        if ($u) {
            $fails = (int)$u['failed_attempts'] + 1;
            $block = $fails >= MAX_FAILED_ATTEMPTS ? 1 : 0;
            $pdo->prepare('UPDATE users SET failed_attempts = ?, is_blocked = ? WHERE id = ?')
                ->execute([$fails, $block, (int)$u['id']]);
            sleep(min($fails, 4));
            log_action($pdo, (int)$u['id'], 'login_fail', 'user', (string)$u['id'], "Неудачный вход (#$fails)");
            if ($block) {
                $error = 'Вы заблокированы. Обратитесь к администратору';
            }
        }
        if ($error === '') {
            $error = !$captchaOk
                ? 'Пазл собран неверно. Попробуйте ещё раз'
                : 'Вы ввели неверный логин или пароль. Пожалуйста проверьте ещё раз введенные данные';
        }
    }
}

$show = captcha_init();
require 'header.php';
?>
<form class="auth" method="post">
  <div class="band"><h1>Войдите, чтобы продолжить</h1></div>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <label>логин / почта</label>
  <input name="login" value="<?= e($loginVal) ?>" placeholder="something@example.com" required>
  <div class="hint">Логин или почта, указанные при регистрации</div>
  <label>пароль</label>
  <input type="password" name="password" id="password" placeholder="**********" required>
  <div class="req">
    <span id="lr1">✓ ≥ 6 символов</span>
    <span id="lr2">✓ A–Z</span>
    <span id="lr3">✓ a–z</span>
    <span id="lr4">✓ 0–9</span>
    <span id="lr5">✓ спецсимвол</span>
  </div>
  <div class="hint">После 3 неудачных попыток учётная запись блокируется</div>
  <label>соберите паззл-капчу</label>
  <?= captcha_html($show) ?>
  <button class="submit" type="submit">Войти</button>
  <a class="alt" href="register.php">Зарегистрироваться</a>
  <a class="alt" href="reset_password.php">Забыли пароль?</a>
</form>
<script>
(function () {
  var rules = [
    [/^.{6,}$/s, 'lr1'],
    [/[A-Z]/, 'lr2'],
    [/[a-z]/, 'lr3'],
    [/[0-9]/, 'lr4'],
    [/[^A-Za-z0-9]/, 'lr5']
  ];
  var inp = document.getElementById('password');
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