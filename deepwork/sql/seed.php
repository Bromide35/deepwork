<?php
/**
 * สร้างข้อมูลย้อนหลัง 30 วัน สำหรับใช้เดโม่ให้กราฟสวย
 * ใช้:  http://localhost/deepwork/sql/seed.php?email=อีเมลที่สมัครไว้
 * ⚠️ ลบไฟล์นี้ก่อนส่งงาน
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

header('Content-Type: text/html; charset=utf-8');

$email = $_GET['email'] ?? '';
if ($email === '') exit('ใส่ ?email=... ด้วยครับ');

$pdo = db();
$st  = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$st->execute([$email]);
$user = $st->fetch();
if (!$user) exit('ไม่พบผู้ใช้อีเมลนี้');

$uid = (int)$user['id'];
$cs  = $pdo->prepare('SELECT id FROM categories WHERE user_id = ? AND archived = 0');
$cs->execute([$uid]);
$catIds = array_column($cs->fetchAll(), 'id');
if (!$catIds) exit('ผู้ใช้นี้ยังไม่มีหมวดหมู่');

$notes = ['อ่านบทที่ 3','ทำการบ้านคณิต','ทบทวนศัพท์','เขียนรายงาน','ติวสอบกลางภาค',''];
$modes = ['stopwatch','countdown','pomodoro'];
$tzUtc = new DateTimeZone('UTC');

$pdo->beginTransaction();
$ins = $pdo->prepare(
    'INSERT INTO sessions
       (user_id, category_id, mode, target_seconds, started_at, ended_at,
        last_heartbeat_at, away_seconds, blur_count, focus_score, completed, note, day_bucket)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
);

$count = 0;
for ($d = 29; $d >= 0; $d--) {
    if ($d % 7 === 3) continue;            // เว้นบางวันให้สมจริง
    $n = random_int(1, 4);
    $i = 0;
    while ($i < $n) {
        $i++;
        $start = (new DateTimeImmutable('now', new DateTimeZone($user['timezone'])))
                    ->modify("-{$d} day")->setTime(random_int(8,21), random_int(0,59), 0);
        $len   = random_int(20, 95);
        $end   = $start->modify("+{$len} minute");
        if ($end > new DateTimeImmutable('now', new DateTimeZone($user['timezone']))) continue;

        $total = $len * 60;
        $blur  = random_int(0, 6);
        $away  = min($blur > 0 ? random_int(20,90)*$blur : 0, (int)($total*0.3));
        $score = calc_focus_score($total, $away, $blur);
        $mode  = $modes[array_rand($modes)];
        $uS    = $start->setTimezone($tzUtc);
        $uE    = $end->setTimezone($tzUtc);

        $ins->execute([
            $uid, $catIds[array_rand($catIds)], $mode,
            $mode === 'countdown' ? $total : null,
            $uS->format('Y-m-d H:i:s'), $uE->format('Y-m-d H:i:s'), $uE->format('Y-m-d H:i:s'),
            $away, $blur, $score, random_int(0,10) > 2 ? 1 : 0,
            $notes[array_rand($notes)],
            day_bucket($uS, $user['timezone'], (int)$user['day_start_hour']),
        ]);
        $count++;
    }
}
$pdo->commit();

echo "<h2 style='font-family:sans-serif'>✅ สร้างข้อมูลตัวอย่างสำเร็จ {$count} เซสชัน</h2>";
echo "<p style='font-family:sans-serif'><a href='../dashboard.php'>→ ไปดู Dashboard</a></p>";
echo "<p style='font-family:sans-serif;color:#c00'>⚠️ อย่าลืมลบไฟล์ seed.php ก่อนส่งงาน</p>";