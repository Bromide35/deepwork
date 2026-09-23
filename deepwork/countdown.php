<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user = require_login();
$pdo  = db();

$presets = [
    15 => ['สั้น ๆ',   'อุ่นเครื่อง'],
    25 => ['โพโมโดโร', 'คลาสสิก'],
    50 => ['โฟกัสลึก', 'แนะนำ'],
    90 => ['มาราธอน',  'สายอึด'],
];

$st = $pdo->prepare('SELECT id, name, icon FROM categories
                     WHERE user_id = ? AND archived = 0 ORDER BY sort_order, id');
$st->execute([$user['id']]);
$cats = $st->fetchAll();

$navPills = [
    'countdown.php' => 'นับถอยหลัง',
    'timer.php'     => 'จับเวลา',
    'dashboard.php' => 'สถิติ',
    'history.php'   => 'ประวัติ',
];

/* ★ switch เลือกคำทักทายตามเวลา */
$hour = (int)(new DateTime('now', new DateTimeZone($user['timezone'])))->format('G');
switch (true) {
    case ($hour < 5):  $greet = 'ดึกแล้ว พักสายตาบ้างนะ'; break;
    case ($hour < 12): $greet = 'อรุณสวัสดิ์ เริ่มวันด้วยโฟกัส'; break;
    case ($hour < 17): $greet = 'ช่วงบ่าย ลุยต่อเลย'; break;
    case ($hour < 21): $greet = 'เย็นนี้เก็บอีกสักรอบ'; break;
    default:           $greet = 'ก่อนนอน อ่านอีกนิดนะ';
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>นับถอยหลัง · <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Gaegu:wght@400;700&family=Itim&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/countdown.css') ?>">
</head>
<body data-state="idle">

<header class="brushbar">
  <nav class="pills">
    <?php foreach ($navPills as $file => $label): ?>
      <a href="<?= url($file) ?>" class="pill<?= basename($_SERVER['PHP_SELF']) === $file ? ' on' : '' ?>">
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <button class="burger" id="burgerBtn" aria-label="เปิดเมนู">
    <svg viewBox="0 0 40 40" width="40" height="40">
      <circle cx="20" cy="20" r="18" fill="#253745"/>
      <path d="M10 14 Q20 12 30 14" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>
      <path d="M10 20 Q20 22 30 20" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>
      <path d="M10 26 Q20 24 30 26" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>
    </svg>
  </button>
</header>

<aside class="drawer" id="drawer" hidden>
  <h2>เมนูทั้งหมด</h2>
  <a href="<?= url('categories.php') ?>">🏷 จัดการหมวดหมู่</a>
  <a href="<?= url('settings.php') ?>">⚙️ ตั้งค่า</a>
  <a href="<?= url('about.php') ?>">ℹ️ เกี่ยวกับโปรเจกต์</a>
  <button type="button" id="themeBtn" class="drawer-btn">🌗 สลับธีม สว่าง/มืด</button>
  <a href="<?= url('logout.php') ?>" class="danger-link">🚪 ออกจากระบบ</a>
</aside>
<div class="scrim" id="scrim" hidden></div>

<main class="stage">
  <p class="greet" id="greet"><?= e($greet) ?></p>

  <section class="clockrow">
    <div class="digit-block">
      <span class="digit" id="digitL">50</span>
      <span class="digit-key" id="keyL">นาที</span>
    </div>

    <div class="alarm">
      <span class="dot dot-top"></span>
      <svg class="alarm-svg" viewBox="0 0 220 230" role="img" aria-label="นาฬิกาปลุก">
        <path class="ink" d="M62 186 L44 214 M78 192 L70 216"/>
        <path class="ink" d="M158 186 L176 214 M142 192 L150 216"/>
        <path class="ink" d="M92 34 Q110 26 130 34 L126 48 Q110 42 96 48 Z"/>
        <path class="ink thick" d="M110 44 C 158 44 194 82 194 118 C 194 158 156 192 110 192
                                    C 62 192 26 156 26 118 C 26 80 62 44 110 44 Z"/>
        <path class="ink" d="M110 54 C 152 54 184 86 184 118 C 184 152 150 182 110 182
                             C 68 182 36 150 36 118 C 36 86 68 54 110 54 Z"/>
        <path id="wedge" class="wedge" d=""/>
        <g class="ticks" id="ticks"></g>
        <line id="handEnd" class="ink hand" x1="110" y1="118" x2="110" y2="62" marker-end="url(#arrow)"/>
        <circle cx="110" cy="118" r="5" class="pivot"/>
        <defs>
          <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5"
                  markerWidth="5" markerHeight="5" orient="auto">
            <path d="M0 0 L10 5 L0 10 z" fill="currentColor"/>
          </marker>
        </defs>
      </svg>
      <span class="dot dot-bottom"></span>

      <button class="playmask" id="playBtn" aria-label="เริ่มนับถอยหลัง">
        <svg viewBox="0 0 100 100" width="100" height="100">
          <path id="playIcon" d="M28 16 L86 50 L28 84 Z"/>
          <g id="pauseIcon" hidden>
            <rect x="32" y="20" width="14" height="60" rx="4"/>
            <rect x="56" y="20" width="14" height="60" rx="4"/>
          </g>
        </svg>
      </button>
    </div>

    <div class="digit-block">
      <span class="digit" id="digitR">00</span>
      <span class="digit-key" id="keyR">วินาที</span>
    </div>
  </section>

  <section class="fuse-wrap" aria-hidden="true">
    <svg class="fuse-svg" viewBox="0 0 800 90" preserveAspectRatio="none">
      <path class="ink thick" d="M44 26 C 26 40 26 54 44 68"/>
      <path class="ink thick" d="M756 26 C 774 40 774 54 756 68"/>
      <path id="fuseAsh" class="fuse-ash" d="M62 48 C 180 40 300 56 420 46 S 640 40 738 49"/>
      <path id="fuseLive" class="fuse-live" pathLength="100"
            d="M62 48 C 180 40 300 56 420 46 S 640 40 738 49"/>
      <g id="spark" class="spark">
        <path d="M0 -18 L5 -5 L18 -2 L6 4 L10 18 L0 9 L-10 18 L-6 4 L-18 -2 L-5 -5 Z"/>
        <circle r="4" class="spark-core"/>
      </g>
    </svg>
    <div class="embers" id="embers"></div>
  </section>

  <section class="setup" id="setup">
    <div class="preset-row">
      <?php foreach ($presets as $min => [$name, $tag]): ?>
        <label class="preset">
          <input type="radio" name="preset" value="<?= $min ?>" <?= $min === 50 ? 'checked' : '' ?>>
          <span class="preset-box"><b><?= $min ?></b><em><?= e($name) ?></em><i><?= e($tag) ?></i></span>
        </label>
      <?php endforeach; ?>
      <label class="preset">
        <input type="radio" name="preset" value="custom">
        <span class="preset-box"><b>⚙</b><em>กำหนดเอง</em><i>5–480</i></span>
      </label>
    </div>

        <div class="setup-row">
      <div class="f">
        <label for="customMin">นาที</label>
        <div class="spin">
          <button type="button" class="spin-btn" data-target="min" data-step="-5">−</button>
          <input id="customMin" type="number" min="0" max="480" step="1" value="50" disabled>
          <button type="button" class="spin-btn" data-target="min" data-step="5">+</button>
        </div>
      </div>

      <div class="f">
        <label for="customSec">วินาที</label>
        <div class="spin">
          <button type="button" class="spin-btn" data-target="sec" data-step="-10">−</button>
          <input id="customSec" type="number" min="0" max="59" step="1" value="0" disabled>
          <button type="button" class="spin-btn" data-target="sec" data-step="10">+</button>
        </div>
      </div>

      <div class="f">
        <label for="catSel">หมวดหมู่</label>
        <select id="catSel">
          <option value="">— ไม่ระบุ —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['icon'] . ' ' . $c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <label class="chk">
        <input type="checkbox" id="soundOn" checked>
        <span>เสียงเตือนเมื่อหมดเวลา</span>
      </label>
    </div>
  </section>

  <section class="live" id="live" hidden>
    <span>Focus <b id="scoreVal">100</b></span>
    <span>·</span>
    <span>หลุดโฟกัส <b id="blurVal">0</b> ครั้ง</span>
    <span>·</span>
    <button class="ghost" id="resetBtn">ยกเลิก</button>
  </section>

  <p class="hint">กด <kbd>Space</kbd> เพื่อเริ่ม/หยุด · สลับแท็บเกิน 3 วินาทีจะถูกนับเป็นการหลุดโฟกัส</p>
</main>

<a class="profile" href="<?= url('settings.php') ?>" aria-label="โปรไฟล์">
  <img src="<?= e($user['avatar'] ? url('uploads/' . $user['avatar']) : url('assets/img/avatar.svg')) ?>"
       alt="" width="42" height="42">
</a>

<div class="finish" id="finish" hidden>
  <div class="finish-card">
    <span class="finish-emoji" id="finishEmoji">🔥</span>
    <h2 id="finishTitle">หมดเวลาแล้ว!</h2>
    <p id="finishBody"></p>
    <div class="finish-actions">
      <button class="btn-brush" id="againBtn">เริ่มรอบใหม่</button>
      <a class="btn-brush ghost" href="<?= url('dashboard.php') ?>">ดูสถิติ</a>
    </div>
  </div>
</div>

<script>
  window.DW = { base: <?= json_encode(BASE_URL) ?>, csrf: <?= json_encode(csrf_token()) ?> };
</script>
<script src="<?= url('assets/js/countdown.js') ?>" defer></script>
</body>
</html>