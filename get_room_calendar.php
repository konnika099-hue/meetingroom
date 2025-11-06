<?php
require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="text-center py-8"><p class="text-red-500">ไม่พบข้อมูลห้องประชุม</p></div>';
    exit;
}

$room_id = (int)$_GET['id'];
$conn = getDBConnection();

// ดึงข้อมูลห้องประชุม
$query = "SELECT room_name FROM meeting_rooms WHERE id = ? AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if (!$room) {
    echo '<div class="text-center py-8"><p class="text-red-500">ไม่พบข้อมูลห้องประชุม</p></div>';
    exit;
}

// รับค่าเดือนและปีที่ต้องการแสดง (ค่าเริ่มต้นเป็นเดือนปัจจุบัน)
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// สร้างวันที่แรกและสุดท้ายของเดือน
$first_day = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$last_day = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));

// ดึงข้อมูลการจองในเดือนนี้
$booking_query = "SELECT booking_date, start_time, end_time, booker_name, purpose, status 
                  FROM bookings 
                  WHERE room_id = ? AND booking_date BETWEEN ? AND ? 
                  ORDER BY booking_date, start_time";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param('iss', $room_id, $first_day, $last_day);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = [];
while ($booking = $bookings_result->fetch_assoc()) {
    $date = $booking['booking_date'];
    if (!isset($bookings[$date])) {
        $bookings[$date] = [];
    }
    $bookings[$date][] = $booking;
}

$conn->close();

// สร้างปฏิทิน
$days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year));
$first_day_of_week = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));

// ชื่อเดือนภาษาไทย
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

$thai_year = $current_year + 543;

// คำนวณเดือนก่อนหน้าและถัดไป
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>

