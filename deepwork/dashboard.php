<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user      = require_login();
$pdo       = db();
$pageTitle = 'สถิติของฉัน';

$today = day_bucket(new DateTimeImmutable('now', new DateTimeZone('UTC')),
                    $user['timezone'], (int)$user['day_start_hour']);

/* ===== SELECT ข้อมูล 30 วัน ===== */
$st = $pdo->prepare(
    "SELECT day_bucket,
            SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at) - away_seconds) AS focus_sec,
            ROUND(AVG(focus_score)) AS avg_score,
            COUNT(*) AS n
     FROM sessions
     WHERE user_id = ? AND ended_at IS NOT NULL
       AND day_bucket >= DATE_SUB(?, INTERVAL 29 DAY)
     GROUP BY day_bucket"
);
$st->execute([$user['id'], $today]);

/* ★ while loop */
$byDay = [];
while ($row = $st->fetch()) {
    $byDay[$row['day_bucket']] = [
        'sec'   => (int)$row['focus_sec'],
        'score' => (int)$row['avg_score'],
    ];
}

/* ★ for loop สร้างกราฟ 7 วัน */
$days = []; $maxSec = 1; $weekSec = 0;
$thaiDay = ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
for ($i = 6; $i >= 0; $i--) {
    $d   = (new DateTimeImmutable($today))->modify("-{$i} day");
    $key = $d->format('Y-m-d');
    $sec = $byDay[$key]['sec'] ?? 0;
    $maxSec = max($maxSec, $sec);
    $weekSec += $sec;
    $days[] = [
        'label' => $thaiDay[(int)$d->format('w')],
        'date'  => $d->format('j/n'),
        'sec'   => $sec,
        'today' => ($key === $today),
    ];
}

/* ★ do...while นับ streak */
$streak = 0;
$cursor = new DateTimeImmutable($today);
do {
    $key = $cursor->format('Y-m-d');
    if (!isset($byDay[$key]) || $byDay[$key]['sec'] < 300) break;
    $streak++;
    $cursor = $cursor->modify('-1 day');
} while ($streak < 365);

$todaySec   = $byDay[$today]['sec']   ?? 0;
$todayScore = $byDay[$today]['score'] ?? 0;
$goalSec    = (int)$user['daily_goal_min'] * 60;
$progress   = $goalSec > 0 ? min(100, (int)round($todaySec / $goalSec * 100)) : 0;
$label      = score_label($todayScore);

/* ===== แยกตามหมวด ===== */
$cs = $pdo->prepare(
    "SELECT c.name, c.color, c.icon,
            SUM(TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at) - s.away_seconds) AS sec
     FROM sessions s
     JOIN categories c ON c.id = s.category_id
     WHERE s.user_id = ? AND s.ended_at IS NOT NULL
       AND s.day_bucket >= DATE_SUB(?, INTERVAL 6 DAY)
     GROUP BY c.id ORDER BY sec DESC"
);
$cs->execute([$user['id'], $today]);
$cats = $cs->fetchAll();
$catTotal = array_sum(array_column($cats, 'sec')) ?: 1;

require __DIR__ . '/inc/header.php';
?>

<h1 class="page-title">สถิติของฉัน</h1>

<section class="cards">
  <div class="card">
    <p class="card-key">โฟกัสวันนี้</p>
    <p class="card-val"><?= fmt_hm($todaySec) ?></p>
    <div class="progress"><span style="width:<?= $progress ?>%"></span></div>
    <p class="muted"><?= $progress ?>% ของเป้า <?= (int)$user['daily_goal_min'] ?> นาที</p>
  </div>
  <div class="card">
    <p class="card-key">Focus Score เฉลี่ย</p>
    <p class="card-val <?= $label['class'] ?>"><?= $todayScore ?></p>
    <p class="muted"><?= $label['emoji'] ?> <?= $label['text'] ?></p>
  </div>
  <div class="card">
    <p class="card-key">Streak ต่อเนื่อง</p>
    <p class="card-val"><?= $streak ?> <span class="unit">วัน</span></p>
    <p class="muted"><?= $streak >= 3 ? 'ทำได้ดีมาก อย่าให้ขาด!' : 'อ่านให้ครบ 5 นาทีเพื่อต่อ streak' ?></p>
  </div>
  <div class="card">
    <p class="card-key">รวม 7 วัน</p>
    <p class="card-val"><?= fmt_hm($weekSec) ?></p>
    <p class="muted">เฉลี่ยวันละ <?= fmt_hm((int)($weekSec / 7)) ?></p>
  </div>
</section>

<section class="panel">
  <h2>โฟกัสย้อนหลัง 7 วัน</h2>
  <div class="chart">
    <?php foreach ($days as $d): ?>
      <?php $h = (int)round($d['sec'] / $maxSec * 100); ?>
      <div class="bar-col<?= $d['today'] ? ' today' : '' ?>">
        <span class="bar-num"><?= $d['sec'] ? round($d['sec']/3600, 1) : '' ?></span>
        <div class="bar" style="height:<?= max($h, 2) ?>%" title="<?= fmt_hm($d['sec']) ?>"></div>
        <span class="bar-lbl"><?= $d['label'] ?></span>
        <span class="bar-date"><?= $d['date'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2>แบ่งตามหมวดหมู่ (7 วันล่าสุด)</h2>
  <?php if (!$cats): ?>
    <p class="muted">ยังไม่มีข้อมูล ลอง <a href="<?= url('countdown.php') ?>">เริ่มจับเวลา</a> ดูสิ</p>
  <?php else: ?>
    <ul class="catlist">
      <?php foreach ($cats as $c): $pct = round((int)$c['sec'] / $catTotal * 100); ?>
        <li>
          <span><?= e($c['icon'] . ' ' . $c['name']) ?></span>
          <div class="cat-bar"><span style="width:<?= $pct ?>%; background:<?= e($c['color']) ?>"></span></div>
          <span class="cat-val"><?= fmt_hm((int)$c['sec']) ?> · <?= $pct ?>%</span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>