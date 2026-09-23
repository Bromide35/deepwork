<?php
$__u    = current_user();
$__page = basename($_SERVER['PHP_SELF']);

$__nav = [
    'countdown.php' => 'นับถอยหลัง',
    'timer.php'     => 'จับเวลา',
    'dashboard.php' => 'สถิติ',
    'history.php'   => 'ประวัติ',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? APP_NAME) ?> · <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Gaegu:wght@400;700&family=Itim&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body data-csrf="<?= csrf_token() ?>">

<header class="brushbar">
  <nav class="pills">
    <?php foreach ($__nav as $file => $label): ?>
      <a href="<?= url($file) ?>" class="pill<?= $__page === $file ? ' on' : '' ?>">
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="userbox">
    <a href="<?= url('categories.php') ?>" class="navmini">🏷 หมวดหมู่</a>
    <a href="<?= url('settings.php') ?>" class="navmini">⚙️ ตั้งค่า</a>
    <a href="<?= url('about.php') ?>" class="navmini">ℹ️ เกี่ยวกับ</a>
    <img src="<?= e($__u['avatar'] ? url('uploads/' . $__u['avatar']) : url('assets/img/avatar.svg')) ?>"
         alt="รูปโปรไฟล์" class="avatar" width="32" height="32">
    <span class="uname"><?= e($__u['display_name']) ?></span>
    <a class="btn-ghost" href="<?= url('logout.php') ?>">ออก</a>
  </div>
</header>

<main class="page">