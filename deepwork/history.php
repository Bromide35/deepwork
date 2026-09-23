<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$user      = require_login();
$pdo       = db();
$pageTitle = 'ประวัติการโฟกัส';
$msg = ''; $msgType = 'ok';

/* ================= UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $msg = 'เซสชันหมดอายุ'; $msgType = 'err';
    } else {
        $id    = (int)($_POST['id'] ?? 0);
        $catId = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        $note  = mb_substr(trim((string)$_POST['note']), 0, 255);
        $tz    = new DateTimeZone($user['timezone']);
        try {
            $start = new DateTimeImmutable($_POST['started_at'], $tz);
            $end   = new DateTimeImmutable($_POST['ended_at'],   $tz);
            $total = $end->getTimestamp() - $start->getTimestamp();

            if ($total <= 0) {
                $msg = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม'; $msgType = 'err';
            } elseif ($total > 86400) {
                $msg = 'เซสชันเดียวยาวเกิน 24 ชั่วโมงไม่ได้'; $msgType = 'err';
            } else {
                $uStart = $start->setTimezone(new DateTimeZone('UTC'));
                $uEnd   = $end->setTimezone(new DateTimeZone('UTC'));
                $away   = max(0, min((int)$_POST['away_seconds'], $total));
                $score  = calc_focus_score($total, $away, (int)$_POST['blur_count']);
                $bucket = day_bucket($uStart, $user['timezone'], (int)$user['day_start_hour']);

                $pdo->prepare(
                    'UPDATE sessions
                     SET category_id=?, started_at=?, ended_at=?, away_seconds=?,
                         focus_score=?, note=?, day_bucket=?, is_edited=1
                     WHERE id=? AND user_id=?'
                )->execute([
                    $catId, $uStart->format('Y-m-d H:i:s'), $uEnd->format('Y-m-d H:i:s'),
                    $away, $score, $note, $bucket, $id, $user['id']
                ]);
                $msg = 'แก้ไขข้อมูลเรียบร้อยแล้ว';
            }
        } catch (Exception $ex) {
            $msg = 'รูปแบบวันที่ไม่ถูกต้อง'; $msgType = 'err';
        }
    }
}

/* ================= DELETE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_check($_POST['csrf'] ?? null)) {
        $pdo->prepare('DELETE FROM sessions WHERE id = ? AND user_id = ?')
            ->execute([(int)$_POST['id'], $user['id']]);
        $msg = 'ลบเซสชันแล้ว';
    }
}

/* ================= SELECT + แบ่งหน้า ================= */
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$cnt = $pdo->prepare('SELECT COUNT(*) c FROM sessions WHERE user_id=? AND ended_at IS NOT NULL');
$cnt->execute([$user['id']]);
$totalRows  = (int)$cnt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

/* ★ do...while สร้างเลขหน้า */
$pageNums = []; $p = 1;
do { $pageNums[] = $p; $p++; } while ($p <= $totalPages);

