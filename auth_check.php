<?php
// auth_check.php
// ระบบล็อกสิทธิ์การเข้าถึง สำหรับหน้าข้อมูลลับหน่วยงานความมั่นคง

// ป้องกันเบราว์เซอร์เก็บหน้าเว็บไว้ในแคช เพื่อรองรับการอัปเดตข้อมูลเมื่อกดย้อนกลับ (Back/Forward Cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าผู้ใช้ผ่านการล็อกอินแล้วหรือไม่
if (!isset($_SESSION['username'])) {
    // หากพยายามเข้าตรงๆ โดยไม่ได้ล็อกอิน ให้ส่งกลับไปหน้าล็อกอินทันที
    header('Location: login.php');
    exit();
}
?>
