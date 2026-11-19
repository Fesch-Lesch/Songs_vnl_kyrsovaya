<?php
require 'config.php';

$login = 'admin';
$email = 'admin@songs.vnl';
$pass  = 'aDm1n';
$role  = 'admin';   // если нужен как в макете («client») — поменяйте на 'client'

$hash = password_hash($pass, PASSWORD_DEFAULT);

$st = $pdo->prepare('SELECT id FROM users WHERE login = ? OR email = ?');
$st->execute([$login, $email]);

if ($st->fetch()) {
    $pdo->prepare('UPDATE users SET password_hash = ?, role = ? WHERE login = ?')
        ->execute([$hash, $role, $login]);
    echo 'Готово: пароль пользователя <b>' . e($login) . '</b> обновлён на <b>' . e($pass) . '</b>';
} else {
    $pdo->prepare('INSERT INTO users (login, email, password_hash, role, name) VALUES (?,?,?,?,?)')
        ->execute([$login, $email, $hash, $role, 'Admin']);
    echo 'Готово: пользователь <b>' . e($login) . '</b> создан (пароль <b>' . e($pass) . '</b>)';
}