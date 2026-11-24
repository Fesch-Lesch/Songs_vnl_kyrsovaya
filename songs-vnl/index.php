<?php
require 'config.php';
$page = 'catalog';

/* ========== КАТАЛОГ: поиск, фильтры, сортировка ========== */
$q      = trim($_GET['q'] ?? '');
$format = $_GET['format'] ?? 'all';
$genre  = $_GET['genre'] ?? 'all';
$sort   = $_GET['sort'] ?? 'popular';
$stock  = isset($_GET['stock']);

$sql  = 'SELECT * FROM albums WHERE 1=1';
$args = [];
if ($q !== '') {
    $sql .= ' AND (title LIKE ? OR artist LIKE ? OR genre LIKE ?)';
    $args = ["%$q%", "%$q%", "%$q%"];
}
if ($format !== 'all') {
    $sql .= ' AND format = ?';
    $args[] = $format;
}
if ($genre !== 'all') {
    $sql .= ' AND genre = ?';
    $args[] = $genre;
}
if ($stock) {
    $sql .= ' AND stock > 0';
}

$sql .= match ($sort) {
    'new'   => ' ORDER BY year DESC, id DESC',
    'price' => ' ORDER BY price ASC, id ASC',
    default => ' ORDER BY popularity DESC, id ASC',
};

$st = $pdo->prepare($sql);
$st->execute($args);
$albums = $st->fetchAll();

$genres = $pdo->query('SELECT DISTINCT genre FROM albums WHERE genre IS NOT NULL AND genre <> "" ORDER BY genre')
    ->fetchAll(PDO::FETCH_COLUMN);

/* ========== Карточка альбома для модального окна ========== */
$modal = null;
$songs = [];
if (!empty($_GET['album'])) {
    $st = $pdo->prepare('SELECT * FROM albums WHERE id = ?');
    $st->execute([(int)$_GET['album']]);
    $modal = $st->fetch() ?: null;
    if ($modal) {
        $st = $pdo->prepare('SELECT * FROM songs WHERE album_id = ? ORDER BY side, position');
        $st->execute([(int)$modal['id']]);
        $songs = $st->fetchAll();
    }
}

$baseQuery = $_GET;
unset($baseQuery['album']);
$backUrl = 'index.php' . ($baseQuery ? '?' . http_build_query($baseQuery) : '');

require 'header.php';
?>
<main>
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Поиск: название, исполнитель, жанр...">
    <select name="format" onchange="this.form.submit()">
      <option value="all">все</option>
      <option value="vinil" <?= $format === 'vinil' ? 'selected' : '' ?>>винил</option>
      <option value="cd" <?= $format === 'cd' ? 'selected' : '' ?>>CD</option>
      <option value="dvd" <?= $format === 'dvd' ? 'selected' : '' ?>>DVD</option>
    </select>
    <select name="genre" onchange="this.form.submit()">
      <option value="all">все</option>
      <?php foreach ($genres as $g): ?>
        <option value="<?= e($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= e($g) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" onchange="this.form.submit()">
      <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>сначала популярные</option>
      <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>сначала новинки</option>
      <option value="price" <?= $sort === 'price' ? 'selected' : '' ?>>сначала дешёвые</option>
    </select>
    <label class="chk">
      <input type="checkbox" name="stock" <?= $stock ? 'checked' : '' ?> onchange="this.form.submit()"> в наличии
    </label>
  </form>

  <div class="grid">
    <?php foreach ($albums as $a):
      $cv = cover_src($a);
      $albumUrl = 'index.php?' . http_build_query(array_merge($baseQuery, ['album' => (int)$a['id']]));
    ?>
      <article class="card">
        <a class="cover" href="<?= e($albumUrl) ?>" title="<?= e($a['artist']) ?> - <?= e($a['title']) ?>">
          <div class="art art<?= (int)$a['id'] % 4 ?>">
            <?php if ($cv): ?><img class="artimg" src="<?= e($cv) ?>" alt=""><?php endif; ?>
            <span class="badge"><?= e($a['format']) ?></span>
            <span class="badge right"><?= e($a['genre']) ?></span>
          </div>
        </a>
        <h3><?= e($a['artist']) ?><b><?= e($a['title']) ?></b></h3>
        <div class="row">
          <div class="price"><small>покупка</small><?= money($a['price']) ?></div>
          <form method="post" action="cart_action.php">
            <input type="hidden" name="album_id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="type" value="purchase">
            <?php if (in_cart((int)$a['id'])): ?>
              <input type="hidden" name="action" value="remove">
              <button class="cartbtn incartbtn" title="убрать из корзины">в корзине</button>
            <?php else: ?>
              <button class="cartbtn" title="добавить в корзину">в корзину</button>
            <?php endif; ?>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$albums): ?>
      <p class="empty">Ничего не найдено. Сбрось фильтры или измени запрос.</p>
    <?php endif; ?>
  </div>
