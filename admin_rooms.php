<?php
require_once 'config.php';
checkAdminAuth();

$conn = getDBConnection();
$message = '';
$message_type = '';

// จัดการการเพิ่ม/แก้ไขห้องประชุม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $room_name = sanitizeInput($_POST['room_name']);
    $description = sanitizeInput($_POST['description']);
    $capacity = (int)$_POST['capacity'];
    $status = sanitizeInput($_POST['status']);
    
    $errors = [];
    
    // ตรวจสอบข้อมูล
    if (empty($room_name)) {
        $errors[] = 'กรุณากรอกชื่อห้องประชุม';
    }
    
    if ($capacity <= 0) {
        $errors[] = 'จำนวนที่นั่งต้องมากกว่า 0';
    }
    
    $image_path = '';
    
    // จัดการการอัพโหลดรูปภาพ
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $image_path = uploadAndResizeImage($_FILES['image']);
        if (!$image_path) {
            $errors[] = 'เกิดข้อผิดพลาดในการอัพโหลดรูปภาพ';
        }
    }
    
    if (empty($errors)) {
        if ($action === 'add') {
            // เพิ่มห้องประชุมใหม่
            $query = "INSERT INTO meeting_rooms (room_name, description, capacity, image_path, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssiss', $room_name, $description, $capacity, $image_path, $status);
            
            if ($stmt->execute()) {
                $message = 'เพิ่มห้องประชุมเรียบร้อยแล้ว';
                $message_type = 'success';
            } else {
                $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            }
        } elseif ($action === 'edit') {
            // แก้ไขห้องประชุม
            $room_id = (int)$_POST['room_id'];
            
            if ($image_path) {
                // อัพเดทพร้อมรูปภาพใหม่
                $query = "UPDATE meeting_rooms SET room_name = ?, description = ?, capacity = ?, image_path = ?, status = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssissi', $room_name, $description, $capacity, $image_path, $status, $room_id);
            } else {
                // อัพเดทไม่รวมรูปภาพ
                $query = "UPDATE meeting_rooms SET room_name = ?, description = ?, capacity = ?, status = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssisi', $room_name, $description, $capacity, $status, $room_id);
            }
            
            if ($stmt->execute()) {
                $message = 'แก้ไขข้อมูลห้องประชุมเรียบร้อยแล้ว';
                $message_type = 'success';
            } else {
                $errors[] = 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล';
            }
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// จัดการการลบห้องประชุม
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // ตรวจสอบว่ามีการจองหรือไม่
    $check_query = "SELECT COUNT(*) as count FROM bookings WHERE room_id = ? AND status IN ('confirmed', 'pending')";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('i', $delete_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    $booking_count = $check_result->fetch_assoc()['count'];
    
    if ($booking_count > 0) {
        $message = 'ไม่สามารถลบห้องประชุมได้ เนื่องจากมีการจองที่ยังไม่เสร็จสิ้น';
        $message_type = 'error';
    } else {
        $delete_query = "DELETE FROM meeting_rooms WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $delete_id);
        
        if ($stmt->execute()) {
            $message = 'ลบห้องประชุมเรียบร้อยแล้ว';
            $message_type = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาดในการลบข้อมูล';
            $message_type = 'error';
        }
    }
}

// ดึงข้อมูลห้องประชุมทั้งหมด
$query = "SELECT r.*, 
                 COUNT(b.id) as total_bookings,
                 SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings
          FROM meeting_rooms r 
          LEFT JOIN bookings b ON r.id = b.room_id 
          GROUP BY r.id 
          ORDER BY r.created_at DESC";
$rooms = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// ดึงข้อมูลห้องประชุมสำหรับแก้ไข
$edit_room = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM meeting_rooms WHERE id = ?";
    $stmt = $conn->prepare($edit_query);
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    $edit_room = $edit_result->fetch_assoc();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการห้องประชุม - ระบบจองห้องประชุม</title>
    
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
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 0.5rem;
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
            
            <a href="admin_rooms.php" class="flex items-center px-6 py-3 text-blue-600 bg-blue-50 border-r-2 border-blue-600">
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
                    <h1 class="text-2xl font-bold text-gray-900">จัดการห้องประชุม</h1>
                    <p class="text-gray-600">เพิ่ม แก้ไข และจัดการห้องประชุมในระบบ</p>
                </div>
                
                <button onclick="showAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                    <i data-lucide="plus" class="w-5 h-5"></i>
                    <span>เพิ่มห้องประชุม</span>
                </button>
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
        
        <!-- Rooms Table -->
        <div class="p-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6">
                    <table id="roomsTable" class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="text-left p-4 font-semibold text-gray-900">รูปภาพ</th>
                                <th class="text-left p-4 font-semibold text-gray-900">ชื่อห้อง</th>
                                <th class="text-left p-4 font-semibold text-gray-900">จำนวนที่นั่ง</th>
                                <th class="text-left p-4 font-semibold text-gray-900">การจอง</th>
                                <th class="text-left p-4 font-semibold text-gray-900">สถานะ</th>
                                <th class="text-left p-4 font-semibold text-gray-900">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="p-4">
                                        <img src="<?php echo escapeOutput($room['image_path'] ?? 'https://via.placeholder.com/80x60/e5e7eb/6b7280?text=No+Image'); ?>" 
                                             alt="<?php echo escapeOutput($room['room_name']); ?>" 
                                             class="w-20 h-15 object-cover rounded-lg border border-gray-200">
                                    </td>
                                    <td class="p-4">
                                        <div>
                                            <h4 class="font-semibold text-gray-900"><?php echo escapeOutput($room['room_name']); ?></h4>
                                            <p class="text-sm text-gray-600 line-clamp-2"><?php echo escapeOutput(substr($room['description'], 0, 100)) . (strlen($room['description']) > 100 ? '...' : ''); ?></p>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="users" class="w-4 h-4 text-gray-500"></i>
                                            <span class="text-gray-900"><?php echo $room['capacity']; ?> คน</span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="space-y-1">
                                            <div class="text-sm text-gray-600">ทั้งหมด: <?php echo $room['total_bookings']; ?></div>
                                            <div class="text-sm text-green-600">ยืนยัน: <?php echo $room['confirmed_bookings']; ?></div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $room['status'] == 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo $room['status'] == 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-2">
                                            <button onclick="editRoom(<?php echo htmlspecialchars(json_encode($room)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-700 p-1" title="แก้ไข">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="viewRoom(<?php echo $room['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-700 p-1" title="ดูรายละเอียด">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            <button onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo escapeOutput($room['room_name']); ?>')" 
                                                    class="text-red-600 hover:text-red-700 p-1" title="ลบ">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
    
    <!-- Add/Edit Room Modal -->
    <div id="roomModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-fadeIn">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">เพิ่มห้องประชุม</h2>
                    <button onclick="closeRoomModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <form id="roomForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="room_id" id="roomId" value="">
                    
                    <!-- Room Name -->
                    <div>
                        <label for="room_name" class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อห้องประชุม <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="building" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <input type="text" id="room_name" name="room_name" required
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="ระบุชื่อห้องประชุม">
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            รายละเอียด
                        </label>
                        <div class="relative">
                            <div class="absolute top-3 left-3 pointer-events-none">
                                <i data-lucide="file-text" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <textarea id="description" name="description" rows="4"
                                      class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                                      placeholder="ระบุรายละเอียดของห้องประชุม..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Capacity and Status -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="capacity" class="block text-sm font-medium text-gray-700 mb-2">
                                จำนวนที่นั่ง <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="users" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="number" id="capacity" name="capacity" required min="1"
                                       class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                       placeholder="จำนวนที่นั่ง">
                            </div>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                สถานะ
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="toggle-left" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <select id="status" name="status"
                                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <option value="active">เปิดใช้งาน</option>
                                    <option value="inactive">ปิดใช้งาน</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Image Upload -->
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                            รูปภาพห้องประชุม
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                            <div id="imagePreview" class="hidden mb-4">
                                <img id="previewImg" class="image-preview mx-auto">
                            </div>
                            <div id="uploadArea">
                                <i data-lucide="upload" class="w-8 h-8 text-gray-400 mx-auto mb-2"></i>
                                <p class="text-gray-600 mb-2">คลิกเพื่อเลือกรูปภาพ หรือลากไฟล์มาวาง</p>
                                <p class="text-sm text-gray-500">รองรับไฟล์ JPG, PNG, GIF (ขนาดไม่เกิน 5MB)</p>
                            </div>
                            <input type="file" id="image" name="image" accept="image/*" class="hidden">
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-6">
                        <button type="button" onclick="closeRoomModal()" 
                                class="flex-1 bg-gray-200 text-gray-800 px-6 py-3 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                            ยกเลิก
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                            <i data-lucide="save" class="w-5 h-5"></i>
                            <span id="submitText">บันทึก</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Room Modal -->
    <div id="viewRoomModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal">
        <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto animate-fadeIn">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">รายละเอียดห้องประชุม</h2>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div id="viewRoomContent">
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
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#roomsTable').DataTable({
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
                "order": [[1, 'asc']]
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
        
        function showLoading() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('loadingSpinner').classList.add('flex');
        }
        
        function hideLoading() {
            document.getElementById('loadingSpinner').classList.add('hidden');
            document.getElementById('loadingSpinner').classList.remove('flex');
        }
        
        // Add room modal
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'เพิ่มห้องประชุม';
            document.getElementById('formAction').value = 'add';
            document.getElementById('submitText').textContent = 'เพิ่มห้องประชุม';
            document.getElementById('roomForm').reset();
            document.getElementById('roomId').value = '';
            
            // Reset image preview
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('uploadArea').classList.remove('hidden');
            
            showModal('roomModal');
        }
        
        function closeRoomModal() {
            closeModal('roomModal');
            document.getElementById('roomForm').reset();
        }
        
        // Edit room
        function editRoom(room) {
            document.getElementById('modalTitle').textContent = 'แก้ไขห้องประชุม';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitText').textContent = 'บันทึกการแก้ไข';
            document.getElementById('roomId').value = room.id;
            document.getElementById('room_name').value = room.room_name;
            document.getElementById('description').value = room.description || '';
            document.getElementById('capacity').value = room.capacity;
            document.getElementById('status').value = room.status;
            
            // Show current image if exists
            if (room.image_path && room.image_path !== '') {
                document.getElementById('previewImg').src = room.image_path;
                document.getElementById('imagePreview').classList.remove('hidden');
                document.getElementById('uploadArea').classList.add('hidden');
            } else {
                document.getElementById('imagePreview').classList.add('hidden');
                document.getElementById('uploadArea').classList.remove('hidden');
            }
            
            showModal('roomModal');
        }
        
        // View room details
        function viewRoom(roomId) {
            showLoading();
            
            fetch(`get_room_details.php?id=${roomId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('viewRoomContent').innerHTML = data;
                    hideLoading();
                    showModal('viewRoomModal');
                    lucide.createIcons();
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideLoading();
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }
        
        function closeViewModal() {
            closeModal('viewRoomModal');
        }
        
        // Delete room
        function deleteRoom(roomId, roomName) {
            if (confirm(`คุณต้องการลบห้องประชุม "${roomName}" หรือไม่?\n\nหากห้องนี้มีการจองที่ยังไม่เสร็จสิ้น จะไม่สามารถลบได้`)) {
                window.location.href = `admin_rooms.php?delete=${roomId}`;
            }
        }
        
        // Image upload handling
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('ขนาดไฟล์ต้องไม่เกิน 5MB');
                    this.value = '';
                    return;
                }
                
                // Check file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('รองรับเฉพาะไฟล์ JPG, PNG, GIF เท่านั้น');
                    this.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                    document.getElementById('uploadArea').classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Drag and drop for image upload
        const uploadArea = document.querySelector('.border-dashed');
        
        uploadArea.addEventListener('click', function() {
            document.getElementById('image').click();
        });
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-blue-400', 'bg-blue-50');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-400', 'bg-blue-50');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-400', 'bg-blue-50');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('image').files = files;
                document.getElementById('image').dispatchEvent(new Event('change'));
            }
        });
        
        // Form validation
        document.getElementById('roomForm').addEventListener('submit', function(e) {
            const roomName = document.getElementById('room_name').value.trim();
            const capacity = document.getElementById('capacity').value;
            
            if (!roomName) {
                e.preventDefault();
                alert('กรุณากรอกชื่อห้องประชุม');
                document.getElementById('room_name').focus();
                return;
            }
            
            if (!capacity || capacity <= 0) {
                e.preventDefault();
                alert('กรุณากรอกจำนวนที่นั่งที่ถูกต้อง');
                document.getElementById('capacity').focus();
                return;
            }
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = ['roomModal', 'viewRoomModal'];
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
                const modals = ['roomModal', 'viewRoomModal'];
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
        
        <?php if ($edit_room): ?>
        // Auto-open edit modal if edit parameter is present
        editRoom(<?php echo json_encode($edit_room); ?>);
        <?php endif; ?>
    </script>
</body>
</html>