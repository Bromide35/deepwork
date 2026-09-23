<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';

$user = require_login_api();

$st = db()->prepare(
    'SELECT id, category_id, mode, started_at, away_seconds, blur_count
     FROM sessions WHERE user_id = ? AND ended_at IS NULL
     ORDER BY id DESC LIMIT 1'
);
$st->execute([$user['id']]);
$ses = $st->fetch();

if (!$ses) json_out(['active' => false]);

json_out([
    'active'       => true,
    'session_id'   => (int)$ses['id'],
    'category_id'  => $ses['category_id'] ? (int)$ses['category_id'] : null,
    'mode'         => $ses['mode'],
    'started_at'   => (new DateTimeImmutable($ses['started_at'], new DateTimeZone('UTC')))->format('c'),
    'away_seconds' => (int)$ses['away_seconds'],
    'blur_count'   => (int)$ses['blur_count'],
    'server_now'   => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
]);