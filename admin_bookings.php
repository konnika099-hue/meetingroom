<?php
require_once 'config.php';
checkAdminAuth();

$conn = getDBConnection();
$message = '';
$message_type = '';

// จัดการการอัพเดทสถานะการจอง
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $booking_id = (int)$_POST['booking_id'];
    
    if ($action === 'approve') {
        $query = "UPDATE bookings SET status = 'confirmed' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $booking_id);
        
        if ($stmt->execute()) {
            $message = 'อนุมัติการจองเรียบร้อยแล้ว';
            $message_type = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาดในการอนุมัติ';
            $message_type = 'error';
        }
    } elseif ($action === 'reject') {
        $query = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $booking_id);
        
        if ($stmt->execute()) {
            $message = 'ปฏิเสธการจองเรียบร้อยแล้ว';
            $message_type = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาดในการปฏิเสธ';
            $message_type = 'error';
        }
    } elseif ($action === 'delete') {
        $query = "DELETE FROM bookings WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $booking_id);
        
        if ($stmt->execute()) {
            $message = 'ลบการจองเรียบร้อยแล้ว';
            $message_type = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาดในการลบ';
            $message_type = 'error';
        }
    }
}

// ดึงข้อมูลการจองทั้งหมด
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$where_conditions = [];
$params = [];
$param_types = '';

if ($filter !== 'all') {
    $where_conditions[] = "b.status = ?";
    $params[] = $filter;
    $param_types .= 's';
}

