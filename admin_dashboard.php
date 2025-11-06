<?php
require_once 'config.php';
checkAdminAuth();

$conn = getDBConnection();

// ดึงสถิติต่างๆ
$stats = [];

// จำนวนห้องประชุมทั้งหมด
$room_query = "SELECT COUNT(*) as total FROM meeting_rooms WHERE status = 'active'";
$result = $conn->query($room_query);
$stats['total_rooms'] = $result->fetch_assoc()['total'];

// จำนวนการจองทั้งหมด
$booking_query = "SELECT COUNT(*) as total FROM bookings";
$result = $conn->query($booking_query);
$stats['total_bookings'] = $result->fetch_assoc()['total'];

// จำนวนการจองวันนี้
$today_query = "SELECT COUNT(*) as today FROM bookings WHERE booking_date = CURDATE()";
$result = $conn->query($today_query);
$stats['today_bookings'] = $result->fetch_assoc()['today'];

// จำนวนการจองที่รอยืนยัน
$pending_query = "SELECT COUNT(*) as pending FROM bookings WHERE status = 'pending'";
$result = $conn->query($pending_query);
$stats['pending_bookings'] = $result->fetch_assoc()['pending'];

// การจองล่าสุด 5 รายการ
$recent_bookings_query = "SELECT b.*, r.room_name,
                                 DATE_FORMAT(b.booking_date, '%d/%m/%Y') as thai_date,
                                 TIME_FORMAT(b.start_time, '%H:%i') as start_time_format,
                                 TIME_FORMAT(b.end_time, '%H:%i') as end_time_format
                          FROM bookings b 
                          JOIN meeting_rooms r ON b.room_id = r.id 
                          ORDER BY b.created_at DESC 
                          LIMIT 5";
$recent_bookings = $conn->query($recent_bookings_query)->fetch_all(MYSQLI_ASSOC);

// ห้องที่มีการจองมากที่สุด
$popular_rooms_query = "SELECT r.room_name, COUNT(b.id) as booking_count
                        FROM meeting_rooms r
                        LEFT JOIN bookings b ON r.id = b.room_id AND b.status = 'confirmed'
                        WHERE r.status = 'active'
                        GROUP BY r.id, r.room_name
                        ORDER BY booking_count DESC
                        LIMIT 5";
$popular_rooms = $conn->query($popular_rooms_query)->fetch_all(MYSQLI_ASSOC);

// การจองในสัปดาห์นี้ (สำหรับกราฟ)
$week_bookings_query = "SELECT DATE(booking_date) as date, COUNT(*) as count
                        FROM bookings 
                        WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        AND booking_date <= CURDATE()
                        GROUP BY DATE(booking_date)
                        ORDER BY date";
$week_bookings = $conn->query($week_bookings_query)->fetch_all(MYSQLI_ASSOC);

$conn->close();

