<?php
require_once 'config.php';

// ดึงข้อมูลห้องประชุมทั้งหมด
$conn = getDBConnection();
$query = "SELECT * FROM meeting_rooms WHERE status = 'active' ORDER BY id";
$result = $conn->query($query);
$rooms = $result->fetch_all(MYSQLI_ASSOC);

// ดึงข้อมูลการจองวันนี้
$today = date('Y-m-d');
$booking_query = "SELECT room_id, COUNT(*) as booking_count FROM bookings WHERE booking_date = ? AND status = 'confirmed' GROUP BY room_id";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param('s', $today);
$stmt->execute();
$booking_result = $stmt->get_result();
$today_bookings = [];
while ($row = $booking_result->fetch_assoc()) {
    $today_bookings[$row['room_id']] = $row['booking_count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจองห้องประชุม</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts - Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .modal {
            backdrop-filter: blur(5px);
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }
        
        .available {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .busy {
            background-color: #fef2f2;
            color: #dc2626;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Header -->
    <header class="hero-gradient text-white py-6 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <i data-lucide="calendar-check" class="w-8 h-8"></i>
                    <div>
                        <h1 class="text-2xl font-bold">ระบบจองห้องประชุม</h1>
                        <p class="text-blue-100 text-sm"><?php echo getThaiDate(); ?></p>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                            <span id="current-time" class="font-medium"></span>
                        </div>
                    </div>
                    <a href="admin_login.php" class="bg-white/20 hover:bg-white/30 transition-colors duration-200 backdrop-blur-sm rounded-lg px-4 py-2 flex items-center space-x-2">
                        <i data-lucide="settings" class="w-5 h-5"></i>
                        <span>ผู้ดูแลระบบ</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">ห้องประชุมทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo count($rooms); ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-lg p-3">
                        <i data-lucide="building" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">การจองวันนี้</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo array_sum($today_bookings); ?></p>
                    </div>
                    <div class="bg-green-100 rounded-lg p-3">
                        <i data-lucide="calendar-check" class="w-6 h-6 text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">ห้องว่างตอนนี้</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo count($rooms) - count($today_bookings); ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-lg p-3">
                        <i data-lucide="check-circle" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rooms Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($rooms as $room): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden card-hover">
                    <div class="relative">
                        <img src="<?php echo escapeOutput($room['image_path'] ?? 'https://via.placeholder.com/400x250/e5e7eb/6b7280?text=ไม่มีรูปภาพ'); ?>" 
                             alt="<?php echo escapeOutput($room['room_name']); ?>" 
                             class="w-full h-48 object-cover">
                        <div class="absolute top-4 right-4">
                            <span class="status-badge <?php echo isset($today_bookings[$room['id']]) ? 'busy' : 'available'; ?>">
                                <?php echo isset($today_bookings[$room['id']]) ? 'มีการจอง' : 'ว่าง'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2"><?php echo escapeOutput($room['room_name']); ?></h3>
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo escapeOutput($room['description']); ?></p>
                        
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-2 text-gray-500">
                                <i data-lucide="users" class="w-4 h-4"></i>
                                <span class="text-sm"><?php echo escapeOutput($room['capacity']); ?> ที่นั่ง</span>
                            </div>
                            <div class="flex items-center space-x-2 text-gray-500">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                <span class="text-sm"><?php echo isset($today_bookings[$room['id']]) ? $today_bookings[$room['id']] : '0'; ?> การจอง</span>
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button onclick="viewRoomDetails(<?php echo $room['id']; ?>)" 
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                <i data-lucide="info" class="w-4 h-4"></i>
                                <span>รายละเอียด</span>
                            </button>
                            <button onclick="viewCalendar(<?php echo $room['id']; ?>)" 
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                <span>ปฏิทิน</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($rooms)): ?>
            <div class="text-center py-12">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                    <i data-lucide="building" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ไม่มีห้องประชุม</h3>
                    <p class="text-gray-600">ยังไม่มีห้องประชุมในระบบ กรุณาติดต่อผู้ดูแลระบบ</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Room Details Modal -->
    <div id="roomDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-fadeIn">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">รายละเอียดห้องประชุม</h2>
                    <button onclick="closeModal('roomDetailsModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div id="roomDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Modal -->
    <div id="calendarModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal">
        <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-fadeIn">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">ปฏิทินการจอง</h2>
                    <button onclick="closeModal('calendarModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div id="calendarContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <span class="text-gray-700">กำลังโหลด...</span>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('th-TH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        updateTime();
        
        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
            document.body.style.overflow = 'auto';
        }
        
        function showLoading() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('loadingSpinner').classList.add('flex');
        }
        
        function hideLoading() {
            document.getElementById('loadingSpinner').classList.add('hidden');
            document.getElementById('loadingSpinner').classList.remove('flex');
        }
        
        // View room details
        function viewRoomDetails(roomId) {
            showLoading();
            
            fetch(`get_room_details.php?id=${roomId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('roomDetailsContent').innerHTML = data;
                    hideLoading();
                    showModal('roomDetailsModal');
                    lucide.createIcons();
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideLoading();
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }
        
        // View calendar
        function viewCalendar(roomId) {
            showLoading();
            
            fetch(`get_room_calendar.php?id=${roomId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('calendarContent').innerHTML = data;
                    hideLoading();
                    showModal('calendarModal');
                    lucide.createIcons();
                    
                    // Add event listener for day bookings modal
                    window.showDayBookings = function(date, roomId) {
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
                                
                                // Create day bookings modal if it doesn't exist
                                if (!document.getElementById('dayBookingsModal')) {
                                    const dayModal = document.createElement('div');
                                    dayModal.id = 'dayBookingsModal';
                                    dayModal.className = 'fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50';
                                    dayModal.innerHTML = `
                                        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-y-auto animate-fadeIn">
                                            <div class="p-6">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h3 id="dayBookingsTitle" class="text-lg font-bold text-gray-900"></h3>
                                                    <button onclick="closeDayBookingsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                                        <i data-lucide="x" class="w-5 h-5"></i>
                                                    </button>
                                                </div>
                                                <div id="dayBookingsContent"></div>
                                            </div>
                                        </div>
                                    `;
                                    document.body.appendChild(dayModal);
                                }
                                
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
                    };
                    
                    window.showDayBookingsModal = function() {
                        document.getElementById('dayBookingsModal').classList.remove('hidden');
                        document.getElementById('dayBookingsModal').classList.add('flex');
                    };
                    
                    window.closeDayBookingsModal = function() {
                        document.getElementById('dayBookingsModal').classList.add('hidden');
                        document.getElementById('dayBookingsModal').classList.remove('flex');
                    };
                    
                    window.loadCalendar = function(roomId, month, year) {
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
                    };
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideLoading();
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['roomDetailsModal', 'calendarModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = ['roomDetailsModal', 'calendarModal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (!modal.classList.contains('hidden')) {
                        closeModal(modalId);
                    }
                });
            }
        });
        
        // Smooth scroll animation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>