if ($search_id > 0) {
    $where_conditions[] = "b.id = ?";
    $params[] = $search_id;
    $param_types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT b.*, r.room_name, r.capacity,
                 DATE_FORMAT(b.booking_date, '%d/%m/%Y') as thai_date,
                 TIME_FORMAT(b.start_time, '%H:%i') as start_time_format,
                 TIME_FORMAT(b.end_time, '%H:%i') as end_time_format,
                 DATE_FORMAT(b.created_at, '%d/%m/%Y %H:%i') as created_at_format
          FROM bookings b 
          JOIN meeting_rooms r ON b.room_id = r.id 
          $where_clause
          ORDER BY b.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$bookings = $result->fetch_all(MYSQLI_ASSOC);

// สถิติการจอง
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM bookings";
$stats = $conn->query($stats_query)->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง - ระบบจองห้องประชุม</title>
    
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
        
        .animate-pulse-soft {
            animation: pulse-soft 2s infinite;
        }
        
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Custom DataTable Styling */
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 1rem;
        }
        
        .dataTables_wrapper .dataTables_length select {
            padding: 0.5rem 2rem 0.5rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background-color: white;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            appearance: none;
            font-family: 'Prompt', sans-serif;
        }
        
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            margin-left: 0.5rem;
            font-family: 'Prompt', sans-serif;
            background-color: white;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .dataTables_wrapper .dataTables_info {
            padding-top: 1rem;
            font-family: 'Prompt', sans-serif;
            color: #6b7280;
        }
        
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 1rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 0.75rem;
            margin: 0 0.125rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
            color: #374151;
            text-decoration: none;
            font-family: 'Prompt', sans-serif;
            transition: all 0.2s ease;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
            color: #111827;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: white !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background-color: white;
            border-color: #d1d5db;
            color: #374151;
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-family: 'Prompt', sans-serif;
        }
        
        .dataTables_wrapper .dataTables_processing {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-family: 'Prompt', sans-serif;
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
            
            <a href="admin_dashboard.php" class="flex items-center px-6 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">หน้าหลัก</span>
            </a>
            
            <a href="admin_rooms.php" class="flex items-center px-6 py-3 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                <i data-lucide="building" class="w-5 h-5"></i>
                <span class="ml-3 sidebar-text">จัดการห้องประชุม</span>
            </a>
            
            <a href="admin_bookings.php" class="flex items-center px-6 py-3 text-blue-600 bg-blue-50 border-r-2 border-blue-600">
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
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">จัดการการจอง</h1>
                    <p class="text-gray-600">อนุมัติ ปฏิเสธ และจัดการการจองห้องประชุม</p>
                </div>
                
                <!-- Filter Buttons -->
                <div class="flex flex-wrap gap-2">
                    <a href="admin_bookings.php" class="<?php echo $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                        <i data-lucide="list" class="w-4 h-4"></i>
                        <span>ทั้งหมด (<?php echo $stats['total']; ?>)</span>
                    </a>
                    <a href="admin_bookings.php?filter=pending" class="<?php echo $filter === 'pending' ? 'bg-orange-600 text-white' : 'bg-orange-100 text-orange-700 hover:bg-orange-200'; ?> px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                        <i data-lucide="clock" class="w-4 h-4 <?php echo $stats['pending'] > 0 ? 'animate-pulse-soft' : ''; ?>"></i>
                        <span>รอยืนยัน (<?php echo $stats['pending']; ?>)</span>
                    </a>
                    <a href="admin_bookings.php?filter=confirmed" class="<?php echo $filter === 'confirmed' ? 'bg-green-600 text-white' : 'bg-green-100 text-green-700 hover:bg-green-200'; ?> px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        <span>ยืนยันแล้ว (<?php echo $stats['confirmed']; ?>)</span>
                    </a>
                    <a href="admin_bookings.php?filter=cancelled" class="<?php echo $filter === 'cancelled' ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700 hover:bg-red-200'; ?> px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        <span>ยกเลิก (<?php echo $stats['cancelled']; ?>)</span>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="mx-6 mt-6">
                <div class="bg-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-50 border border-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-200 rounded-lg p-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="<?php echo $message_type == 'success' ? 'check-circle' : 'alert-circle'; ?>" class="w-5 h-5 text-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-600"></i>
                        <span class="text-<?php echo $message_type == 'success' ? 'green' : 'red'; ?>-800"><?php echo $message; ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Bookings Table -->
        <div class="p-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6">
                    <table id="bookingsTable" class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="text-left p-4 font-semibold text-gray-900">ID</th>
                                <th class="text-left p-4 font-semibold text-gray-900">ผู้จอง</th>
                                <th class="text-left p-4 font-semibold text-gray-900">ห้องประชุม</th>
                                <th class="text-left p-4 font-semibold text-gray-900">วันที่และเวลา</th>
                                <th class="text-left p-4 font-semibold text-gray-900">วัตถุประสงค์</th>
                                <th class="text-left p-4 font-semibold text-gray-900">สถานะ</th>
                                <th class="text-left p-4 font-semibold text-gray-900">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="p-4">
                                        <span class="font-mono text-sm text-gray-600">#<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td class="p-4">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo escapeOutput($booking['booker_name']); ?></p>
                                            <?php if ($booking['booker_email']): ?>
                                                <p class="text-sm text-gray-600"><?php echo escapeOutput($booking['booker_email']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($booking['booker_phone']): ?>
                                                <p class="text-sm text-gray-600"><?php echo escapeOutput($booking['booker_phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo escapeOutput($booking['room_name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo $booking['capacity']; ?> ที่นั่ง</p>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo $booking['thai_date']; ?></p>
                                            <p class="text-sm text-gray-600"><?php echo $booking['start_time_format']; ?> - <?php echo $booking['end_time_format']; ?></p>
                                            <p class="text-xs text-gray-500">จองเมื่อ: <?php echo $booking['created_at_format']; ?></p>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <p class="text-sm text-gray-600 max-w-xs">
                                            <?php echo $booking['purpose'] ? escapeOutput(substr($booking['purpose'], 0, 80)) . (strlen($booking['purpose']) > 80 ? '...' : '') : '-'; ?>
                                        </p>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            <?php 
                                            echo $booking['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : 
                                                 ($booking['status'] == 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700');
                                            ?>">
                                            <?php 
                                            echo $booking['status'] == 'confirmed' ? 'ยืนยันแล้ว' : 
                                                 ($booking['status'] == 'pending' ? 'รอยืนยัน' : 'ยกเลิก');
                                            ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-2">
                                            <button onclick="viewBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-700 p-1" title="ดูรายละเอียด">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            
                                            <?php if ($booking['status'] == 'pending'): ?>
                                                <button onclick="approveBooking(<?php echo $booking['id']; ?>)" 
                                                        class="text-green-600 hover:text-green-700 p-1" title="อนุมัติ">
                                                    <i data-lucide="check" class="w-4 h-4"></i>
                                                </button>
                                                <button onclick="rejectBooking(<?php echo $booking['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-700 p-1" title="ปฏิเสธ">
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo escapeOutput($booking['booker_name']); ?>')" 
                                                    class="text-red-600 hover:text-red-700 p-1" title="ลบ">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-12">
                            <i data-lucide="calendar-x" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">ไม่พบการจอง</h3>
                            <p class="text-gray-600">
                                <?php 
                                echo $filter === 'pending' ? 'ไม่มีการจองที่รอยืนยัน' : 
                                     ($filter === 'confirmed' ? 'ไม่มีการจองที่ยืนยันแล้ว' : 
                                      ($filter === 'cancelled' ? 'ไม่มีการจองที่ยกเลิก' : 'ยังไม่มีการจองในระบบ'));
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Booking Modal -->
    <div id="viewBookingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-fadeIn">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">รายละเอียดการจอง</h2>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div id="viewBookingContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 animate-fadeIn">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-4">
                    <div id="confirmIcon" class="flex-shrink-0"></div>
                    <h3 id="confirmTitle" class="text-lg font-semibold text-gray-900"></h3>
                </div>
                
                <p id="confirmMessage" class="text-gray-600 mb-6"></p>
                
                <div class="flex space-x-3">
                    <button onclick="closeConfirmModal()" class="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                        ยกเลิก
                    </button>
                    <button id="confirmAction" class="flex-1 px-4 py-2 rounded-lg text-white transition-colors">
                        ยืนยัน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#bookingsTable').DataTable({
                "language": {
                    "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                    "zeroRecords": "ไม่พบข้อมูล",
                    "info": "แสดงหน้าที่ _PAGE_ จาก _PAGES_",
                    "infoEmpty": "ไม่มีข้อมูล",
                    "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
                    "search": "ค้นหา:",
                    "paginate": {
                        "first": "หน้าแรก",
                        "last": "หน้าสุดท้าย",
                        "next": "ถัดไป",
                        "previous": "ก่อนหน้า"
                    }
                },
                "pageLength": 10,
                "responsive": true,
                "order": [[0, 'desc']]
            });
        });
        
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
        
        // View booking details
        function viewBooking(booking) {
            const statusText = booking.status === 'confirmed' ? 'ยืนยันแล้ว' : 
                              (booking.status === 'pending' ? 'รอยืนยัน' : 'ยกเลิก');
            const statusColor = booking.status === 'confirmed' ? 'text-green-600' : 
                               (booking.status === 'pending' ? 'text-orange-600' : 'text-red-600');
            
            const content = `
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-2">ข้อมูลผู้จอง</h4>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="user" class="w-4 h-4 text-gray-500"></i>
                                        <span class="text-gray-700">${booking.booker_name}</span>
                                    </div>
                                    ${booking.booker_email ? `
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="mail" class="w-4 h-4 text-gray-500"></i>
                                            <span class="text-gray-700">${booking.booker_email}</span>
                                        </div>
                                    ` : ''}
                                    ${booking.booker_phone ? `
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="phone" class="w-4 h-4 text-gray-500"></i>
                                            <span class="text-gray-700">${booking.booker_phone}</span>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-2">ข้อมูลการจอง</h4>
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="building" class="w-4 h-4 text-blue-500"></i>
                                        <span class="text-gray-700">${booking.room_name}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="calendar" class="w-4 h-4 text-blue-500"></i>
                                        <span class="text-gray-700">${booking.thai_date}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="clock" class="w-4 h-4 text-blue-500"></i>
                                        <span class="text-gray-700">${booking.start_time_format} - ${booking.end_time_format}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="info" class="w-4 h-4 text-blue-500"></i>
                                        <span class="${statusColor} font-medium">${statusText}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            ${booking.purpose ? `
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 mb-2">วัตถุประสงค์</h4>
                                    <p class="text-gray-700 whitespace-pre-wrap">${booking.purpose}</p>
                                </div>
                            ` : ''}
                            
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-2">ข้อมูลเพิ่มเติม</h4>
                                <div class="space-y-2 text-sm text-gray-600">
                                    <p>หมายเลขการจอง: #${String(booking.id).padStart(4, '0')}</p>
                                    <p>จองเมื่อ: ${booking.created_at_format}</p>
                                    <p>จำนวนที่นั่ง: ${booking.capacity} คน</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${booking.status === 'pending' ? `
                        <div class="flex space-x-3 pt-4 border-t border-gray-200">
                            <button onclick="approveBooking(${booking.id}); closeViewModal();" 
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center space-x-2">
                                <i data-lucide="check" class="w-4 h-4"></i>
                                <span>อนุมัติการจอง</span>
                            </button>
                            <button onclick="rejectBooking(${booking.id}); closeViewModal();" 
                                    class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center space-x-2">
                                <i data-lucide="x" class="w-4 h-4"></i>
                                <span>ปฏิเสธการจอง</span>
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('viewBookingContent').innerHTML = content;
            showModal('viewBookingModal');
            lucide.createIcons();
        }
        
        function closeViewModal() {
            closeModal('viewBookingModal');
        }
        
        // Booking actions
        function approveBooking(bookingId) {
            showConfirmModal(
                'อนุมัติการจอง',
                'คุณต้องการอนุมัติการจองนี้หรือไม่?',
                'bg-green-600 hover:bg-green-700',
                '<i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>',
                () => submitAction('approve', bookingId)
            );
        }
        
        function rejectBooking(bookingId) {
            showConfirmModal(
                'ปฏิเสธการจอง',
                'คุณต้องการปฏิเสธการจองนี้หรือไม่?',
                'bg-red-600 hover:bg-red-700',
                '<i data-lucide="x-circle" class="w-6 h-6 text-red-600"></i>',
                () => submitAction('reject', bookingId)
            );
        }
        
        function deleteBooking(bookingId, bookerName) {
            showConfirmModal(
                'ลบการจอง',
                `คุณต้องการลบการจองของ "${bookerName}" หรือไม่?\n\nการดำเนินการนี้ไม่สามารถยกเลิกได้`,
                'bg-red-600 hover:bg-red-700',
                '<i data-lucide="trash-2" class="w-6 h-6 text-red-600"></i>',
                () => submitAction('delete', bookingId)
            );
        }
        
        // Confirmation modal
        function showConfirmModal(title, message, buttonClass, icon, action) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmIcon').innerHTML = icon;
            
            const actionBtn = document.getElementById('confirmAction');
            actionBtn.className = `flex-1 px-4 py-2 rounded-lg text-white transition-colors ${buttonClass}`;
            actionBtn.onclick = () => {
                action();
                closeConfirmModal();
            };
            
            showModal('confirmModal');
            lucide.createIcons();
        }
        
        function closeConfirmModal() {
            closeModal('confirmModal');
        }
        
        // Submit action
        function submitAction(action, bookingId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="booking_id" value="${bookingId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['viewBookingModal', 'confirmModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = ['viewBookingModal', 'confirmModal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (!modal.classList.contains('hidden')) {
                        closeModal(modalId);
                    }
                });
            }
        });
        
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
        
        // Auto-hide alerts after 5 seconds
        const alert = document.querySelector('.bg-green-50, .bg-red-50');
        if (alert) {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>