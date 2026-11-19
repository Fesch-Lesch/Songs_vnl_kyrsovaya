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
    } else {
        $captchaOk = captcha_check($_POST['captcha'] ?? null);
        $passOk    = $u && password_verify($_POST['password'] ?? '', $u['password_hash']);

        if ($captchaOk && $passOk) {
            $pdo->prepare('UPDATE users SET failed_attempts = 0 WHERE id = ?')->execute([(int)$u['id']]);
            $_SESSION['user_id'] = (int)$u['id'];
            flash_set('ok', 'Вы успешно авторизовались');
            header('Location: cabinet.php');
            exit;
        }

        if ($u) {
            $fails = (int)$u['failed_attempts'] + 1;
            $block = $fails >= MAX_FAILED_ATTEMPTS ? 1 : 0;
            $pdo->prepare('UPDATE users SET failed_attempts = ?, is_blocked = ? WHERE id = ?')
                ->execute([$fails, $block, (int)$u['id']]);
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
  <input type="password" name="password" placeholder="**********" required>
  <div class="hint">После 3 неудачных попыток учётная запись блокируется</div>
  <label>соберите паззл-капчу</label>
  <?= captcha_html($show) ?>
  <button class="submit" type="submit">Войти</button>
  <a class="alt" href="register.php">Зарегистрироваться</a>
</form>
<?php require 'footer.php'; ?>