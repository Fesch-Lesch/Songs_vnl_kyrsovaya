<?php
require 'config.php';

$fio = trim($_POST['fio'] ?? '');
$simUrl = trim($_POST['sim_url'] ?? 'http://prb.sylas.ru/TransferSimulator/fullName');
$fetchedFromSim = false;
$simError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fetch_sim') {
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $raw = @file_get_contents($simUrl, false, $ctx);
    if ($raw === false) {
        $simError = 'Не удалось получить ответ от эмулятора по адресу: ' . $simUrl;
    } else {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $fio = trim((string)($decoded['fullName'] ?? $decoded['fio'] ?? $decoded['name'] ?? ''));
        } else {
            $fio = trim(strip_tags($raw));
        }
        $fetchedFromSim = true;
    }
}

/* Критерий 1: отсутствие цифр и запрещённых спецсимволов */
$forbiddenChars = '0-9!@#$%^&*_=+<>?/\\|`~"\';:[]{}()';
$hasForbidden = ($fio === '') || (bool)preg_match('/[' . preg_quote($forbiddenChars, '/') . ']/u', $fio);

/* Критерий 2: корректная структура ФИО (слова из букв кириллицы/латиницы, пробелы и дефис для двойных фамилий/имён) */
$words = preg_split('/\s+/u', $fio, -1, PREG_SPLIT_NO_EMPTY);
$validStructure = ($fio !== '')
    && (bool)preg_match('/^[А-ЯЁA-Z][а-яёa-z-]+(\s+[А-ЯЁA-Z][а-яёa-z-]+){1,2}$/u', $fio)
    && count($words) >= 2;

$crit1Passed = !$hasForbidden;
$crit2Passed = $validStructure;
$overallPassed = $crit1Passed && $crit2Passed;

$verdictText = $overallPassed
    ? 'Проверка пройдена: ФИО не содержит запрещённых символов и соответствует структуре.'
    : 'ФИО содержит запрещённые символы или некорректную структуру.';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Валидация данных клиента — SONGS.VNL</title>
<style>
  body { font-family: Arial, sans-serif; background: #2b1e1a; color: #f6efe3; margin: 0; padding: 30px; }
  .box { max-width: 720px; margin: 0 auto; background: #423227; border-radius: 14px; padding: 24px; }
  h1 { margin-top: 0; color: #51d4c9; font-size: 26px; }
  label { display: block; margin: 12px 0 6px; font-weight: bold; }
  input[type="text"] { width: 100%; box-sizing: border-box; padding: 10px 12px; border-radius: 8px; border: 0; background: #31261f; color: #fff; font-size: 16px; }
  .row { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
  button { padding: 10px 18px; border-radius: 8px; border: 0; cursor: pointer; font-weight: bold; font-size: 15px; }
  .btn-teal { background: #51d4c9; color: #2b1e1a; }
  .btn-olive { background: #6c7d54; color: #fff; }
  .card { margin-top: 20px; padding: 16px; border-radius: 10px; background: #31261f; }
  .ok { color: #7be08a; font-weight: bold; }
  .bad { color: #ff7b72; font-weight: bold; }
  textarea { width: 100%; box-sizing: border-box; margin-top: 8px; padding: 10px; border-radius: 8px; border: 0; background: #221915; color: #f6efe3; font-family: monospace; }
</style>
</head>
<body>
<div class="box">
  <h1>Валидация данных клиента (TransferSimulator)</h1>

  <form method="post">
    <label for="sim_url">URL эмулятора TransferSimulator</label>
    <input id="sim_url" name="sim_url" type="text" value="<?= e($simUrl) ?>">
    <div class="row">
      <button class="btn-olive" type="submit" name="action" value="fetch_sim">Получить данные</button>
    </div>
  </form>

  <?php if ($simError !== ''): ?>
    <p class="bad"><?= e($simError) ?></p>
  <?php elseif ($fetchedFromSim): ?>
    <p class="ok">Данные получены от эмулятора.</p>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="sim_url" value="<?= e($simUrl) ?>">
    <label for="fio">ФИО клиента</label>
    <input id="fio" name="fio" type="text" value="<?= e($fio) ?>" placeholder="Например: Иванов Иван Иванович">
    <div class="row">
      <button class="btn-teal" type="submit" name="action" value="validate">Отправить результат теста</button>
    </div>
  </form>

  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <div class="card">
      <div><b>Входное значение:</b> <?= e($fio !== '' ? $fio : '(пусто)') ?></div>
      <p>
        <b>Критерий 1 (нет цифр и запрещённых символов):</b>
        <span class="<?= $crit1Passed ? 'ok' : 'bad' ?>"><?= $crit1Passed ? 'Пройден' : 'Не пройден' ?></span>
      </p>
      <p>
        <b>Критерий 2 (корректная структура ФИО):</b>
        <span class="<?= $crit2Passed ? 'ok' : 'bad' ?>"><?= $crit2Passed ? 'Пройден' : 'Не пройден' ?></span>
      </p>
      <p>
        <b>Итог:</b>
        <span class="<?= $overallPassed ? 'ok' : 'bad' ?>"><?= e($verdictText) ?></span>
      </p>
      <label for="case_out">Текст для вставки в закладку столбца «Результат» в ТестКейс.docx</label>
      <textarea id="case_out" rows="3" readonly><?= e("ФИО: \"{$fio}\" | Критерий 1 (запрещённые символы): " . ($crit1Passed ? 'OK' : 'FAIL') . " | Критерий 2 (структура ФИО): " . ($crit2Passed ? 'OK' : 'FAIL') . " | Итог: " . ($overallPassed ? 'Пройден' : 'Провален')) ?></textarea>
    </div>
  <?php endif; ?>
</div>
</body>
</html>