// ข้อความแจ้งเตือน
$alert = getAlert();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบจองห้องประชุม</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts - Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        
        .main-content {
            transition: all 0.3s ease;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .animate-pulse-soft {
            animation: pulse-soft 2s infinite;
        }
        
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stats-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Sidebar -->
    <div id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg z-40 sidebar">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="bg-blue-100 rounded-lg p-2">
                    <i data-lucide="calendar-check" class="w-6 h-6 text-blue-600"></i>
                </div>
                <div class="sidebar-text">
                    <h2 class="font-bold text-gray-900">ผู้ดูแลระบบ</h2>
                    <p class="text-sm text-gray-600"><?php echo escapeOutput($_SESSION['admin_name']); ?></p>
                </div>
            </div>
        </div>
        
        <nav class="mt-6">
            <div class="px-6 mb-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">เมนูหลัก</h3>
            </div>
            
            <a href="admin_dashboard.php" class="flex items-center px-6 py-3 text-blue-600 bg-blue-50 border-r-2 border-blue-600">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">หน้าหลัก</span>
            </a>
            
            <a href="admin_rooms.php" class="flex items-center px-6 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                <i data-lucide="building" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">จัดการห้องประชุม</span>
            </a>
            
            <a href="admin_bookings.php" class="flex items-center px-6 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                <i data-lucide="calendar" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">จัดการการจอง</span>
            </a>
            
            <div class="px-6 mt-8 mb-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">อื่นๆ</h3>
            </div>
            
            <a href="index.php" target="_blank" class="flex items-center px-6 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                <i data-lucide="external-link" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">ดูหน้าผู้ใช้</span>
            </a>
            
            <a href="admin_logout.php" class="flex items-center px-6 py-3 text-red-600 hover:text-red-700 hover:bg-red-50 transition-colors">
                <i data-lucide="log-out" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">ออกจากระบบ</span>
            </a>
        </nav>
        
        <!-- Toggle Button -->
        <button onclick="toggleSidebar()" class="absolute top-6 -right-3 bg-white border border-gray-300 rounded-full p-1 shadow-md hover:shadow-lg transition-all">
            <i data-lucide="chevron-left" id="sidebar-toggle-icon" class="w-4 h-4 text-gray-600"></i>
        </button>
    </div>
    
    <!-- Main Content -->
    <div id="main-content" class="ml-64 main-content">
        
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-600"><?php echo getThaiDate(); ?></p>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="bg-gray-100 rounded-lg px-4 py-2">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="clock" class="w-5 h-5 text-gray-600"></i>
                            <span id="current-time" class="text-gray-700 font-medium"></span>
                        </div>
                    </div>
                    
                    <?php if ($stats['pending_bookings'] > 0): ?>
                        <a href="admin_bookings.php?filter=pending" class="bg-orange-100 text-orange-700 px-4 py-2 rounded-lg hover:bg-orange-200 transition-colors flex items-center space-x-2">
                            <i data-lucide="bell" class="w-5 h-5 animate-pulse-soft"></i>
                            <span><?php echo $stats['pending_bookings']; ?> รอยืนยัน</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <!-- Alert -->
        <?php if ($alert): ?>
            <div class="mx-6 mt-6">
                <div class="bg-<?php echo $alert['type'] == 'success' ? 'green' : ($alert['type'] == 'error' ? 'red' : 'blue'); ?>-50 border border-<?php echo $alert['type'] == 'success' ? 'green' : ($alert['type'] == 'error' ? 'red' : 'blue'); ?>-200 rounded-lg p-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="<?php echo $alert['type'] == 'success' ? 'check-circle' : ($alert['type'] == 'error' ? 'alert-circle' : 'info'); ?>" class="w-5 h-5 text-<?php echo $alert['type'] == 'success' ? 'green' : ($alert['type'] == 'error' ? 'red' : 'blue'); ?>-600"></i>
                        <span class="text-<?php echo $alert['type'] == 'success' ? 'green' : ($alert['type'] == 'error' ? 'red' : 'blue'); ?>-800"><?php echo escapeOutput($alert['message']); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total Rooms -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">ห้องประชุมทั้งหมด</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_rooms']; ?></p>
                        </div>
                        <div class="bg-blue-100 rounded-lg p-3">
                            <i data-lucide="building" class="w-6 h-6 text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Total Bookings -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">การจองทั้งหมด</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_bookings']; ?></p>
                        </div>
                        <div class="bg-green-100 rounded-lg p-3">
                            <i data-lucide="calendar-check" class="w-6 h-6 text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Today Bookings -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">การจองวันนี้</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['today_bookings']; ?></p>
                        </div>
                        <div class="bg-purple-100 rounded-lg p-3">
                            <i data-lucide="calendar-days" class="w-6 h-6 text-purple-600"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Bookings -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">รอยืนยัน</p>
                            <p class="text-3xl font-bold text-orange-600"><?php echo $stats['pending_bookings']; ?></p>
                        </div>
                        <div class="bg-orange-100 rounded-lg p-3">
                            <i data-lucide="clock" class="w-6 h-6 text-orange-600"></i>
                        </div>
                    </div>
                </div>
            </div>
                        </div>
            
            <!-- Charts and Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Booking Chart -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">การจองในสัปดาห์นี้</h3>
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <i data-lucide="trending-up" class="w-4 h-4"></i>
                            <span>7 วันล่าสุด</span>
                        </div>
                    </div>
                    <div id="booking-chart" class="h-64"></div>
                </div>
                
                <!-- Popular Rooms -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">ห้องยอดนิยม</h3>
                        <i data-lucide="star" class="w-5 h-5 text-yellow-500"></i>
                    </div>
                    
                    <div class="space-y-4">
                        <?php foreach ($popular_rooms as $index => $room): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-medium text-gray-600">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo escapeOutput($room['room_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo $room['booking_count']; ?> การจอง</p>
                                    </div>
                                </div>
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($room['booking_count'] / max(1, $popular_rooms[0]['booking_count'])) * 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($popular_rooms)): ?>
                            <div class="text-center py-8">
                                <i data-lucide="inbox" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                <p class="text-gray-500">ยังไม่มีข้อมูลการจอง</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">กิจกรรมล่าสุด</h3>
                    <a href="admin_bookings.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center space-x-1">
                        <span>ดูทั้งหมด</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
                
                <div class="space-y-4">
                    <?php foreach ($recent_bookings as $booking): ?>
                        <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-<?php echo $booking['status'] == 'confirmed' ? 'green' : ($booking['status'] == 'pending' ? 'orange' : 'red'); ?>-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="<?php echo $booking['status'] == 'confirmed' ? 'check' : ($booking['status'] == 'pending' ? 'clock' : 'x'); ?>" class="w-5 h-5 text-<?php echo $booking['status'] == 'confirmed' ? 'green' : ($booking['status'] == 'pending' ? 'orange' : 'red'); ?>-600"></i>
                                </div>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2">
                                    <p class="font-medium text-gray-900"><?php echo escapeOutput($booking['booker_name']); ?></p>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $booking['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : ($booking['status'] == 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'); ?>">
                                        <?php echo $booking['status'] == 'confirmed' ? 'ยืนยันแล้ว' : ($booking['status'] == 'pending' ? 'รอยืนยัน' : 'ยกเลิก'); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    <?php echo escapeOutput($booking['room_name']); ?> - 
                                    <?php echo $booking['thai_date']; ?> 
                                    (<?php echo $booking['start_time_format']; ?>-<?php echo $booking['end_time_format']; ?>)
                                </p>
                                <?php if ($booking['purpose']): ?>
                                    <p class="text-sm text-gray-500 truncate"><?php echo escapeOutput($booking['purpose']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-shrink-0">
                                <a href="admin_bookings.php?id=<?php echo $booking['id']; ?>" class="text-blue-600 hover:text-blue-700">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recent_bookings)): ?>
                        <div class="text-center py-8">
                            <i data-lucide="calendar-x" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                            <p class="text-gray-500">ยังไม่มีการจองในระบบ</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <a href="admin_rooms.php?action=add" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-6 transition-colors card-hover">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 rounded-lg p-3">
                            <i data-lucide="plus" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold">เพิ่มห้องประชุม</h4>
                            <p class="text-blue-100 text-sm">สร้างห้องประชุมใหม่</p>
                        </div>
                    </div>
                </a>
                
                <a href="admin_bookings.php?filter=pending" class="bg-orange-600 hover:bg-orange-700 text-white rounded-xl p-6 transition-colors card-hover">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 rounded-lg p-3">
                            <i data-lucide="clock" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold">ยืนยันการจอง</h4>
                            <p class="text-orange-100 text-sm">จัดการการจองที่รอยืนยัน</p>
                        </div>
                    </div>
                </a>
                
                <a href="index.php" target="_blank" class="bg-green-600 hover:bg-green-700 text-white rounded-xl p-6 transition-colors card-hover">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 rounded-lg p-3">
                            <i data-lucide="external-link" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold">ดูหน้าผู้ใช้</h4>
                            <p class="text-green-100 text-sm">เปิดหน้าสำหรับผู้ใช้งาน</p>
                        </div>
                    </div>
                </a>
            </div>
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
        
        setInterval(updateTime, 1000);
        updateTime();
        
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('sidebar-toggle-icon');
            
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('ml-20');
                mainContent.classList.add('ml-64');
                toggleIcon.setAttribute('data-lucide', 'chevron-left');
            } else {
                sidebar.classList.add('collapsed');
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-20');
                toggleIcon.setAttribute('data-lucide', 'chevron-right');
            }
            
            lucide.createIcons();
        }
        
        // Initialize booking chart
        const bookingChartData = <?php echo json_encode($week_bookings); ?>;
        
        // Prepare chart data
        const chartDates = [];
        const chartCounts = [];
        
        // Fill in missing dates with 0 bookings
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateString = date.toISOString().split('T')[0];
            
            const dayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
            const dayName = dayNames[date.getDay()];
            chartDates.push(dayName);
            
            const foundData = bookingChartData.find(item => item.date === dateString);
            chartCounts.push(foundData ? parseInt(foundData.count) : 0);
        }
        
        const chartOptions = {
            chart: {
                type: 'area',
                height: 256,
                toolbar: {
                    show: false
                },
                sparkline: {
                    enabled: false
                }
            },
            series: [{
                name: 'การจอง',
                data: chartCounts
            }],
            xaxis: {
                categories: chartDates,
                labels: {
                    style: {
                        fontFamily: 'Prompt, sans-serif'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        fontFamily: 'Prompt, sans-serif'
                    }
                }
            },
            colors: ['#3B82F6'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.1,
                    stops: [0, 90, 100]
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            grid: {
                show: true,
                strokeDashArray: 3,
                borderColor: '#E5E7EB'
            },
            tooltip: {
                style: {
                    fontFamily: 'Prompt, sans-serif'
                }
            }
        };
        
        const chart = new ApexCharts(document.querySelector("#booking-chart"), chartOptions);
        chart.render();
        
        // Mobile sidebar handling
        if (window.innerWidth < 1024) {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            sidebar.classList.add('collapsed');
            mainContent.classList.remove('ml-64');
            mainContent.classList.add('ml-20');
        }
        
        // Responsive handling
        window.addEventListener('resize', function() {
            if (window.innerWidth < 1024) {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('main-content');
                
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.remove('ml-64');
                    mainContent.classList.add('ml-20');
                    document.getElementById('sidebar-toggle-icon').setAttribute('data-lucide', 'chevron-right');
                    lucide.createIcons();
                }
            }
        });
    </script>
</body>
</html>