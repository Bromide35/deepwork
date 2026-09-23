<?php
declare(strict_types=1);

/* ===== ป้องกัน XSS ===== */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ===== ส่งผลลัพธ์แบบ JSON ให้ JavaScript ===== */
function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json(): array
{
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw ?: '[]', true);
    return is_array($d) ? $d : [];
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return inet_pton($ip) ?: inet_pton('0.0.0.0');
}

/* ===== CSRF Token ===== */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $t): bool
{
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/* ===== Focus Score — หัวใจของโปรเจกต์ ===== */
function calc_focus_score(int $totalSec, int $awaySec, int $blurCount): int
{
    if ($totalSec < 60) return 0;
    $hours   = $totalSec / 3600;
    $ratio   = max(0.0, ($totalSec - $awaySec) / $totalSec);
    $penalty = min(0.5, 0.04 * ($blurCount / max($hours, 0.25)));
    return (int)round(100 * $ratio * (1 - $penalty));
}

/* ===== ★ ใช้คำสั่ง switch (เกณฑ์ข้อ 3) ===== */
function score_label(int $score): array
{
    switch (true) {
        case ($score >= 90): return ['text' => 'Deep Work',  'class' => 'sc-great', 'emoji' => '🔥'];
        case ($score >= 75): return ['text' => 'Focused',    'class' => 'sc-good',  'emoji' => '✅'];
        case ($score >= 55): return ['text' => 'Distracted', 'class' => 'sc-mid',   'emoji' => '⚠️'];
        default:             return ['text' => 'Scattered',  'class' => 'sc-bad',   'emoji' => '💤'];
    }
}

/* ===== ★ switch อีกจุด ===== */
function mode_thai(string $mode): string
{
    switch ($mode) {
        case 'stopwatch': return 'จับเวลาขึ้น';
        case 'countdown': return 'นับถอยหลัง';
        case 'pomodoro':  return 'โพโมโดโร';
        default:          return 'ไม่ระบุ';
    }
}

/* ===== แปลงเวลาเป็น "วัน" ตามที่ผู้ใช้ตั้งไว้ ===== */
function day_bucket(DateTimeImmutable $utc, string $tz, int $startHour): string
{
    return $utc->setTimezone(new DateTimeZone($tz))
               ->sub(new DateInterval('PT' . $startHour . 'H'))
               ->format('Y-m-d');
}

/* ===== จัดรูปแบบเวลา ===== */
function fmt_hm(int $sec): string
{
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    return $h > 0 ? "{$h} ชม. {$m} น." : "{$m} นาที";
}

function fmt_clock(int $sec): string
{
    return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
}