<div class="space-y-6">
    <!-- Calendar Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-xl font-bold text-gray-900"><?php echo escapeOutput($room['room_name']); ?></h3>
            <p class="text-gray-600"><?php echo $thai_months[$current_month]; ?> <?php echo $thai_year; ?></p>
        </div>
        
        <div class="flex items-center space-x-2">
            <button onclick="loadCalendar(<?php echo $room_id; ?>, <?php echo $prev_month; ?>, <?php echo $prev_year; ?>)" 
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
            
            <button onclick="loadCalendar(<?php echo $room_id; ?>, <?php echo date('n'); ?>, <?php echo date('Y'); ?>)" 
                    class="px-4 py-2 text-sm bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition-colors">
                วันนี้
            </button>
            
            <button onclick="loadCalendar(<?php echo $room_id; ?>, <?php echo $next_month; ?>, <?php echo $next_year; ?>)" 
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                <i data-lucide="chevron-right" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- Calendar -->
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <!-- Days of week header -->
        <div class="grid grid-cols-7 bg-gray-50">
            <?php 
            $days_thai = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
            foreach ($days_thai as $day): 
            ?>
                <div class="p-3 text-center text-sm font-medium text-gray-600"><?php echo $day; ?></div>
            <?php endforeach; ?>
        </div>
        
        <!-- Calendar grid -->
        <div class="grid grid-cols-7">
            <?php
            // เพิ่มช่องว่างสำหรับวันก่อนวันที่ 1
            for ($i = 0; $i < $first_day_of_week; $i++) {
                echo '<div class="h-20 lg:h-24 bg-gray-50 border-b border-r border-gray-200"></div>';
            }
            
            // แสดงวันที่ในเดือน
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                $is_today = ($date == date('Y-m-d'));
                $day_bookings = isset($bookings[$date]) ? $bookings[$date] : [];
                
                echo '<div class="h-20 lg:h-24 border-b border-r border-gray-200 p-1 relative ' . ($is_today ? 'bg-blue-50' : 'bg-white') . '">';
                
                // หมายเลขวันที่
                echo '<div class="text-sm font-medium ' . ($is_today ? 'text-blue-600' : 'text-gray-900') . '">' . $day . '</div>';
                
                // แสดงการจอง
                if (!empty($day_bookings)) {
                    $booking_count = count($day_bookings);
                    $confirmed_count = count(array_filter($day_bookings, function($b) { return $b['status'] == 'confirmed'; }));
                    
                    echo '<div class="mt-1 space-y-1">';
                    
                    // แสดงการจองเพียงบางส่วน (จำกัดที่ 2 รายการ)
                    $displayed = 0;
                    foreach ($day_bookings as $booking) {
                        if ($displayed >= 2) break;
                        
                        $status_color = $booking['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : 
                                       ($booking['status'] == 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700');
                        
                        echo '<div class="text-xs px-1 py-0.5 rounded ' . $status_color . ' truncate" title="' . 
                             escapeOutput($booking['booker_name']) . ' (' . 
                             date('H:i', strtotime($booking['start_time'])) . '-' . 
                             date('H:i', strtotime($booking['end_time'])) . ')">';
                        echo escapeOutput(substr($booking['booker_name'], 0, 8)) . '...';
                        echo '</div>';
                        
                        $displayed++;
                    }
                    
                    // แสดงจำนวนการจองเพิ่มเติม
                    if ($booking_count > 2) {
                        echo '<div class="text-xs text-gray-500">+' . ($booking_count - 2) . ' รายการ</div>';
                    }
                    
                    echo '</div>';
                    
                    // ปุ่มดูรายละเอียด
                    echo '<button onclick="showDayBookings(\'' . $date . '\', ' . $room_id . ')" 
                          class="absolute bottom-1 right-1 w-5 h-5 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition-colors flex items-center justify-center"
                          title="ดูรายละเอียด">
                          <i data-lucide="eye" class="w-3 h-3"></i>
                          </button>';
                } else {
                    // วันที่ไม่มีการจอง - แสดงปุ่มจอง
                    if ($date >= date('Y-m-d')) {
                        echo '<button onclick="window.open(\'booking_form.php?room_id=' . $room_id . '&date=' . $date . '\', \'_blank\')" 
                              class="absolute bottom-1 right-1 w-5 h-5 bg-green-600 text-white rounded text-xs hover:bg-green-700 transition-colors flex items-center justify-center"
                              title="จองห้อง">
                              <i data-lucide="plus" class="w-3 h-3"></i>
                              </button>';
                    }
                }
                
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center gap-4 text-sm">
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-green-100 border border-green-200 rounded"></div>
            <span class="text-gray-600">ยืนยันแล้ว</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-orange-100 border border-orange-200 rounded"></div>
            <span class="text-gray-600">รอยืนยัน</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-red-100 border border-red-200 rounded"></div>
            <span class="text-gray-600">ยกเลิก</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-blue-50 border border-blue-200 rounded"></div>
            <span class="text-gray-600">วันนี้</span>
        </div>
    </div>

    <!-- Quick Booking Button -->
    <div class="text-center">
        <button onclick="window.open('booking_form.php?room_id=<?php echo $room_id; ?>', '_blank')" 
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2 mx-auto">
            <i data-lucide="calendar-plus" class="w-5 h-5"></i>
            <span>จองห้องประชุมนี้</span>
        </button>
    </div>
</div>

<!-- Day Bookings Modal -->
<div id="dayBookingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-y-auto animate-fadeIn">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 id="dayBookingsTitle" class="text-lg font-bold text-gray-900"></h3>
                <button onclick="closeDayBookingsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div id="dayBookingsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
    function loadCalendar(roomId, month, year) {
        showLoading();
        
        fetch(`get_room_calendar.php?id=${roomId}&month=${month}&year=${year}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('calendarContent').innerHTML = data;
                hideLoading();
                lucide.createIcons();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                alert('เกิดข้อผิดพลาดในการโหลดปฏิทิน');
            });
    }
    
    function showDayBookings(date, roomId) {
        showLoading();
        
        fetch(`get_day_bookings.php?date=${date}&room_id=${roomId}`)
            .then(response => response.text())
            .then(data => {
                const thaiDate = new Date(date).toLocaleDateString('th-TH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                });
                
                document.getElementById('dayBookingsTitle').textContent = `การจอง ${thaiDate}`;
                document.getElementById('dayBookingsContent').innerHTML = data;
                hideLoading();
                showDayBookingsModal();
                lucide.createIcons();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
            });
    }
    
    function showDayBookingsModal() {
        document.getElementById('dayBookingsModal').classList.remove('hidden');
        document.getElementById('dayBookingsModal').classList.add('flex');
    }
    
    function closeDayBookingsModal() {
        document.getElementById('dayBookingsModal').classList.add('hidden');
        document.getElementById('dayBookingsModal').classList.remove('flex');
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('dayBookingsModal');
        if (event.target === modal) {
            closeDayBookingsModal();
        }
    });
</script> 