<?php
// logout.php
// สำหรับออกจากระบบและทำลาย Session

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ลบตัวแปร Session ทั้งหมด
$_SESSION = [];

// ทำลาย Session
session_destroy();

// นำกลับไปยังหน้าเข้าสู่ระบบ
header('Location: login.php');
exit();
?>
