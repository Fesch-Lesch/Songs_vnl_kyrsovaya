<?php
require 'config.php';
$page = 'auth';
$error = '';
$doneMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginOrEmail = trim($_POST['login'] ?? '');
    $newPass = $_POST['new_pass'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!captcha_check($_POST['captcha'] ?? null)) {
        $error = 'Пазл собран неверно. Попробуйте ещё раз';
    } elseif (($errs = password_errors($newPass))) {
        $error = 'Слабый пароль: ' . implode(', ', $errs);
    } elseif ($newPass !== $confirm) {
        $error = 'Пароли не совпадают';
    } else {
        $st = $pdo->prepare('SELECT * FROM users WHERE login = ? OR email = ?');
        $st->execute([$loginOrEmail, $loginOrEmail]);
        $u = $st->fetch();
        if (!$u) {
            $error = 'Пользователь не найден';
        } elseif (is_password_reused($pdo, (int)$u['id'], $newPass)) {
            $error = 'Нельзя использовать один из последних 5 паролей';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ?, failed_attempts = 0, is_blocked = 0 WHERE id = ?')
                ->execute([$hash, (int)$u['id']]);
            record_password_history($pdo, (int)$u['id'], $hash);
            log_action($pdo, (int)$u['id'], 'password_reset', 'user', (string)$u['id'], 'Восстановление пароля');
            $doneMsg = 'Пароль успешно обновлён. Теперь вы можете войти.';
        }
    }
}

$show = captcha_init();
require 'header.php';
?>
<form class="auth" method="post">
  <div class="band"><h1>Восстановление пароля</h1></div>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <?php if ($doneMsg): ?><p class="ok"><?= e($doneMsg) ?></p><?php endif; ?>
  <label>логин или почта</label>
  <input name="login" required placeholder="something@example.com">
  <label>новый пароль</label>
  <input type="password" name="new_pass" required>
  <label>повторите пароль</label>
  <input type="password" name="confirm" required>
  <label>соберите паззл-капчу</label>
  <?= captcha_html($show) ?>
  <button class="submit" type="submit">Сменить пароль</button>
  <a class="alt" href="login.php">Вернуться ко входу</a>
</form>
<?php require 'footer.php'; ?>