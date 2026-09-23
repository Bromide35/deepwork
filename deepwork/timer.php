<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user      = require_login();
$pageTitle = 'จับเวลา';

$st = db()->prepare('SELECT id, name, color, icon FROM categories
                     WHERE user_id = ? AND archived = 0 ORDER BY sort_order, id');
$st->execute([$user['id']]);
$cats = $st->fetchAll();

$modes = [
    'stopwatch' => ['⏱', 'จับเวลาขึ้น', 'นับขึ้นไปเรื่อย ๆ'],
    'pomodoro'  => ['🍅', 'โพโมโดโร',  'โฟกัส 25 พัก 5'],
];

require __DIR__ . '/inc/header.php';
?>

<div class="timer-wrap">

  <!-- ★ radio เลือกโหมด -->
  <div class="mode-row">
    <?php foreach ($modes as $val => [$icon, $name, $desc]): ?>
      <label class="mode-opt">
        <input type="radio" name="mode" value="<?= $val ?>" <?= $val === 'stopwatch' ? 'checked' : '' ?>>
        <span class="mode-box">
          <span class="mode-icon"><?= $icon ?></span>
          <span class="mode-name"><?= $name ?></span>
          <span class="mode-desc"><?= $desc ?></span>
        </span>
      </label>
    <?php endforeach; ?>
  </div>

  <div class="cat-row" id="catRow">
    <?php foreach ($cats as $i => $c): ?>
      <button class="chip<?= $i === 0 ? ' active' : '' ?>"
              data-id="<?= (int)$c['id'] ?>" style="--chip:<?= e($c['color']) ?>">
        <?= e($c['icon'] . ' ' . $c['name']) ?>
      </button>
    <?php endforeach; ?>
    <button class="chip" id="addCat">+ เพิ่มหมวด</button>
  </div>

  <p class="muted" id="stateText">พร้อมเริ่มโฟกัส</p>
  <h1 class="clock-big" id="clock">00:00:00</h1>

  <div class="stats">
    <div class="stat"><span class="stat-val sc-great" id="scoreVal">100</span><span class="stat-key">Focus Score</span></div>
    <div class="stat"><span class="stat-val" id="blurVal">0</span><span class="stat-key">Distractions</span></div>
    <div class="stat"><span class="stat-val" id="awayVal">0:00</span><span class="stat-key">Away</span></div>
  </div>

  <button class="btn" id="toggleBtn">เริ่มโฟกัส</button>

  <p class="hint">สลับแท็บหรือย่อหน้าต่างเกิน 3 วินาที ระบบจะหยุดนับเวลาและบันทึกเป็น distraction</p>

  <div class="result" id="result" hidden></div>
</div>

<script>
  window.DW = { base: <?= json_encode(BASE_URL) ?>, csrf: <?= json_encode(csrf_token()) ?> };
</script>
<script src="<?= url('assets/js/timer.js') ?>" defer></script>

<?php require __DIR__ . '/inc/footer.php'; ?>