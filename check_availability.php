<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['available' => false, 'message' => 'Invalid request method']);
    exit;
}

$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$date = isset($_POST['date']) ? sanitizeInput($_POST['date']) : '';
$start_time = isset($_POST['start_time']) ? sanitizeInput($_POST['start_time']) : '';
$end_time = isset($_POST['end_time']) ? sanitizeInput($_POST['end_time']) : '';

// ตรวจสอบข้อมูลพื้นฐาน
if ($room_id <= 0 || empty($date) || empty($start_time) || empty($end_time)) {
    echo json_encode(['available' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสอบรูปแบบวันที่
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['available' => false, 'message' => 'รูปแบบวันที่ไม่ถูกต้อง']);
    exit;
}

// ตรวจสอบรูปแบบเวลา
if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    echo json_encode(['available' => false, 'message' => 'รูปแบบเวลาไม่ถูกต้อง']);
    exit;
}

// ตรวจสอบว่าเวลาเริ่มต้นน้อยกว่าเวลาสิ้นสุด
if ($start_time >= $end_time) {
    echo json_encode(['available' => false, 'message' => 'เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด']);
    exit;
}

// ตรวจสอบว่าวันที่ไม่ใช่วันที่ผ่านมาแล้ว
if ($date < date('Y-m-d')) {
    echo json_encode(['available' => false, 'message' => 'ไม่สามารถจองวันที่ผ่านมาแล้วได้']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // ตรวจสอบว่าห้องประชุมมีอยู่จริงและเปิดใช้งาน
    $room_query = "SELECT room_name FROM meeting_rooms WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($room_query);
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $room_result = $stmt->get_result();
    
    if ($room_result->num_rows === 0) {
        echo json_encode(['available' => false, 'message' => 'ไม่พบห้องประชุมหรือห้องไม่เปิดใช้งาน']);
        exit;
    }
    
    $room = $room_result->fetch_assoc();
    
    // ตรวจสอบการจองที่ซ้ำกัน
    $check_query = "SELECT booker_name, 
                           TIME_FORMAT(start_time, '%H:%i') as start_time_format,
                           TIME_FORMAT(end_time, '%H:%i') as end_time_format,
                           status
                    FROM bookings 
                    WHERE room_id = ? AND booking_date = ? 
                    AND status IN ('confirmed', 'pending')
                    AND ((start_time <= ? AND end_time > ?) 
                         OR (start_time < ? AND end_time >= ?)
                         OR (start_time >= ? AND end_time <= ?))
                    ORDER BY start_time";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('isssssss', $room_id, $date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
    $stmt->execute();
    $conflict_result = $stmt->get_result();
    
    if ($conflict_result->num_rows > 0) {
        $conflicts = $conflict_result->fetch_all(MYSQLI_ASSOC);
        $conflict_messages = [];
        
        foreach ($conflicts as $conflict) {
            $status_text = $conflict['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 'รอยืนยัน';
            $conflict_messages[] = sprintf(
                '%s (%s-%s) โดย %s',
                $status_text,
                $conflict['start_time_format'],
                $conflict['end_time_format'],
                $conflict['booker_name']
            );
        }
        
        echo json_encode([
            'available' => false, 
            'message' => 'มีการจองซ้ำ: ' . implode(', ', $conflict_messages)
        ]);
        exit;
    }
    
    // ดึงการจองอื่นๆ ในวันเดียวกันเพื่อแสดงข้อมูลเพิ่มเติม
    $other_bookings_query = "SELECT TIME_FORMAT(start_time, '%H:%i') as start_time_format,
                                    TIME_FORMAT(end_time, '%H:%i') as end_time_format,
                                    booker_name, status
                             FROM bookings 
                             WHERE room_id = ? AND booking_date = ? 
                             AND status IN ('confirmed', 'pending')
                             ORDER BY start_time";
    
    $stmt = $conn->prepare($other_bookings_query);
    $stmt->bind_param('is', $room_id, $date);
    $stmt->execute();
    $other_result = $stmt->get_result();
    $other_bookings = $other_result->fetch_all(MYSQLI_ASSOC);
    
    $conn->close();
    
    // หาช่วงเวลาที่แนะนำ
    $recommended_times = [];
    $business_hours = [
        ['08:00', '12:00'],
        ['13:00', '17:00'],
        ['18:00', '20:00']
    ];
    
    foreach ($business_hours as $hours) {
        $period_start = $hours[0];
        $period_end = $hours[1];
        
        // ตรวจสอบว่าช่วงเวลานี้ว่างหรือไม่
        $is_free = true;
        foreach ($other_bookings as $booking) {
            $booking_start = $booking['start_time_format'];
            $booking_end = $booking['end_time_format'];
            
            if (($period_start >= $booking_start && $period_start < $booking_end) ||
                ($period_end > $booking_start && $period_end <= $booking_end) ||
                ($period_start <= $booking_start && $period_end >= $booking_end)) {
                $is_free = false;
                break;
            }
        }
        
        if ($is_free) {
            $recommended_times[] = $hours;
        }
    }
    
    echo json_encode([
        'available' => true,
        'message' => 'ช่วงเวลานี้ว่างสามารถจองได้',
        'room_name' => $room['room_name'],
        'other_bookings' => $other_bookings,
        'recommended_times' => $recommended_times
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'available' => false, 
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}
?>