</main>

<?php if ($modal):
  $mcv = cover_src($modal);
  $mAudio = audio_src($modal);
?>
<div class="overlay" data-back="<?= e($backUrl) ?>">
  <div class="modal">
    <a class="close" href="<?= e($backUrl) ?>"><img src="images/cross.png" alt="закрыть"></a>

    <div class="m-left">
      <div class="m-cover">
        <img class="disc" src="images/vinil.png" alt="">
        <div class="art art<?= (int)$modal['id'] % 4 ?>">
          <?php if ($mcv): ?><img class="artimg" src="<?= e($mcv) ?>" alt=""><?php endif; ?>
          <span class="badge"><?= e($modal['format']) ?></span>
          <span class="badge right"><?= e($modal['genre']) ?></span>
        </div>
        <button class="play" type="button" title="слушать / пауза">
          <img class="ic-play" src="images/play.png" alt="">
          <img class="ic-pause" src="images/pause.png" alt="">
        </button>
      </div>
      <div class="m-artist"><?= e($modal['artist']) ?></div>
      <h2><?= e($modal['title']) ?></h2>
      <div class="m-prices">
        <div><small>покупка</small><?= money($modal['price']) ?></div>
        <div><small>аренда / сутки</small><?= money($modal['rent_price']) ?></div>
      </div>
    </div>

    <div class="m-right">
      <h4 class="rl">List of songs</h4>
      <div class="tracks">
        <ul>
          <?php foreach ($songs as $s): if ($s['side'] === 'A'): ?>
            <li title="<?= e($s['title']) ?>"><?= e($s['side'] . $s['position']) ?> - <?= e($s['title']) ?></li>
          <?php endif; endforeach; ?>
        </ul>
        <ul>
          <?php foreach ($songs as $s): if ($s['side'] === 'B'): ?>
            <li title="<?= e($s['title']) ?>"><?= e($s['side'] . $s['position']) ?> - <?= e($s['title']) ?></li>
          <?php endif; endforeach; ?>
        </ul>
      </div>
      <h4 class="rl">About</h4>
      <p class="about"><?= e($modal['about']) ?></p>
      <div class="m-meta">
        <div class="m-meta-col">
          <div><small>country</small><span><?= e($modal['country']) ?></span></div>
          <div><small>year</small><span><?= (int)$modal['year'] ?></span></div>
        </div>
        <div class="m-actions">
          <form method="post" action="cart_action.php">
            <input type="hidden" name="album_id" value="<?= (int)$modal['id'] ?>">
            <input type="hidden" name="type" value="purchase">
            <?php if (in_cart_type((int)$modal['id'], 'purchase')): ?>
              <input type="hidden" name="action" value="remove">
              <button class="btn incart" title="убрать из корзины">в корзине</button>
            <?php else: ?>
              <button class="btn" title="добавить в корзину">в корзину</button>
            <?php endif; ?>
          </form>
          <form method="post" action="cart_action.php">
            <input type="hidden" name="album_id" value="<?= (int)$modal['id'] ?>">
            <input type="hidden" name="type" value="rent">
            <?php if (in_cart_type((int)$modal['id'], 'rent')): ?>
              <input type="hidden" name="action" value="remove">
              <button class="btn rent incart" title="убрать из корзины"><span><b>в корзине</b></span></button>
            <?php else: ?>
              <button class="btn rent" title="добавить в корзину (аренда)"><span><b>в аренду</b></span></button>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <?php
