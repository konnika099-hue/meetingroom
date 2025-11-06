<?php
require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="text-center py-8"><p class="text-red-500">ไม่พบข้อมูลห้องประชุม</p></div>';
    exit;
}

$room_id = (int)$_GET['id'];
$conn = getDBConnection();

// ดึงข้อมูลห้องประชุม
$query = "SELECT * FROM meeting_rooms WHERE id = ? AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if (!$room) {
    echo '<div class="text-center py-8"><p class="text-red-500">ไม่พบข้อมูลห้องประชุม</p></div>';
    exit;
}

// ดึงข้อมูลการจองล่าสุด 5 รายการ
$booking_query = "SELECT b.*, DATE_FORMAT(b.booking_date, '%d/%m/%Y') as thai_date,
                         TIME_FORMAT(b.start_time, '%H:%i') as start_time_format,
                         TIME_FORMAT(b.end_time, '%H:%i') as end_time_format
                  FROM bookings b 
                  WHERE b.room_id = ? 
                  ORDER BY b.booking_date DESC, b.start_time DESC 
                  LIMIT 5";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$bookings_result = $stmt->get_result();
$recent_bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);

// ดึงสถิติการจอง
$stats_query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                    SUM(CASE WHEN booking_date = CURDATE() THEN 1 ELSE 0 END) as today_bookings
                FROM bookings 
                WHERE room_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<div class="space-y-6">
    <!-- Room Image and Basic Info -->
    <div class="flex flex-col lg:flex-row gap-6">
        <div class="lg:w-1/2">
            <img src="<?php echo escapeOutput($room['image_path'] ?? 'https://via.placeholder.com/500x300/e5e7eb/6b7280?text=ไม่มีรูปภาพ'); ?>" 
                 alt="<?php echo escapeOutput($room['room_name']); ?>" 
                 class="w-full h-64 lg:h-80 object-cover rounded-lg shadow-sm">
        </div>
        
        <div class="lg:w-1/2 space-y-4">
            <div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php echo escapeOutput($room['room_name']); ?></h3>
                <p class="text-gray-600 leading-relaxed"><?php echo nl2br(escapeOutput($room['description'])); ?></p>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="users" class="w-5 h-5 text-blue-600"></i>
                        <span class="text-sm text-gray-600">จำนวนที่นั่ง</span>
                    </div>
                    <p class="text-2xl font-bold text-blue-600 mt-1"><?php echo escapeOutput($room['capacity']); ?></p>
                </div>
                
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="calendar-check" class="w-5 h-5 text-green-600"></i>
                        <span class="text-sm text-gray-600">การจองทั้งหมด</span>
                    </div>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?php echo $stats['total_bookings']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-orange-50 rounded-lg p-4 text-center">
            <i data-lucide="clock" class="w-6 h-6 text-orange-600 mx-auto mb-2"></i>
            <p class="text-sm text-gray-600">รอยืนยัน</p>
            <p class="text-xl font-bold text-orange-600"><?php echo $stats['pending_bookings']; ?></p>
        </div>
        
        <div class="bg-blue-50 rounded-lg p-4 text-center">
            <i data-lucide="check-circle" class="w-6 h-6 text-blue-600 mx-auto mb-2"></i>
            <p class="text-sm text-gray-600">ยืนยันแล้ว</p>
            <p class="text-xl font-bold text-blue-600"><?php echo $stats['confirmed_bookings']; ?></p>
        </div>
        
        <div class="bg-purple-50 rounded-lg p-4 text-center">
            <i data-lucide="calendar-days" class="w-6 h-6 text-purple-600 mx-auto mb-2"></i>
            <p class="text-sm text-gray-600">จองวันนี้</p>
            <p class="text-xl font-bold text-purple-600"><?php echo $stats['today_bookings']; ?></p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4 text-center">
            <i data-lucide="trending-up" class="w-6 h-6 text-gray-600 mx-auto mb-2"></i>
            <p class="text-sm text-gray-600">สถานะ</p>
            <p class="text-sm font-semibold text-green-600"><?php echo $room['status'] == 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?></p>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="bg-gray-50 rounded-lg p-6">
        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <i data-lucide="history" class="w-5 h-5 mr-2"></i>
            การจองล่าสุด
        </h4>
        
        <?php if (!empty($recent_bookings)): ?>
            <div class="space-y-3">
                <?php foreach ($recent_bookings as $booking): ?>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-1">
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
                                <p class="text-sm text-gray-600"><?php echo escapeOutput($booking['purpose']); ?></p>
                            </div>
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <div class="flex items-center space-x-1">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <span><?php echo $booking['thai_date']; ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    <span><?php echo $booking['start_time_format']; ?> - <?php echo $booking['end_time_format']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <i data-lucide="calendar-x" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                <p class="text-gray-500">ยังไม่มีการจองในห้องนี้</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-3 pt-4">
        <button onclick="viewCalendar(<?php echo $room['id']; ?>); closeModal('roomDetailsModal');" 
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
            <i data-lucide="calendar" class="w-5 h-5"></i>
            <span>ดูปฏิทินการจอง</span>
        </button>
        
        <button onclick="window.open('booking_form.php?room_id=<?php echo $room['id']; ?>', '_blank')" 
                class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
            <i data-lucide="plus" class="w-5 h-5"></i>
            <span>จองห้องนี้</span>
        </button>
    </div>
</div>