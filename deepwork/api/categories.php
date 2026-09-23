<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';

$user = require_login_api();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = $pdo->prepare('SELECT id, name, color, icon FROM categories
                         WHERE user_id = ? AND archived = 0 ORDER BY sort_order, id');
    $st->execute([$user['id']]);
    json_out(['ok' => true, 'items' => $st->fetchAll()]);
}

if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) json_out(['error' => 'csrf'], 419);

$in    = body_json();
$name  = trim((string)($in['name'] ?? ''));
$color = preg_match('/^#[0-9a-f]{6}$/i', $in['color'] ?? '') ? $in['color'] : '#FF7A2F';

if ($name === '' || mb_strlen($name) > 60) json_out(['error' => 'invalid_name'], 422);

$pdo->prepare('INSERT INTO categories (user_id, name, color) VALUES (?,?,?)')
    ->execute([$user['id'], $name, $color]);

json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'name' => $name, 'color' => $color]);