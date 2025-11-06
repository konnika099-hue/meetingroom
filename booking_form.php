<?php
require_once 'config.php';

// ตรวจสอบว่ามีการส่งข้อมูลมาหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_id = (int)$_POST['room_id'];
    $booker_name = sanitizeInput($_POST['booker_name']);
    $booker_email = sanitizeInput($_POST['booker_email']);
    $booker_phone = sanitizeInput($_POST['booker_phone']);
    $booking_date = sanitizeInput($_POST['booking_date']);
    $start_time = sanitizeInput($_POST['start_time']);
    $end_time = sanitizeInput($_POST['end_time']);
    $purpose = sanitizeInput($_POST['purpose']);
    
    $errors = [];
    
    // ตรวจสอบข้อมูล
    if (empty($booker_name)) {
        $errors[] = 'กรุณากรอกชื่อผู้จอง';
    }
    
    if (!empty($booker_email) && !filter_var($booker_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    
    if (empty($booking_date)) {
        $errors[] = 'กรุณาเลือกวันที่จอง';
    } elseif ($booking_date < date('Y-m-d')) {
        $errors[] = 'ไม่สามารถจองวันที่ผ่านมาแล้วได้';
    }
    
    if (empty($start_time) || empty($end_time)) {
        $errors[] = 'กรุณาเลือกเวลาเริ่มต้นและสิ้นสุด';
    } elseif ($start_time >= $end_time) {
        $errors[] = 'เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด';
    }
    
    // ตรวจสอบการจองซ้ำ
    if (empty($errors)) {
        $conn = getDBConnection();
        $check_query = "SELECT id FROM bookings 
                       WHERE room_id = ? AND booking_date = ? 
                       AND status IN ('confirmed', 'pending')
                       AND ((start_time <= ? AND end_time > ?) 
                            OR (start_time < ? AND end_time >= ?)
                            OR (start_time >= ? AND end_time <= ?))";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('isssssss', $room_id, $booking_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'ช่วงเวลานี้มีการจองแล้ว กรุณาเลือกช่วงเวลาอื่น';
        }
        
        $conn->close();
    }
    
    // บันทึกข้อมูล
    if (empty($errors)) {
        $conn = getDBConnection();
        $insert_query = "INSERT INTO bookings (room_id, booker_name, booker_email, booker_phone, booking_date, start_time, end_time, purpose, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('isssssss', $room_id, $booker_name, $booker_email, $booker_phone, $booking_date, $start_time, $end_time, $purpose);
        
        if ($stmt->execute()) {
            $booking_id = $conn->insert_id;
            $success_message = "จองห้องประชุมเรียบร้อยแล้ว หมายเลขการจอง: " . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
        } else {
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        }
        
        $conn->close();
    }
}

// ดึงข้อมูลห้องประชุม
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : (isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0);
$selected_date = isset($_GET['date']) ? $_GET['date'] : (isset($_POST['booking_date']) ? $_POST['booking_date'] : date('Y-m-d'));

