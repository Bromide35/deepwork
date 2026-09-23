<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';

$user = require_login_api();
if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) json_out(['error' => 'csrf'], 419);

$in    = body_json();
$mode  = in_array($in['mode'] ?? '', ['stopwatch','countdown','pomodoro'], true) ? $in['mode'] : 'stopwatch';
$catId = !empty($in['category_id']) ? (int)$in['category_id'] : null;
$target= isset($in['target_seconds']) ? max(10, min(28800, (int)$in['target_seconds'])) : null;

$pdo = db();

if ($catId !== null) {
    $c = $pdo->prepare('SELECT 1 FROM categories WHERE id = ? AND user_id = ?');
    $c->execute([$catId, $user['id']]);
    if (!$c->fetch()) $catId = null;
}

/* ปิดเซสชันค้างที่ heartbeat ขาดเกิน 5 นาที */
$pdo->prepare(
    "UPDATE sessions
     SET ended_at = COALESCE(last_heartbeat_at, started_at), is_abandoned = 1
     WHERE user_id = ? AND ended_at IS NULL
       AND COALESCE(last_heartbeat_at, started_at) < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)"
)->execute([$user['id']]);

$now    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$bucket = day_bucket($now, $user['timezone'], (int)$user['day_start_hour']);

$pdo->prepare(
    'INSERT INTO sessions (user_id, category_id, mode, target_seconds, started_at, last_heartbeat_at, day_bucket)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    $user['id'], $catId, $mode, $target,
    $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), $bucket
]);

json_out([
    'ok'         => true,
    'session_id' => (int)$pdo->lastInsertId(),
    'started_at' => $now->format('c'),
    'server_now' => $now->format('c'),
]);