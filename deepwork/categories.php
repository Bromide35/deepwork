<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user      = require_login();
$pdo       = db();
$pageTitle = 'จัดการหมวดหมู่';
$msg = ''; $msgType = 'ok';

$icons = ['📘','📗','📕','💻','🧮','🔬','🌏','🎨','🎵','✍️','🏃','💼'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $name   = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
    $color  = preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#FF7A2F';
    $icon   = in_array($_POST['icon'] ?? '', $icons, true) ? $_POST['icon'] : '📘';

    if ($action === 'create') {                          // INSERT
        if ($name === '') { $msg = 'กรุณากรอกชื่อหมวดหมู่'; $msgType = 'err'; }
        else {
            $pdo->prepare('INSERT INTO categories (user_id, name, color, icon) VALUES (?,?,?,?)')
                ->execute([$user['id'], $name, $color, $icon]);
            $msg = "เพิ่มหมวด “{$name}” แล้ว";
        }
    }
    if ($action === 'update') {                          // UPDATE
        $pdo->prepare('UPDATE categories SET name=?, color=?, icon=? WHERE id=? AND user_id=?')
            ->execute([$name, $color, $icon, (int)$_POST['id'], $user['id']]);
        $msg = 'แก้ไขหมวดหมู่แล้ว';
    }
    if ($action === 'archive') {                         // ซ่อน
        $pdo->prepare('UPDATE categories SET archived=1 WHERE id=? AND user_id=?')
            ->execute([(int)$_POST['id'], $user['id']]);
        $msg = 'ซ่อนหมวดหมู่แล้ว';
    }
    if ($action === 'delete') {                          // DELETE
        $pdo->prepare('DELETE FROM categories WHERE id=? AND user_id=?')
            ->execute([(int)$_POST['id'], $user['id']]);
        $msg = 'ลบหมวดหมู่ถาวรแล้ว';
    }
}

$st = $pdo->prepare(
    "SELECT c.*, COUNT(s.id) AS n_sessions,
            COALESCE(SUM(TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at) - s.away_seconds),0) AS total_sec
     FROM categories c
     LEFT JOIN sessions s ON s.category_id = c.id AND s.ended_at IS NOT NULL
     WHERE c.user_id = ?
     GROUP BY c.id ORDER BY c.archived, c.sort_order, c.id"
);
$st->execute([$user['id']]);
$cats = $st->fetchAll();

require __DIR__ . '/inc/header.php';
?>

<h1 class="page-title">จัดการหมวดหมู่</h1>

<?php if ($msg): ?>
  <div class="alert <?= $msgType === 'ok' ? 'alert-ok' : '' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<section class="panel">
  <h2>เพิ่มหมวดหมู่ใหม่</h2>
  <form method="post" class="rowform">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field grow">
      <label for="name">ชื่อหมวดหมู่</label>
      <input id="name" type="text" name="name" required maxlength="60"
             placeholder="เช่น คณิตศาสตร์, อ่านนิยาย, ทำการบ้าน">
    </div>
    <div class="field">
      <label for="color">สี</label>
      <input id="color" type="color" name="color" value="#FF7A2F">
    </div>
    <div class="field">
      <label for="icon">ไอคอน</label>
      <select id="icon" name="icon" class="icon-select">
        <?php foreach ($icons as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><button class="btn" type="submit">+ เพิ่ม</button></div>
  </form>
</section>

<section class="panel">
  <h2>หมวดหมู่ทั้งหมด (<?= count($cats) ?>)</h2>
  <div class="cat-grid">
    <?php foreach ($cats as $c): ?>
      <div class="cat-card<?= (int)$c['archived'] ? ' archived' : '' ?>" style="--chip:<?= e($c['color']) ?>">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <div class="cat-head">
            <select name="icon" class="icon-select">
              <?php foreach ($icons as $ic): ?>
                <option value="<?= $ic ?>" <?= $c['icon'] === $ic ? 'selected' : '' ?>><?= $ic ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="60">
            <input type="color" name="color" value="<?= e($c['color']) ?>">
          </div>
          <p class="muted"><?= (int)$c['n_sessions'] ?> เซสชัน · รวม <?= fmt_hm((int)$c['total_sec']) ?></p>
          <div class="cat-actions"><button class="btn btn-mini" type="submit">บันทึก</button></div>
        </form>

        <div class="cat-actions">
          <?php if (!(int)$c['archived']): ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="archive">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn-ghost" type="submit">ซ่อน</button>
            </form>
          <?php endif; ?>
          <form method="post" style="display:inline"
                onsubmit="return confirm('ลบถาวร? ประวัติที่ใช้หมวดนี้จะกลายเป็น ไม่ระบุ')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-mini btn-del" type="submit">ลบ</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>