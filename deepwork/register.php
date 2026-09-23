<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

if (current_user()) redirect(url('countdown.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'เซสชันหมดอายุ กรุณาลองใหม่';
    } else {
        $r = register_user($_POST['email'] ?? '', $_POST['name'] ?? '', $_POST['password'] ?? '');
        if ($r['ok']) redirect(url('countdown.php'));
        $error = $r['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>สมัครสมาชิก · <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Gaegu:wght@400;700&family=Itim&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="auth-body">
<div class="auth-card">
  <img src="<?= url('assets/img/logo.svg') ?>" alt="โลโก้" width="52" height="52">
  <h1>สร้างบัญชี</h1>
  <p class="muted">วัดคุณภาพโฟกัส ไม่ใช่แค่จำนวนชั่วโมง</p>

  <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="name">ชื่อที่แสดง</label>
      <input id="name" type="text" name="name" required maxlength="80"
             value="<?= e($_POST['name'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="email">อีเมล</label>
      <input id="email" type="email" name="email" required
             value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัว)</label>
      <input id="password" type="password" name="password" required minlength="8">
    </div>
    <button class="btn" type="submit">สมัครสมาชิก</button>
  </form>

  <p class="foot">มีบัญชีแล้ว? <a href="<?= url('login.php') ?>">เข้าสู่ระบบ</a></p>
</div>
</body>
</html>