if ($room_id > 0) {
    $conn = getDBConnection();
    $query = "SELECT * FROM meeting_rooms WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    
    if (!$room) {
        header('Location: index.php');
        exit;
    }
    
    $conn->close();
} else {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองห้องประชุม - <?php echo escapeOutput($room['room_name']); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts - Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            transition: all 0.3s ease;
        }
        
        .form-group:focus-within {
            transform: translateY(-2px);
        }
        
        .success-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Header -->
    <header class="hero-gradient text-white py-4 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="text-white hover:text-blue-200 transition-colors">
                        <i data-lucide="arrow-left" class="w-6 h-6"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold">จองห้องประชุม</h1>
                        <p class="text-blue-100 text-sm"><?php echo escapeOutput($room['room_name']); ?></p>
                    </div>
                </div>
                <div class="hidden md:flex items-center space-x-2">
                    <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                    <span class="text-sm"><?php echo getThaiDate(); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        
        <?php if (isset($success_message)): ?>
            <!-- Success Message -->
            <div class="max-w-2xl mx-auto mb-8 animate-fadeIn">
                <div class="success-card text-white rounded-xl p-6 shadow-lg">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="bg-white/20 rounded-full p-2">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                        <h2 class="text-xl font-bold">จองสำเร็จ!</h2>
                    </div>
                    <p class="text-white/90 mb-6"><?php echo escapeOutput($success_message); ?></p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-white/10 rounded-lg p-4">
                            <p class="text-white/70 text-sm">ห้องประชุม</p>
                            <p class="font-semibold"><?php echo escapeOutput($room['room_name']); ?></p>
                        </div>
                        <div class="bg-white/10 rounded-lg p-4">
                            <p class="text-white/70 text-sm">วันที่จอง</p>
                            <p class="font-semibold"><?php echo convertToThaiYear($booking_date); ?></p>
                        </div>
                        <div class="bg-white/10 rounded-lg p-4">
                            <p class="text-white/70 text-sm">เวลา</p>
                            <p class="font-semibold"><?php echo date('H:i', strtotime($start_time)); ?> - <?php echo date('H:i', strtotime($end_time)); ?></p>
                        </div>
                        <div class="bg-white/10 rounded-lg p-4">
                            <p class="text-white/70 text-sm">สถานะ</p>
                            <p class="font-semibold">รอยืนยัน</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="index.php" class="flex-1 bg-white text-green-600 px-6 py-3 rounded-lg font-medium text-center hover:bg-gray-100 transition-colors">
                            กลับหน้าหลัก
                        </a>
                        <button onclick="window.print()" class="flex-1 bg-white/20 text-white px-6 py-3 rounded-lg font-medium hover:bg-white/30 transition-colors">
                            พิมพ์ใบจอง
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($success_message)): ?>
            <div class="max-w-4xl mx-auto">
                
                <!-- Room Info Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8 overflow-hidden">
                    <div class="md:flex">
                        <div class="md:w-1/3">
                            <img src="<?php echo escapeOutput($room['image_path'] ?? 'https://via.placeholder.com/400x250/e5e7eb/6b7280?text=ไม่มีรูปภาพ'); ?>" 
                                 alt="<?php echo escapeOutput($room['room_name']); ?>" 
                                 class="w-full h-48 md:h-full object-cover">
                        </div>
                        <div class="p-6 md:w-2/3">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3"><?php echo escapeOutput($room['room_name']); ?></h2>
                            <p class="text-gray-600 mb-4"><?php echo nl2br(escapeOutput($room['description'])); ?></p>
                            
                            <div class="flex items-center space-x-6">
                                <div class="flex items-center space-x-2 text-gray-500">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                    <span><?php echo escapeOutput($room['capacity']); ?> ที่นั่ง</span>
                                </div>
                                <div class="flex items-center space-x-2 text-green-600">
                                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                                    <span>เปิดใช้งาน</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="bg-blue-100 rounded-lg p-2">
                            <i data-lucide="calendar-plus" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">ข้อมูลการจอง</h3>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center space-x-2 mb-2">
                                <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                                <span class="font-medium text-red-800">เกิดข้อผิดพลาด</span>
                            </div>
                            <ul class="list-disc list-inside text-red-700 text-sm space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo escapeOutput($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                        
                        <!-- Personal Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="booker_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    ชื่อผู้จอง <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                                    </div>
                                    <input type="text" id="booker_name" name="booker_name" required
                                           value="<?php echo escapeOutput($_POST['booker_name'] ?? ''); ?>"
                                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                           placeholder="กรอกชื่อ-นามสกุล">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="booker_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    เบอร์โทรศัพท์
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="phone" class="w-5 h-5 text-gray-400"></i>
                                    </div>
                                    <input type="tel" id="booker_phone" name="booker_phone"
                                           value="<?php echo escapeOutput($_POST['booker_phone'] ?? ''); ?>"
                                           class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                           placeholder="หมายเลขโทรศัพท์">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="booker_email" class="block text-sm font-medium text-gray-700 mb-2">
                                อีเมล
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="email" id="booker_email" name="booker_email"
                                       value="<?php echo escapeOutput($_POST['booker_email'] ?? ''); ?>"
                                       class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                       placeholder="email@example.com">
                            </div>
                        </div>

                        <!-- Booking Information -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">รายละเอียดการจอง</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="form-group">
                                    <label for="booking_date" class="block text-sm font-medium text-gray-700 mb-2">
                                        วันที่จอง <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="calendar" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="date" id="booking_date" name="booking_date" required
                                               value="<?php echo escapeOutput($selected_date); ?>"
                                               min="<?php echo date('Y-m-d'); ?>"
                                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                                        เวลาเริ่มต้น <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="clock" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="time" id="start_time" name="start_time" required
                                               value="<?php echo escapeOutput($_POST['start_time'] ?? ''); ?>"
                                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                                        เวลาสิ้นสุด <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="clock" class="w-5 h-5 text-gray-400"></i>
                                        </div>
                                        <input type="time" id="end_time" name="end_time" required
                                               value="<?php echo escapeOutput($_POST['end_time'] ?? ''); ?>"
                                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                วัตถุประสงค์การใช้ห้อง
                            </label>
                            <div class="relative">
                                <div class="absolute top-3 left-3 pointer-events-none">
                                    <i data-lucide="file-text" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <textarea id="purpose" name="purpose" rows="4"
                                          class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                                          placeholder="ระบุวัตถุประสงค์การใช้ห้องประชุม..."><?php echo escapeOutput($_POST['purpose'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Time Availability Check -->
                        <div id="availability-check" class="bg-gray-50 rounded-lg p-4 hidden">
                            <div class="flex items-center space-x-2 mb-3">
                                <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                                <span class="font-medium text-gray-900">ตรวจสอบความพร้อม</span>
                            </div>
                            <div id="availability-result"></div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6">
                            <button type="button" onclick="checkAvailability()" 
                                    class="flex-1 bg-blue-100 text-blue-700 px-6 py-3 rounded-lg font-medium hover:bg-blue-200 transition-colors flex items-center justify-center space-x-2">
                                <i data-lucide="search" class="w-5 h-5"></i>
                                <span>ตรวจสอบความพร้อม</span>
                            </button>
                            
                            <button type="submit" 
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                <i data-lucide="calendar-check" class="w-5 h-5"></i>
                                <span>ยืนยันการจอง</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Auto-set end time when start time changes
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = this.value;
            if (startTime) {
                const [hours, minutes] = startTime.split(':');
                const startDate = new Date(2000, 0, 1, parseInt(hours), parseInt(minutes));
                startDate.setHours(startDate.getHours() + 1); // Add 1 hour default
                
                const endHours = startDate.getHours().toString().padStart(2, '0');
                const endMinutes = startDate.getMinutes().toString().padStart(2, '0');
                
                const endTimeInput = document.getElementById('end_time');
                if (!endTimeInput.value) {
                    endTimeInput.value = `${endHours}:${endMinutes}`;
                }
            }
        });
        
        // Check availability function
        function checkAvailability() {
            const roomId = <?php echo $room_id; ?>;
            const date = document.getElementById('booking_date').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (!date || !startTime || !endTime) {
                alert('กรุณากรอกวันที่และเวลาให้ครบถ้วน');
                return;
            }
            
            if (startTime >= endTime) {
                alert('เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด');
                return;
            }
            
            const checkDiv = document.getElementById('availability-check');
            const resultDiv = document.getElementById('availability-result');
            
            checkDiv.classList.remove('hidden');
            resultDiv.innerHTML = '<div class="flex items-center space-x-2"><div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div><span class="text-gray-600">กำลังตรวจสอบ...</span></div>';
            
            fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `room_id=${roomId}&date=${date}&start_time=${startTime}&end_time=${endTime}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    resultDiv.innerHTML = '<div class="flex items-center space-x-2 text-green-600"><i data-lucide="check-circle" class="w-5 h-5"></i><span>ช่วงเวลานี้ว่างสามารถจองได้</span></div>';
                } else {
                    resultDiv.innerHTML = '<div class="flex items-center space-x-2 text-red-600"><i data-lucide="x-circle" class="w-5 h-5"></i><span>ช่วงเวลานี้ไม่ว่าง: ' + data.message + '</span></div>';
                }
                lucide.createIcons();
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="flex items-center space-x-2 text-red-600"><i data-lucide="alert-circle" class="w-5 h-5"></i><span>เกิดข้อผิดพลาดในการตรวจสอบ</span></div>';
                lucide.createIcons();
            });
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const bookingDate = document.getElementById('booking_date').value;
            
            if (startTime >= endTime) {
                e.preventDefault();
                alert('เวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด');
                return;
            }
            
            if (bookingDate < new Date().toISOString().split('T')[0]) {
                e.preventDefault();
                alert('ไม่สามารถจองวันที่ผ่านมาแล้วได้');
                return;
            }
        });
        
        // Auto-check availability when date/time changes
        ['booking_date', 'start_time', 'end_time'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                const availabilityDiv = document.getElementById('availability-check');
                if (!availabilityDiv.classList.contains('hidden')) {
                    checkAvailability();
                }
            });
        });
    </script>
</body>
</html>