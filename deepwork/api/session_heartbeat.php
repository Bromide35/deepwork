<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';

$user = require_login_api();
$in   = body_json();

$sid  = (int)($in['session_id'] ?? 0);
$away = max(0, (int)($in['away_seconds'] ?? 0));
$blur = max(0, (int)($in['blur_count'] ?? 0));

db()->prepare(
    'UPDATE sessions
     SET last_heartbeat_at = UTC_TIMESTAMP(), away_seconds = ?, blur_count = ?
     WHERE id = ? AND user_id = ? AND ended_at IS NULL'
)->execute([$away, $blur, $sid, $user['id']]);

json_out(['ok' => true]);