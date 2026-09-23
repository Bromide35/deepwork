<?php
declare(strict_types=1);

/* ========= ตั้งค่าหลัก ========= */
const APP_NAME = 'DeepWork';
const BASE_URL = '/deepwork';      // ชื่อโฟลเดอร์ใน htdocs

const DB_HOST = '127.0.0.1';
const DB_NAME = 'deepwork';
const DB_USER = 'root';
const DB_PASS = '';                // XAMPP ค่าเริ่มต้นคือว่าง

/* ========= เวลาภายในระบบใช้ UTC ========= */
date_default_timezone_set('UTC');

/* ========= เริ่ม session ========= */
if (session_status() === PHP_SESSION_NONE) {
    session_name('DWSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ========= สร้าง URL ========= */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}