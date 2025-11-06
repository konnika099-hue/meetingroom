-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2025 at 01:23 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `meeting_room_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `email`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ดูแลระบบ', 'admin@example.com', '2025-06-14 13:39:17', '2025-06-14 13:39:17');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `booker_name` varchar(100) NOT NULL,
  `booker_email` varchar(100) DEFAULT NULL,
  `booker_phone` varchar(20) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `room_id`, `booker_name`, `booker_email`, `booker_phone`, `booking_date`, `start_time`, `end_time`, `purpose`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'นายสมชาย ใจดี', 'somchai@example.com', '081-234-5678', '2025-06-15', '09:00:00', '12:00:00', 'ประชุมคณะกรรมการ', 'confirmed', '2025-06-14 13:39:17', '2025-06-14 13:39:17'),
(2, 2, 'นางสาวสมหญิง รักงาน', 'somying@example.com', '082-345-6789', '2025-06-16', '14:00:00', '16:00:00', 'ประชุมทีมพัฒนา', 'confirmed', '2025-06-14 13:39:17', '2025-06-14 13:39:17'),
(3, 3, 'นายประสิทธิ์ ขยันทำงาน', 'prasit@example.com', '083-456-7890', '2025-06-17', '10:00:00', '15:00:00', 'อบรมพนักงานใหม่', 'cancelled', '2025-06-14 13:39:17', '2025-06-14 14:03:57'),
(4, 1, 'แบงค์', 'r@r.com', '0852817096', '2025-06-14', '22:00:00', '23:00:00', 'ประชุมครับ', 'confirmed', '2025-06-14 14:02:47', '2025-06-14 14:03:37'),
(5, 1, 'แบงค์', 'r@r.com', '0852817096', '2025-06-21', '19:00:00', '20:00:00', 'ประชุม', 'confirmed', '2025-06-21 11:00:55', '2025-06-21 11:02:15');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_rooms`
--

CREATE TABLE `meeting_rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meeting_rooms`
--

INSERT INTO `meeting_rooms` (`id`, `room_name`, `description`, `capacity`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ห้องประชุมใหญ่', 'ห้องประชุมสำหรับการประชุมใหญ่ พร้อมอุปกรณ์ครบครัน', 50, 'uploads/684d7d52986e3.jpg', 'active', '2025-06-14 13:39:17', '2025-06-14 13:46:58'),
(2, 'ห้องประชุมเล็ก', 'ห้องประชุมสำหรับการประชุมย่อย เหมาะสำหรับทีมเล็ก', 10, 'uploads/684d7d07789cd.jpg', 'active', '2025-06-14 13:39:17', '2025-06-14 13:45:43'),
(3, 'ห้องสัมมนา', 'ห้องสำหรับจัดสัมมนาและอบรม พร้อมเครื่องเสียง', 100, 'uploads/684d7d6a831c0.jpg', 'active', '2025-06-14 13:39:17', '2025-06-14 13:47:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_date` (`room_id`,`booking_date`),
  ADD KEY `idx_booking_date` (`booking_date`);

--
-- Indexes for table `meeting_rooms`
--
ALTER TABLE `meeting_rooms`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `meeting_rooms`
--
ALTER TABLE `meeting_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `meeting_rooms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
