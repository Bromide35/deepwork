<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user      = require_login();
$pageTitle = 'เกี่ยวกับโปรเจกต์';

$features = [
    ['🎯','Focus Score','วัด "คุณภาพ" การโฟกัส ไม่ใช่แค่ชั่วโมง โดยหักคะแนนเมื่อสลับแท็บบ่อย'],
    ['🔥','เชือกชนวนติดไฟ','แทน progress bar ธรรมดา สร้างความรู้สึกเร่งด่วนให้ไม่อยากลุกไปไหน'],
    ['🌙','Day Reset ยืดหยุ่น','ตั้งได้ว่าวันใหม่เริ่มกี่โมง แก้ปัญหาอ่านข้ามเที่ยงคืนแล้ว streak ขาด'],
    ['💾','กู้เซสชันอัตโนมัติ','ปิดแท็บหรือเบราว์เซอร์ค้าง ข้อมูลไม่หาย เพราะมี heartbeat ทุก 30 วินาที'],
    ['✏️','แก้เวลาย้อนหลังได้','ลืมกดเริ่มก็แก้ได้ แต่ติดป้าย "แก้ไขแล้ว" เพื่อความโปร่งใส'],
    ['🖥','ใช้บนคอมได้','ต่างจากแอปคู่แข่ง (YPT) ที่รองรับเฉพาะมือถือ'],
];

$stack = [
    'ภาษาฝั่งเซิร์ฟเวอร์'   => 'PHP 8',
    'ฐานข้อมูล'            => 'MySQL / MariaDB',
    'ฝั่งผู้ใช้'            => 'HTML5 · CSS3 · JavaScript (ES6)',
    'การเชื่อมต่อฐานข้อมูล' => 'PDO + Prepared Statement',
    'ความปลอดภัย'          => 'password_hash() · CSRF Token · Login Throttle',
];

require __DIR__ . '/inc/header.php';
?>

<h1 class="page-title">เกี่ยวกับ <?= APP_NAME ?></h1>

<section class="panel">
  <img src="<?= url('assets/img/logo.svg') ?>" alt="โลโก้" width="72" height="72">
  <h2>วัดคุณภาพโฟกัส ไม่ใช่แค่จำนวนชั่วโมง</h2>
  <p class="muted">
    DeepWork เกิดจากการวิเคราะห์จุดอ่อนของแอปจับเวลาอ่านหนังสือชื่อดังอย่าง YPT (Yeolpumta)
    ที่แม้จะมีผู้ใช้หลายล้านคน แต่ยังมีปัญหาเรื่องเขตเวลา การแก้เวลาย้อนหลัง
    และไม่มีเวอร์ชันบนคอมพิวเตอร์
  </p>
</section>

<section class="panel">
  <h2>ฟีเจอร์เด่น</h2>
  <div class="feat-grid">
    <?php foreach ($features as [$icon, $title, $desc]): ?>
      <article class="feat">
        <h3><?= $icon ?> <?= e($title) ?></h3>
        <p class="muted"><?= e($desc) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2>เทคโนโลยีที่ใช้</h2>
  <table class="table">
    <?php foreach ($stack as $k => $v): ?>
      <tr><th style="width:38%"><?= e($k) ?></th><td><?= e($v) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="panel">
  <h2>ลิงก์ที่เกี่ยวข้อง</h2>
  <ul class="linklist">
    <li><a href="<?= url('countdown.php') ?>">→ เริ่มนับถอยหลัง</a></li>
    <li><a href="<?= url('dashboard.php') ?>">→ ดูสถิติของฉัน</a></li>
    <li><a href="https://www.php.net/manual/th/" target="_blank" rel="noopener">→ คู่มือ PHP อย่างเป็นทางการ</a></li>
  </ul>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>