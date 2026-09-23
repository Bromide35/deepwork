<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';

$user = require_login_api();
if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) json_out(['error' => 'csrf'], 419);

$in        = body_json();
$sid       = (int)($in['session_id'] ?? 0);
$away      = max(0, (int)($in['away_seconds'] ?? 0));
$blur      = max(0, (int)($in['blur_count'] ?? 0));
$completed = !empty($in['completed']) ? 1 : 0;

$pdo = db();
$st  = $pdo->prepare('SELECT * FROM sessions WHERE id = ? AND user_id = ? AND ended_at IS NULL');
$st->execute([$sid, $user['id']]);
$ses = $st->fetch();
if (!$ses) json_out(['error' => 'session_not_found'], 404);

$start = new DateTimeImmutable($ses['started_at'], new DateTimeZone('UTC'));
$end   = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$total = max(0, $end->getTimestamp() - $start->getTimestamp());
$away  = min($away, $total);
$score = calc_focus_score($total, $away, $blur);

$pdo->prepare(
    'UPDATE sessions
     SET ended_at = ?, away_seconds = ?, blur_count = ?, focus_score = ?,
         last_heartbeat_at = ?, completed = ?
     WHERE id = ? AND user_id = ?'
)->execute([
    $end->format('Y-m-d H:i:s'), $away, $blur, $score,
    $end->format('Y-m-d H:i:s'), $completed, $sid, $user['id']
]);

json_out([
    'ok'            => true,
    'total_seconds' => $total,
    'focus_seconds' => $total - $away,
    'focus_score'   => $score,
    'label'         => score_label($score),
]);