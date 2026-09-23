<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user      = require_login();
$pdo       = db();
$pageTitle = 'ตั้งค่า';
$msg = ''; $msgType = 'ok';

$timezones = [
    'Asia/Bangkok'     => 'ไทย (GMT+7)',
    'Asia/Tokyo'       => 'ญี่ปุ่น (GMT+9)',
    'Asia/Seoul'       => 'เกาหลี (GMT+9)',
    'Asia/Singapore'   => 'สิงคโปร์ (GMT+8)',
    'Europe/London'    => 'ลอนดอน (GMT+0)',
    'America/New_York' => 'นิวยอร์ก (GMT-5)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $name    = mb_substr(trim((string)$_POST['display_name']), 0, 80);
    $tzKey   = array_key_exists($_POST['timezone'] ?? '', $timezones) ? $_POST['timezone'] : 'Asia/Bangkok';
    $startHr = max(0, min(12, (int)$_POST['day_start_hour']));
    $goal    = max(15, min(960, (int)$_POST['daily_goal_min']));
    $zen     = isset($_POST['zen_mode']) ? 1 : 0;
    $theme   = in_array($_POST['theme'] ?? '', ['dark','light','auto'], true) ? $_POST['theme'] : 'dark';

    /* ===== อัปโหลดรูปโปรไฟล์ ===== */
    $avatar = $user['avatar'];
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allow = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        $info  = getimagesize($_FILES['avatar']['tmp_name']);
        $mime  = $info['mime'] ?? '';

        if (!isset($allow[$mime])) {
            $msg = 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP'; $msgType = 'err';
        } elseif ($_FILES['avatar']['size'] > 2*1024*1024) {
            $msg = 'ไฟล์ต้องไม่เกิน 2 MB'; $msgType = 'err';
        } else {
            $newName = 'u' . $user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $allow[$mime];
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/uploads/' . $newName)) {
                if ($avatar && is_file(__DIR__ . '/uploads/' . $avatar)) unlink(__DIR__ . '/uploads/' . $avatar);
                $avatar = $newName;
            }
        }
    }

    if ($msgType === 'ok') {
        $pdo->prepare(
            'UPDATE users SET display_name=?, avatar=?, timezone=?,
                    day_start_hour=?, daily_goal_min=?, zen_mode=?, theme=? WHERE id=?'
        )->execute([$name, $avatar, $tzKey, $startHr, $goal, $zen, $theme, $user['id']]);

        $msg  = 'บันทึกการตั้งค่าเรียบร้อยแล้ว';
        $user = array_merge($user, [
            'display_name'=>$name, 'avatar'=>$avatar, 'timezone'=>$tzKey,
            'day_start_hour'=>$startHr, 'daily_goal_min'=>$goal,
            'zen_mode'=>$zen, 'theme'=>$theme,
        ]);
    }
}

require __DIR__ . '/inc/header.php';
?>

<h1 class="page-title">ตั้งค่า</h1>

<?php if ($msg): ?>
  <div class="alert <?= $msgType === 'ok' ? 'alert-ok' : '' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <section class="panel">
    <h2>โปรไฟล์</h2>
    <div class="avatar-row">
      <img src="<?= e($user['avatar'] ? url('uploads/'.$user['avatar']) : url('assets/img/avatar.svg')) ?>"
           alt="รูปโปรไฟล์ปัจจุบัน" class="avatar-lg" width="84" height="84">
      <div class="field grow">
        <label for="avatar">เปลี่ยนรูปโปรไฟล์ (JPG/PNG/WEBP ไม่เกิน 2 MB)</label>
        <input id="avatar" type="file" name="avatar" accept="image/*">
      </div>
    </div>
    <div class="field">
      <label for="display_name">ชื่อที่แสดง</label>
      <input id="display_name" type="text" name="display_name" required maxlength="80"
             value="<?= e($user['display_name']) ?>">
    </div>
    <div class="field">
      <label>อีเมล</label>
      <input type="email" value="<?= e($user['email']) ?>" disabled>
    </div>
  </section>

  <section class="panel">
    <h2>เวลาและเป้าหมาย</h2>
    <div class="field">
      <label for="timezone">เขตเวลา</label>
      <select id="timezone" name="timezone">
        <?php foreach ($timezones as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $user['timezone'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="day_start_hour">วันใหม่เริ่มตอน</label>
      <select id="day_start_hour" name="day_start_hour">
        <?php /* ★ for loop สร้างตัวเลือก */ ?>
        <?php for ($h = 0; $h <= 12; $h++): ?>
          <option value="<?= $h ?>" <?= (int)$user['day_start_hour'] === $h ? 'selected' : '' ?>>
            <?= sprintf('%02d:00 น.', $h) ?><?= $h === 4 ? ' (แนะนำ)' : '' ?>
          </option>
        <?php endfor; ?>
      </select>
      <p class="muted">ตั้ง 04:00 น. → อ่านตอนตี 2 จะนับเป็นของเมื่อวาน streak จะไม่ขาด</p>
    </div>
    <div class="field">
      <label for="daily_goal_min">เป้าหมายต่อวัน (นาที)</label>
      <input id="daily_goal_min" type="number" name="daily_goal_min"
             min="15" max="960" step="15" value="<?= (int)$user['daily_goal_min'] ?>">
    </div>
  </section>

  <section class="panel">
    <h2>การแสดงผล</h2>
    <div class="field">
      <label>ธีมสี</label>
      <div class="radio-row">
        <?php $themes = ['dark'=>'🌙 มืด', 'light'=>'☀️ สว่าง', 'auto'=>'🔄 ตามระบบ'];
        foreach ($themes as $val => $lbl): ?>
          <label class="radio">
            <input type="radio" name="theme" value="<?= $val ?>" <?= $user['theme'] === $val ? 'checked' : '' ?>>
            <span><?= $lbl ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field">
      <label class="checkbox">
        <input type="checkbox" name="zen_mode" value="1" <?= (int)$user['zen_mode'] ? 'checked' : '' ?>>
        <span>เปิด Zen Mode — ซ่อนอันดับและการเปรียบเทียบกับผู้อื่น</span>
      </label>
    </div>
  </section>

  <button class="btn" type="submit">บันทึกการตั้งค่า</button>
</form>

<?php require __DIR__ . '/inc/footer.php'; ?>