$st = $pdo->prepare(
    "SELECT s.*, c.name AS cat_name, c.color AS cat_color, c.icon AS cat_icon,
            TIMESTAMPDIFF(SECOND, s.started_at, s.ended_at) AS total_sec
     FROM sessions s
     LEFT JOIN categories c ON c.id = s.category_id
     WHERE s.user_id = ? AND s.ended_at IS NOT NULL
     ORDER BY s.started_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$st->execute([$user['id']]);
$rows = $st->fetchAll();

$cl = $pdo->prepare('SELECT id, name, icon FROM categories WHERE user_id=? AND archived=0 ORDER BY sort_order');
$cl->execute([$user['id']]);
$catOptions = $cl->fetchAll();

$tz = new DateTimeZone($user['timezone']);
require __DIR__ . '/inc/header.php';
?>

<h1 class="page-title">ประวัติการโฟกัส</h1>
<p class="muted">ทั้งหมด <?= $totalRows ?> เซสชัน · หน้า <?= $page ?>/<?= $totalPages ?></p>

<?php if ($msg): ?>
  <div class="alert <?= $msgType === 'ok' ? 'alert-ok' : '' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="empty">
    <img src="<?= url('assets/img/empty.svg') ?>" alt="ยังไม่มีข้อมูล" width="120">
    <p>ยังไม่มีประวัติ — <a href="<?= url('countdown.php') ?>">เริ่มจับเวลาเลย</a></p>
  </div>
<?php else: ?>

<table class="table">
  <thead>
    <tr><th>วันที่</th><th>หมวด</th><th>โหมด</th><th>ระยะเวลา</th><th>Score</th><th>บันทึก</th><th>จัดการ</th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r):
      $s     = (new DateTimeImmutable($r['started_at'], new DateTimeZone('UTC')))->setTimezone($tz);
      $eTime = (new DateTimeImmutable($r['ended_at'],   new DateTimeZone('UTC')))->setTimezone($tz);
      $focus = (int)$r['total_sec'] - (int)$r['away_seconds'];
      $lb    = score_label((int)$r['focus_score']); ?>
    <tr>
      <td><?= $s->format('j/n/') . ($s->format('Y') + 543) ?><br>
          <span class="muted"><?= $s->format('H:i') ?>–<?= $eTime->format('H:i') ?></span></td>
      <td><?php if ($r['cat_name']): ?>
            <span class="tag" style="--chip:<?= e($r['cat_color']) ?>"><?= e($r['cat_icon'].' '.$r['cat_name']) ?></span>
          <?php else: ?><span class="muted">—</span><?php endif; ?></td>
      <td><?= e(mode_thai($r['mode'])) ?></td>
      <td><strong><?= fmt_clock(max(0,$focus)) ?></strong>
          <?php if ((int)$r['blur_count'] > 0): ?><br><span class="muted">หลุด <?= (int)$r['blur_count'] ?> ครั้ง</span><?php endif; ?></td>
      <td class="<?= $lb['class'] ?>"><?= (int)$r['focus_score'] ?></td>
      <td><?= $r['note'] ? e($r['note']) : '<span class="muted">—</span>' ?>
          <?php if ((int)$r['is_edited'] === 1): ?><span class="badge">แก้ไขแล้ว</span><?php endif; ?></td>
      <td>
        <button class="btn btn-mini" type="button" onclick="toggleEdit(<?= (int)$r['id'] ?>)">แก้ไข</button>
        <form method="post" onsubmit="return confirm('ยืนยันลบเซสชันนี้?')" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-mini btn-del" type="submit">ลบ</button>
        </form>
      </td>
    </tr>

    <tr id="edit-<?= (int)$r['id'] ?>" hidden>
      <td colspan="7">
        <form method="post" class="editform">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="blur_count" value="<?= (int)$r['blur_count'] ?>">

          <div class="field"><label>เวลาเริ่ม</label>
            <input type="datetime-local" name="started_at" required value="<?= $s->format('Y-m-d\TH:i') ?>"></div>
          <div class="field"><label>เวลาสิ้นสุด</label>
            <input type="datetime-local" name="ended_at" required value="<?= $eTime->format('Y-m-d\TH:i') ?>"></div>

          <div class="field"><label>หมวดหมู่</label>
            <select name="category_id">
              <option value="">— ไม่ระบุ —</option>
              <?php foreach ($catOptions as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$r['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= e($c['icon'].' '.$c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select></div>

          <div class="field"><label>เวลาหลุดโฟกัส (วินาที)</label>
            <input type="number" name="away_seconds" min="0" value="<?= (int)$r['away_seconds'] ?>"></div>

          <div class="field grow"><label>บันทึกย่อ</label>
            <input type="text" name="note" maxlength="255" value="<?= e($r['note']) ?>"
                   placeholder="เช่น อ่านบทที่ 3 จบ"></div>

          <div class="field">
            <button class="btn btn-mini" type="submit">บันทึกการแก้ไข</button>
          </div>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
  <nav class="pager">
    <?php foreach ($pageNums as $n): ?>
      <a href="?page=<?= $n ?>" class="pagelink<?= $n === $page ? ' on' : '' ?>"><?= $n ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php endif; ?>

<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  row.hidden = !row.hidden;
}
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>