<?php
$user = current_user($pdo);
$cartCount = count(cart());
$page = $page ?? 'catalog';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>SONGS.VNL — продажа и аренда CD/DVD/винила</title>
<link href="https://fonts.googleapis.com/css2?family=Alumni+Sans+SC:wght@400;700&family=Irish+Grover&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php if ($page === 'auth'): ?>
<div class="authwrap">
  <div class="auth-logo"><img src="images/logo.png" alt=""><span>songs.vnl</span></div>
<?php else: ?>
<header class="top">
  <a class="logo" href="index.php"><img src="images/logo.png" alt=""><span>songs.vnl</span></a>
  <nav>
    <?php if ($page === 'catalog'): ?>
      <a class="pill outline" href="index.php"><span><b>каталог</b></span></a>
    <?php else: ?>
      <a class="pill solid" href="index.php"><span>каталог</span></a>
    <?php endif; ?>

    <?php if ($page === 'cart'): ?>
      <a class="pill icon outline" href="cart.php"><span><img src="images/cart_image_gradient.png" alt=""><?= $cartCount ? '<b>' . $cartCount . '</b>' : '' ?></span></a>
    <?php else: ?>
      <a class="pill icon solid" href="cart.php"><img src="images/cart_image.png" alt=""><?= $cartCount ? '<b>' . $cartCount . '</b>' : '' ?></a>
    <?php endif; ?>

    <?php if ($user): ?>
      <?php if ($page === 'cabinet'): ?>
        <a class="pill outline" href="cabinet.php"><span><b>кабинет</b></span></a>
      <?php else: ?>
        <a class="pill solid" href="cabinet.php"><span>кабинет</span></a>
      <?php endif; ?>
    <?php else: ?>
      <a class="pill solid" href="login.php"><span>войти</span></a>
    <?php endif; ?>

    <?php if ($user && $user['role'] === 'admin'): ?>
      <?php if ($page === 'admin'): ?>
        <a class="pill outline" href="admin.php"><span><b>админ</b></span></a>
      <?php else: ?>
        <a class="pill solid" href="admin.php"><span>админ</span></a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($user): ?>
      <a class="logout" href="?logout=1" title="выйти"><img src="images/cross.png" alt="выйти"></a>
    <?php endif; ?>
  </nav>
</header>
<?php endif; ?>