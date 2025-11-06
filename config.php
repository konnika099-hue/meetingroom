<?php
// การกำหนดค่าฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'meeting_room_booking');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// การเชื่อมต่อฐานข้อมูล
function getDBConnection() {
    try {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connection->connect_error) {
            throw new Exception("Connection failed: " . $connection->connect_error);
        }
        
        $connection->set_charset(DB_CHARSET);
        return $connection;
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// ฟังก์ชันสำหรับป้องกัน SQL Injection
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// ฟังก์ชันสำหรับป้องกัน XSS
function escapeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// ฟังก์ชันแค่นวณวันที่ภาษาไทย
function getThaiDate($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $thai_days = [
        'Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร',
        'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $thai_months[date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543;
    $day_name = $thai_days[date('l', $timestamp)];
    
    return "วัน{$day_name}ที่ {$day} {$month} {$year}";
}

// ฟังก์ชันแปลงวันที่เป็นปีไทย
function convertToThaiYear($date) {
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp) + 543;
    
    return "{$day}/{$month}/{$year}";
}

// เริ่มต้น session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า timezone เป็นเวลาไทย
date_default_timezone_set('Asia/Bangkok');

// การกำหนดค่าการอัพโหลดไฟล์
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// ฟังก์ชันสำหรับอัพโหลดและปรับขนาดรูปภาพ
function uploadAndResizeImage($file, $max_width = 800, $max_height = 600) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = UPLOAD_PATH . $new_filename;
    
    // สร้างโฟลเดอร์ถ้ายังไม่มี
    if (!file_exists(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }
    
    // ตรวจสอบว่า GD extension ติดตั้งหรือไม่
    if (!extension_loaded('gd')) {
        // ถ้าไม่มี GD extension ให้ copy ไฟล์ธรรมดา
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return $upload_path;
        } else {
            return false;
        }
    }
    
    // อ่านไฟล์รูปภาพ
    $source = false;
    switch ($file_extension) {
        case 'jpg':
        case 'jpeg':
            if (function_exists('imagecreatefromjpeg')) {
                $source = imagecreatefromjpeg($file['tmp_name']);
            }
            break;
        case 'png':
            if (function_exists('imagecreatefrompng')) {
                $source = imagecreatefrompng($file['tmp_name']);
            }
            break;
        case 'gif':
            if (function_exists('imagecreatefromgif')) {
                $source = imagecreatefromgif($file['tmp_name']);
            }
            break;
        default:
            return false;
    }
    
    // ถ้าไม่สามารถสร้าง image resource ได้ ให้ copy ไฟล์ธรรมดา
    if (!$source) {
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            return $upload_path;
        } else {
            return false;
        }
    }
    
    // ได้ขนาดรูปภาพต้นฉบับ
    $original_width = imagesx($source);
    $original_height = imagesy($source);
    
    // คำนวณขนาดใหม่
    $ratio = min($max_width / $original_width, $max_height / $original_height);
    $new_width = intval($original_width * $ratio);
    $new_height = intval($original_height * $ratio);
    
    // สร้างรูปภาพใหม่
    $resized = imagecreatetruecolor($new_width, $new_height);
    
    // รักษาความโปร่งใสสำหรับ PNG และ GIF
    if ($file_extension == 'png' || $file_extension == 'gif') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefill($resized, 0, 0, $transparent);
    }
    
    // ปรับขนาดรูปภาพ
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    
    // บันทึกรูปภาพ
    $success = false;
    switch ($file_extension) {
        case 'jpg':
        case 'jpeg':
            if (function_exists('imagejpeg')) {
                $success = imagejpeg($resized, $upload_path, 85);
            }
            break;
        case 'png':
            if (function_exists('imagepng')) {
                $success = imagepng($resized, $upload_path);
            }
            break;
        case 'gif':
            if (function_exists('imagegif')) {
                $success = imagegif($resized, $upload_path);
            }
            break;
    }
    
    // ล้างหน่วยความจำ
    imagedestroy($source);
    imagedestroy($resized);
    
    return $success ? $upload_path : false;
}

// ฟังก์ชันตรวจสอบสิทธิ์ผู้ดูแลระบบ
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        header('Location: admin_login.php');
        exit();
    }
}

// ฟังก์ชันแสดงข้อความแจ้งเตือน
function showAlert($message, $type = 'info') {
    $_SESSION['alert_message'] = $message;
    $_SESSION['alert_type'] = $type;
}

function getAlert() {
    if (isset($_SESSION['alert_message'])) {
        $message = $_SESSION['alert_message'];
        $type = $_SESSION['alert_type'] ?? 'info';
        unset($_SESSION['alert_message'], $_SESSION['alert_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}
?>