$curUser = current_user($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review']) && $curUser && $modal) {
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    if ($comment !== '') {
        $pdo->prepare('INSERT INTO reviews (album_id, user_id, rating, comment) VALUES (?,?,?,?)')
            ->execute([(int)$modal['id'], (int)$curUser['id'], $rating, $comment]);
        log_action($pdo, (int)$curUser['id'], 'review_add', 'album', (string)$modal['id'], "Отзыв ($rating/5) к альбому #" . (int)$modal['id']);
        flash_set('ok', 'Отзыв добавлен');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
$revStmt = $pdo->prepare('SELECT r.rating, r.comment, r.created_at, u.login FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.album_id = ? ORDER BY r.id DESC');
$revStmt->execute([(int)$modal['id']]);
$reviewsList = $revStmt->fetchAll();
$avgRating = $reviewsList ? round(array_sum(array_column($reviewsList, 'rating')) / count($reviewsList), 1) : null;
?>
<h4 class="rl" style="margin-top:16px">Reviews <?= $avgRating ? '(средняя оценка: ' . $avgRating . '/5)' : '' ?></h4>
<div style="max-height:140px;overflow:auto;font-size:20px;margin-bottom:8px">
  <?php foreach ($reviewsList as $rv): ?>
    <div style="margin-bottom:6px"><b><?= e($rv['login']) ?></b> · ★ <?= (int)$rv['rating'] ?>/5: <?= e($rv['comment']) ?></div>
  <?php endforeach; ?>
  <?php if (!$reviewsList): ?><div style="color:var(--muted)">Отзывов пока нет</div><?php endif; ?>
</div>
<?php if ($curUser): ?>
<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <select name="rating" style="background:var(--input);color:var(--cream);border:0;border-radius:8px;padding:4px 8px;font-size:18px">
    <?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>"><?= $r ?> ★</option><?php endfor; ?>
  </select>
  <input name="comment" required placeholder="Ваш отзыв..." style="flex:1;min-width:160px;background:var(--input);color:var(--cream);border:0;border-radius:8px;padding:4px 10px;font-size:18px">
  <button class="btn small" type="submit" name="add_review" value="1">Оставить</button>
</form>
<?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  var btn = document.querySelector('.play');
  var disc = document.querySelector('.disc');
  var ov = document.querySelector('.overlay');
  var closeBtn = document.querySelector('.modal .close');
  if (!btn) return;

  var audioUrl = <?= json_encode($mAudio, JSON_UNESCAPED_SLASHES) ?>;
  var audioEl = null;
  var ctx = null;
  var timer = null;
  var playing = false;

  function stopSound() {
    clearTimeout(timer);
    timer = null;
    if (audioEl) {
      audioEl.pause();
      audioEl.currentTime = 0;
    }
    if (ctx) {
      ctx.close().catch(function () {});
      ctx = null;
    }
  }

  function startSynth() {
    var AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
      setPlaying(false);
      return;
    }
    ctx = new AudioCtx();
    var notes = [196, 220, 247, 262, 294, 262, 247, 220];
    var t = ctx.currentTime;
    for (var i = 0; i < 60; i++) {
      var o = ctx.createOscillator();
      var g = ctx.createGain();
      o.type = 'triangle';
      o.frequency.value = notes[i % 8] * (i % 16 < 8 ? 1 : 0.5);
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(0.12, t + 0.06);
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.48);
      o.connect(g);
      g.connect(ctx.destination);
      o.start(t);
      o.stop(t + 0.5);
      t += 0.5;
    }
    timer = setTimeout(function () {
      setPlaying(false);
    }, 30000);
  }

  function startSound() {
    if (audioUrl) {
      if (!audioEl) {
        audioEl = new Audio(audioUrl);
        audioEl.preload = 'auto';
        audioEl.addEventListener('ended', function () {
          setPlaying(false);
        });
        audioEl.addEventListener('error', function () {
          setPlaying(false);
          alert('Не удалось воспроизвести трек: ' + audioUrl);
        });
      }
      audioEl.currentTime = 0;
      var playPromise = audioEl.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
          setPlaying(false);
        });
      }
      timer = setTimeout(function () {
        setPlaying(false);
      }, 30000);
      return;
    }
    startSynth();
  }

  function setPlaying(nextState) {
    if (playing === nextState) return;
    playing = nextState;
    btn.classList.toggle('playing', playing);
    if (disc) disc.classList.toggle('spin', playing);
    if (playing) startSound();
    else stopSound();
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    setPlaying(!playing);
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      setPlaying(false);
    });
  }

  if (ov) {
    ov.addEventListener('click', function (ev) {
      if (ev.target === ov) {
        setPlaying(false);
        location.href = ov.dataset.back;
      }
    });
  }
})();
</script>
<?php endif; ?>
<?php require 'footer.php'; ?>