<?php if (($page ?? '') === 'auth'): ?>
</div>
<?php else: ?>
<footer>
  <div>
    <h4>songs.vnl</h4>
    <p>АС магазина аудио- и видеодисков: продажа и аренда CD, DVD и винила. Работаем 24/7.</p>
    <p class="copy">© 2026 АС «Прокат дисков» · CD / DVD / Vinyl</p>
  </div>
  <div>
    <h4>контакты</h4>
    <p>+7 (495) 123-45-67<br>hello@prokat-diskov.ru<br>Доставка 390 ₽ - от 3000 ₽ бесплатно</p>
  </div>
  <div>
    <h4>магазины самовывоза</h4>
    <?php foreach ($pdo->query('SELECT * FROM stores ORDER BY id') as $s): ?>
      <p><?= e($s['address']) ?> - <?= e($s['hours']) ?></p>
    <?php endforeach; ?>
  </div>
</footer>
<?php endif; ?>

<?php
$flash = flash_get();
if (!$flash) {
    if (!empty($err))     $flash = ['type' => 'err', 'text' => $err];
    elseif (!empty($msg)) $flash = ['type' => 'ok',  'text' => $msg];
}
?>
<?php if ($flash): ?>
<div class="alertwrap">
  <div class="alertbox">
    <div class="band <?= $flash['type'] === 'ok' ? '' : 'bad' ?>">
      <h1><?= $flash['type'] === 'ok' ? 'Успех' : 'Ошибка' ?></h1>
    </div>
    <div class="alertbody">
      <div class="aicon <?= $flash['type'] === 'ok' ? 'ok' : 'bad' ?>"><?= $flash['type'] === 'ok' ? '✓' : '!' ?></div>
      <p><?= e($flash['text']) ?></p>
      <button class="btn wide" onclick="this.closest('.alertwrap').remove()">ОК</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (($page ?? '') !== 'auth' && current_user($pdo)): ?>
<div class="alertwrap hidden" id="logoutmodal">
  <div class="alertbox">
    <div class="band"><h1>Вы уверены, что хотите выйти из аккаунта?</h1></div>
    <div class="alertbody cols2">
      <button class="btn wide no" onclick="document.getElementById('logoutmodal').classList.add('hidden')">Нет (вернуться)</button>
      <a class="btn wide yes" href="?logout=1">Да (выйти)</a>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('a.logout').forEach(function (a) {
  a.addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('logoutmodal').classList.remove('hidden');
  });
});
</script>
<?php endif; ?>
</body></html>