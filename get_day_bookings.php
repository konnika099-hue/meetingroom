<?php
require_once 'config.php';

if (!isset($_GET['date']) || !isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    echo '<div class="text-center py-4"><p class="text-red-500">ข้อมูลไม่ถูกต้อง</p></div>';
    exit;
}

$date = $_GET['date'];
$room_id = (int)$_GET['room_id'];

// ตรวจสอบรูปแบบวันที่
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo '<div class="text-center py-4"><p class="text-red-500">รูปแบบวันที่ไม่ถูกต้อง</p></div>';
    exit;
}

$conn = getDBConnection();

// ดึงข้อมูลห้องประชุม
$room_query = "SELECT room_name FROM meeting_rooms WHERE id = ? AND status = 'active'";
$stmt = $conn->prepare($room_query);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$room_result = $stmt->get_result();
$room = $room_result->fetch_assoc();

if (!$room) {
    echo '<div class="text-center py-4"><p class="text-red-500">ไม่พบข้อมูลห้องประชุม</p></div>';
    exit;
}

// ดึงข้อมูลการจองในวันที่เลือก
$booking_query = "SELECT *, 
                         TIME_FORMAT(start_time, '%H:%i') as start_time_format,
                         TIME_FORMAT(end_time, '%H:%i') as end_time_format
                  FROM bookings 
                  WHERE room_id = ? AND booking_date = ? 
                  ORDER BY start_time";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param('is', $room_id, $date);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

// แปลงวันที่เป็นภาษาไทย
$thai_date = getThaiDate($date);
?>

<div class="space-y-4">
    <?php if (!empty($bookings)): ?>
        <div class="space-y-3">
            <?php foreach ($bookings as $index => $booking): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="font-medium text-gray-900"><?php echo escapeOutput($booking['booker_name']); ?></span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    <?php 
                                    echo $booking['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : 
                                         ($booking['status'] == 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700');
                                    ?>">
                                    <?php 
                                    echo $booking['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 
                                         ($booking['status'] == 'pending' ? 'รอยืนยัน' : 'ยกเลิก');
                                    ?>
                                </span>
                            </div>
                            
                            <div class="space-y-1 text-sm text-gray-600">
                                <div class="flex items-center space-x-2">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    <span><?php echo $booking['start_time_format']; ?> - <?php echo $booking['end_time_format']; ?></span>
                                </div>
                                
                                <?php if ($booking['booker_email']): ?>
                                <div class="flex items-center space-x-2">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                    <span><?php echo escapeOutput($booking['booker_email']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['booker_phone']): ?>
                                <div class="flex items-center space-x-2">
                                    <i data-lucide="phone" class="w-4 h-4"></i>
                                    <span><?php echo escapeOutput($booking['booker_phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($booking['purpose']): ?>
                                <div class="flex items-start space-x-2">
                                    <i data-lucide="file-text" class="w-4 h-4 mt-0.5"></i>
                                    <span><?php echo nl2br(escapeOutput($booking['purpose'])); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-xs text-gray-400">
                            #<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Summary -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
            <div class="flex items-center justify-between text-sm">
                <span class="text-blue-700 font-medium">สรุปการจอง</span>
                <span class="text-blue-600">รวม <?php echo count($bookings); ?> รายการ</span>
            </div>
            
            <div class="mt-2 grid grid-cols-3 gap-4 text-xs">
                <div class="text-center">
                    <div class="text-green-600 font-semibold">
                        <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'confirmed'; })); ?>
                    </div>
                    <div class="text-gray-600">ยืนยันแล้ว</div>
                </div>
                <div class="text-center">
                    <div class="text-orange-600 font-semibold">
                        <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'pending'; })); ?>
                    </div>
                    <div class="text-gray-600">รอยืนยัน</div>
                </div>
                <div class="text-center">
                    <div class="text-red-600 font-semibold">
                        <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'cancelled'; })); ?>
                    </div>
                    <div class="text-gray-600">ยกเลิก</div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="text-center py-8">
            <div class="bg-gray-50 rounded-lg p-6">
                <i data-lucide="calendar-x" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                <h4 class="text-lg font-medium text-gray-900 mb-2">ไม่มีการจอง</h4>
                <p class="text-gray-600 mb-4">ในวันที่นี้ยังไม่มีการจองห้องประชุม</p>
                
                <?php if ($date >= date('Y-m-d')): ?>
                    <button onclick="window.open('booking_form.php?room_id=<?php echo $room_id; ?>&date=<?php echo $date; ?>', '_blank')" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 mx-auto">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>จองห้องในวันนี้</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Available Time Slots (สำหรับวันที่ยังมาไม่ถึง) -->
    <?php if ($date >= date('Y-m-d')): ?>
        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
            <h5 class="font-medium text-green-800 mb-2 flex items-center">
                <i data-lucide="clock" class="w-4 h-4 mr-2"></i>
                ช่วงเวลาที่แนะนำ
            </h5>
            
            <div class="grid grid-cols-2 gap-2 text-sm">
                <?php
                // แสดงช่วงเวลาแนะนำ
                $recommended_times = [
                    ['09:00', '12:00', 'เช้า'],
                    ['13:00', '16:00', 'บ่าย'],
                    ['16:00', '18:00', 'เย็น']
                ];
                
                foreach ($recommended_times as $time_slot):
                    $is_available = true;
                    // ตรวจสอบว่าช่วงเวลานี้ว่างหรือไม่
                    foreach ($bookings as $booking) {
                        if ($booking['status'] == 'confirmed' || $booking['status'] == 'pending') {
                            $booking_start = strtotime($booking['start_time']);
                            $booking_end = strtotime($booking['end_time']);
                            $slot_start = strtotime($time_slot[0] . ':00');
                            $slot_end = strtotime($time_slot[1] . ':00');
                            
                            if (($slot_start >= $booking_start && $slot_start < $booking_end) ||
                                ($slot_end > $booking_start && $slot_end <= $booking_end) ||
                                ($slot_start <= $booking_start && $slot_end >= $booking_end)) {
                                $is_available = false;
                                break;
                            }
                        }
                    }
                ?>
                    <div class="flex items-center justify-between bg-white rounded p-2">
                        <span class="text-gray-700"><?php echo $time_slot[2]; ?> (<?php echo $time_slot[0]; ?>-<?php echo $time_slot[1]; ?>)</span>
                        <span class="text-xs px-2 py-1 rounded <?php echo $is_available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo $is_available ? 'ว่าง' : 'ไม่ว่าง'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Action Button -->
    <div class="text-center pt-2">
        <?php if ($date >= date('Y-m-d')): ?>
            <button onclick="window.open('booking_form.php?room_id=<?php echo $room_id; ?>&date=<?php echo $date; ?>', '_blank')" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                <span>จองห้องในวันนี้</span>
            </button>
        <?php else: ?>
            <p class="text-gray-500 text-sm">วันที่ผ่านมาแล้ว ไม่สามารถจองได้</p>
        <?php endif; ?>
    </div>
</div>