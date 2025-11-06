<?php
require_once 'config.php';

// ลบ session ทั้งหมด
session_destroy();

// เปลี่ยนเส้นทางไปหน้า login
header('Location: admin_login.php');
exit();
?>