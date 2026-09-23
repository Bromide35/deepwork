<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const MAX_ATTEMPTS = 8;
const LOCK_MINUTES = 15;

function current_user(): ?array
{
    if (empty($_SESSION['uid'])) return null;

    static $cache = null;
    if ($cache !== null) return $cache;

    $st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([$_SESSION['uid']]);
    $cache = $st->fetch() ?: null;

    if (!$cache) { session_destroy(); return null; }
    return $cache;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) redirect(url('login.php'));
    return $u;
}

function require_login_api(): array
{
    $u = current_user();
    if (!$u) json_out(['error' => 'unauthorized'], 401);
    return $u;
}

/* ================= สมัครสมาชิก (INSERT) ================= */
function register_user(string $email, string $name, string $pass): array
{
    $email = mb_strtolower(trim($email));
    $name  = trim($name);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'อีเมลไม่ถูกต้อง'];
    if (mb_strlen($name) < 2)                       return ['ok' => false, 'error' => 'ชื่อสั้นเกินไป'];
    if (strlen($pass) < 8)                          return ['ok' => false, 'error' => 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัว'];

    $pdo = db();
    $st  = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) return ['ok' => false, 'error' => 'อีเมลนี้ถูกใช้แล้ว'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?,?,?)')
            ->execute([$email, $name, password_hash($pass, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();

        /* ★ for loop สร้างหมวดเริ่มต้น (เกณฑ์ข้อ 3) */
        $defaults = [
            ['อ่านหนังสือ', '#FF7A2F', '📘'],
            ['ทำงาน',       '#4A9E7F', '💻'],
            ['ทบทวน',       '#E0A83C', '✍️'],
        ];
        $cat = $pdo->prepare('INSERT INTO categories (user_id, name, color, icon, sort_order) VALUES (?,?,?,?,?)');
        for ($i = 0; $i < count($defaults); $i++) {
            $cat->execute([$uid, $defaults[$i][0], $defaults[$i][1], $defaults[$i][2], $i]);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'สมัครไม่สำเร็จ ลองใหม่อีกครั้ง'];
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    return ['ok' => true];
}

/* ================= เข้าสู่ระบบ ================= */
function login_user(string $email, string $pass): array
{
    $email = mb_strtolower(trim($email));
    $pdo   = db();
    $ip    = client_ip();

    $chk = $pdo->prepare(
        'SELECT COUNT(*) c FROM login_attempts
         WHERE ip = ? AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)'
    );
    $chk->execute([$ip, LOCK_MINUTES]);
    if ((int)$chk->fetch()['c'] >= MAX_ATTEMPTS) {
        return ['ok' => false, 'error' => 'พยายามเข้าสู่ระบบบ่อยเกินไป กรุณารอ ' . LOCK_MINUTES . ' นาที'];
    }

    $st = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        $pdo->prepare('INSERT INTO login_attempts (ip, email) VALUES (?,?)')->execute([$ip, $email]);
        return ['ok' => false, 'error' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'];
    }

    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    return ['ok' => true];
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}