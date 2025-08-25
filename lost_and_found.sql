-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 12, 2025 at 10:44 PM
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
-- Database: `lost_and_found`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `announcement_type` enum('public','admin_only') NOT NULL DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `admin_id`, `content`, `created_at`, `announcement_type`) VALUES
(1, 1, '為了進一步提升系統的安全性，保障您的個人資料與使用體驗，所有用戶務必於2050年5月15日前完成密碼更新。新的密碼要求必須至少包含32位字元，並且需包含至少一個大寫字母、一個小寫字母、一個數字以及一個特殊字符（例如 !@#$%^&*）。若您在更新過程中遇到任何問題，或有其他相關疑問，請隨時聯繫系統管理員，感謝您的配合與支持。', '2025-06-10 05:44:47', 'public'),
(2, 1, '網頁程式課程結束後 \"關閉網站\"，謝謝大家👍', '2025-06-10 05:45:09', 'public'),
(15, 1, 'TEST', '2025-06-13 04:07:18', 'public');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `announcement_id`, `user_id`, `is_read`, `read_at`) VALUES
(1410, 2, 2, 0, NULL),
(1411, 1, 2, 0, NULL),
(1464, 2, 3, 0, NULL),
(1465, 1, 3, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`id`, `item_id`, `user_id`, `status`, `created_at`, `updated_at`, `rejected_at`) VALUES
(22, 21, 2, 'pending', '2025-06-11 17:37:41', '2025-06-11 09:37:41', NULL),
(23, 16, 2, 'pending', '2025-06-11 17:37:47', '2025-06-11 09:37:47', NULL),
(24, 1, 3, 'pending', '2025-06-12 16:04:56', '2025-06-12 08:04:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(19, 'jvktk@punkproof.com', 'e23549033a7365b095b8b05d896ee01c41fcb8e5dc93be80c9d93ec00cfe5e5f', '2025-06-13 15:13:28', '2025-06-12 15:13:28'),
(20, 'jvktk@punkproof.com', '15a3c409cde911a9b7a6e33ecd7ef92c5d1692278c0d3476a94282facb21c7f8', '2025-06-13 15:14:41', '2025-06-12 15:14:41'),
(21, '7znih@punkproof.com', '6e834c04e07caaa7a3fdb65a273ae7b4141537448c623a1466db26cc1f46ad1e', '2025-06-13 15:41:09', '2025-06-12 15:41:09'),
(22, 'g6xof@punkproof.com', '65851589a76f4b043c9322442f64a4e5151cc6a01fb34b5ff2bd4145a99fa414', '2025-06-13 16:05:20', '2025-06-12 16:05:20'),
(23, 'g6xof@punkproof.com', '53a142a6d6cacebb08148c3081c20b201e3c1e45f8d93b6cf3da894d457c714d', '2025-06-13 16:13:37', '2025-06-12 16:13:37');

-- --------------------------------------------------------

--
-- Table structure for table `lost_items`
--

CREATE TABLE `lost_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(100) NOT NULL,
  `status` enum('available','claimed') NOT NULL,
  `created_at` datetime NOT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `item_type` enum('lost_by_user','found_by_user') NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lost_items`
--

INSERT INTO `lost_items` (`id`, `user_id`, `title`, `description`, `location`, `status`, `created_at`, `approval_status`, `item_type`, `updated_at`, `approved_at`, `ended_at`, `image`, `rejected_at`) VALUES
(1, 2, '遺失錢包', '黑色皮夾，內有證件', '22.60577255727531,120.31334624521024', '', '2025-06-09 22:00:00', 'approved', 'lost_by_user', '2025-06-12 20:31:03', '2025-06-10 05:47:44', NULL, 'lost_1_1_1749760263_cd55.jpg', '2025-06-10 14:27:14'),
(2, 2, '遺失手機', '白色 iPhone 15', '22.634584872348903,120.29083475471394', 'available', '2025-06-09 22:05:00', 'approved', 'lost_by_user', '2025-06-11 10:48:54', NULL, NULL, 'lost_1_2_1749638934_f389.jpg', NULL),
(3, 2, '遺失鑰匙', '紅色鑰匙圈', '22.717948448450098,120.31096640022177', 'available', '2025-06-09 22:10:00', 'approved', 'lost_by_user', '2025-06-11 08:32:37', NULL, NULL, 'lost_1_3_1749630757_068c.jpg', NULL),
(4, 2, '遺失書包', '黑色背包，內有筆記本', '22.733270525165484,120.28076896971587', 'available', '2025-06-09 22:15:00', 'approved', 'lost_by_user', '2025-06-11 08:34:00', NULL, NULL, 'lost_1_4_1749630840_c9f7.jpg', NULL),
(5, 2, '遺失手錶', '銀色手錶，防水', '22.713532324310684,120.32484386039809', 'available', '2025-06-09 22:20:00', 'approved', 'lost_by_user', '2025-06-11 08:34:38', NULL, NULL, 'lost_1_5_1749630878_f2cf.jpg', '2025-06-11 16:23:55'),
(7, 2, '遺失筆記本', '紅色封面筆記本', '22.64210082770943,120.28346092365922', 'available', '2025-06-09 22:30:00', 'approved', 'lost_by_user', '2025-06-11 08:35:33', NULL, NULL, 'lost_1_7_1749630933_d89e.jpg', NULL),
(8, 2, '遺失充電器', 'USB-C 充電器', '22.710963712800563,120.31833901399125', 'available', '2025-06-09 22:35:00', 'approved', 'lost_by_user', '2025-06-11 10:49:37', NULL, NULL, 'lost_1_8_1749638977_715f.jpg', NULL),
(9, 2, '遺失水壺', '藍色保溫杯', '22.57922492094792,120.33932283967486', 'available', '2025-06-09 22:40:00', 'approved', 'lost_by_user', '2025-06-11 08:37:23', NULL, NULL, 'lost_1_9_1749631043_1e61.jpg', NULL),
(10, 2, '遺失證件', '學生證，藍色', '22.688125562759247,120.29418673022462', 'available', '2025-06-09 22:45:00', 'approved', 'lost_by_user', '2025-06-10 04:35:13', NULL, NULL, 'lost_1_10_1749530113_428b.png', NULL),
(11, 2, '拾獲錢包', '黑色皮夾，內有現金', '22.60291798911733,120.38955688476562', 'available', '2025-06-09 22:50:00', 'approved', 'found_by_user', '2025-06-11 08:38:40', NULL, NULL, 'found_1_11_1749631120_60c9.jpg', '2025-06-10 14:28:41'),
(12, 2, '拾獲手機', '黑色 Samsung', '22.68680793962115,120.27029338383561', 'available', '2025-06-09 22:55:00', 'approved', 'found_by_user', '2025-06-11 09:22:37', NULL, NULL, 'found_1_12_1749631159_7505.jpg', NULL),
(13, 2, '拾獲鑰匙', '金色鑰匙圈', '22.698497134924025,120.32027975471394', 'available', '2025-06-09 23:00:00', 'approved', 'found_by_user', '2025-06-11 10:50:27', NULL, NULL, 'found_1_13_1749639027_eed3.jpg', NULL),
(14, 2, '拾獲書包', '紅色背包，內有書籍', '22.73695480167452,120.30204201160386', 'available', '2025-06-09 23:05:00', 'approved', 'found_by_user', '2025-06-11 10:51:01', NULL, NULL, 'found_2_4_1749483552.png', NULL),
(15, 2, '拾獲手錶', '金色手錶', '22.55693356120865,120.34501159363512', 'available', '2025-06-09 23:10:00', 'approved', 'found_by_user', '2025-06-11 10:52:28', NULL, NULL, 'found_2_5_1749483553.png', NULL),
(16, 3, '遺失眼鏡', '黑色框架眼鏡', '22.643589908718553,120.28474187342029', '', '2025-06-09 23:15:00', 'approved', 'lost_by_user', '2025-06-11 11:03:23', NULL, NULL, 'lost_1_16_1749639803_6c09.jpg', NULL),
(17, 3, '遺失錢包', '棕色皮夾', '22.630840941601193,120.28853101160391', 'available', '2025-06-09 23:20:00', 'approved', 'lost_by_user', '2025-06-12 20:32:51', NULL, NULL, 'lost_1_17_1749760371_e388.jpg', NULL),
(18, 3, '遺失手機', '紅色手機殼', '22.60330990928739,120.3021041702953', 'available', '2025-06-09 23:25:00', 'approved', 'lost_by_user', '2025-06-11 10:54:22', NULL, NULL, 'lost_1_18_1749639262_07b2.jpg', NULL),
(19, 3, '遺失鑰匙', '藍色鑰匙圈', '22.681000637754046,120.30256541883043', 'available', '2025-06-09 23:30:00', 'approved', 'lost_by_user', '2025-06-11 10:54:55', NULL, NULL, 'lost_1_19_1749639295_30ff.jpg', NULL),
(20, 3, '遺失筆記本', '黑色筆記本', '22.551071212500858,120.35076604334215', 'available', '2025-06-09 23:35:00', 'approved', 'lost_by_user', '2025-06-11 11:00:35', NULL, NULL, 'lost_1_20_1749639635_97c4.jpg', NULL),
(21, 3, '拾獲耳機', '白色耳機', '22.726723815750834,120.30551887927967', '', '2025-06-09 23:40:00', 'approved', 'found_by_user', '2025-06-11 11:00:07', NULL, NULL, 'found_1_21_1749639607_ae09.jpg', NULL),
(22, 3, '拾獲水壺', '綠色水壺', '22.610250638772225,120.30371208045152', 'available', '2025-06-09 23:45:00', 'approved', 'found_by_user', '2025-06-11 10:56:38', NULL, NULL, 'found_1_22_1749639398_731b.jpg', NULL),
(23, 3, '拾獲充電器', 'Type-C 充電器', '22.6246,120.2818', 'available', '2025-06-09 23:50:00', 'approved', 'found_by_user', '2025-06-11 10:57:13', '2025-06-10 12:28:49', NULL, 'found_1_23_1749639433_81e3.jpg', NULL),
(24, 3, '拾獲書包', '藍色背包', '22.636193960941217,120.29376983642578', 'available', '2025-06-09 23:55:00', 'approved', 'found_by_user', '2025-06-11 10:57:44', '2025-06-10 12:29:16', NULL, 'found_1_24_1749639464_b025.jpg', NULL),
(82, 3, 'TEST', 'TEST', '22.61703640194097,120.24991673860063', 'available', '2025-06-12 16:05:51', 'pending', 'lost_by_user', '2025-06-12 08:05:51', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(247, 2, '您的失物「遺失手錶」申報已被拒絕，請在 24 小時後重新編輯或提交。', 0, '2025-06-11 16:23:55'),
(248, 2, '管理員已編輯您的失物「遺失手機」。', 0, '2025-06-11 16:31:55'),
(249, 2, '管理員已編輯您的失物「遺失鑰匙」。', 0, '2025-06-11 16:32:37'),
(250, 2, '管理員已編輯您的失物「遺失書包」。', 0, '2025-06-11 16:34:00'),
(251, 2, '管理員已編輯您的失物「遺失手錶」。', 0, '2025-06-11 16:34:38'),
(252, 2, '管理員已編輯您的失物「遺失筆記本」。', 0, '2025-06-11 16:35:33'),
(253, 2, '管理員已編輯您的失物「遺失充電器」。', 0, '2025-06-11 16:36:32'),
(254, 2, '管理員已編輯您的失物「遺失水壺」。', 0, '2025-06-11 16:37:23'),
(255, 2, '管理員已編輯您的失物「拾獲錢包」。', 0, '2025-06-11 16:38:40'),
(256, 2, '管理員已編輯您的失物「拾獲手機測試測試測試」。', 0, '2025-06-11 16:39:19'),
(257, 2, '管理員已編輯您的失物「拾獲鑰匙」。', 0, '2025-06-11 16:39:48'),
(258, 2, '管理員已編輯您的失物「拾獲手機」。', 0, '2025-06-11 17:22:37'),
(259, 2, '您的帳號於 2025-06-11 17:23:20 在另一裝置上登入。', 0, '2025-06-11 17:23:20'),
(260, 1, '新失物申請待審核：拾獲耳機（申請者學號: u0000001，失物ID: 21）', 0, '2025-06-11 17:37:41'),
(261, 3, '您的失物「拾獲耳機」（ID: 21）有新的領取申請（申請者學號: u0000001），待管理員審核。', 0, '2025-06-11 17:37:41'),
(262, 1, '新失物申請待審核：遺失眼鏡（申請者學號: u0000001，失物ID: 16）', 0, '2025-06-11 17:37:47'),
(263, 3, '您的失物「遺失眼鏡」（ID: 16）有新的領取申請（申請者學號: u0000001），待管理員審核。', 0, '2025-06-11 17:37:47'),
(264, 2, '管理員已編輯您的失物「遺失手機」。', 0, '2025-06-11 18:48:54'),
(265, 2, '管理員已編輯您的失物「遺失充電器」。', 0, '2025-06-11 18:49:37'),
(266, 2, '管理員已編輯您的失物「拾獲鑰匙」。', 0, '2025-06-11 18:50:27'),
(267, 2, '管理員已編輯您的失物「拾獲書包」。', 0, '2025-06-11 18:51:01'),
(268, 2, '管理員已編輯您的失物「拾獲手錶」。', 0, '2025-06-11 18:52:28'),
(269, 3, '管理員已編輯您的失物「遺失眼鏡」。', 0, '2025-06-11 18:53:14'),
(270, 3, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-11 18:53:46'),
(271, 3, '管理員已編輯您的失物「遺失手機」。', 0, '2025-06-11 18:54:22'),
(272, 3, '管理員已編輯您的失物「遺失鑰匙」。', 0, '2025-06-11 18:54:55'),
(273, 3, '管理員已編輯您的失物「遺失筆記本」。', 0, '2025-06-11 18:55:37'),
(274, 3, '管理員已編輯您的失物「拾獲耳機」。', 0, '2025-06-11 18:56:08'),
(275, 3, '管理員已編輯您的失物「拾獲水壺」。', 0, '2025-06-11 18:56:38'),
(276, 3, '管理員已編輯您的失物「拾獲充電器」。', 0, '2025-06-11 18:57:13'),
(277, 3, '管理員已編輯您的失物「拾獲書包」。', 0, '2025-06-11 18:57:44'),
(278, 3, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-11 18:58:50'),
(279, 3, '管理員已編輯您的失物「拾獲耳機」。', 0, '2025-06-11 19:00:07'),
(280, 3, '管理員已編輯您的失物「遺失筆記本」。', 0, '2025-06-11 19:00:35'),
(281, 3, '管理員已編輯您的失物「遺失眼鏡」。', 0, '2025-06-11 19:03:23'),
(282, 1, '新失物申請待審核：遺失錢包（申請者學號: u0000002，失物ID: 1）', 0, '2025-06-12 16:04:56'),
(283, 2, '您的失物「遺失錢包」（ID: 1）有新的領取申請（申請者學號: u0000002），待管理員審核。', 0, '2025-06-12 16:04:56'),
(284, 1, '新遺失物品申報待審核：TEST', 0, '2025-06-12 16:05:51'),
(285, 1, '您的帳號於 2025-06-12 16:41:14 在另一裝置上登入。', 0, '2025-06-12 16:41:14'),
(286, 1, '您的帳號於 2025-06-13 04:04:16 在另一裝置上登入。', 0, '2025-06-13 04:04:16'),
(287, 1, '您的帳號於 2025-06-13 04:13:34 在另一裝置上登入。', 0, '2025-06-13 04:13:34'),
(288, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:18:13'),
(289, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:18:31'),
(290, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:24:21'),
(291, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:26:04'),
(292, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:29:00'),
(293, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:30:02'),
(294, 2, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:31:03'),
(295, 3, '管理員已編輯您的失物「遺失錢包」。', 0, '2025-06-13 04:32:51');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(256) NOT NULL,
  `role` enum('user','admin','disabled') NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone` varchar(20) NOT NULL,
  `uID` varchar(10) NOT NULL,
  `last_name` varchar(20) NOT NULL,
  `first_name` varchar(20) NOT NULL,
  `nickname` varchar(20) DEFAULT NULL,
  `address` varchar(256) DEFAULT NULL,
  `sID` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `session_token` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `email`, `email_verified`, `phone`, `uID`, `last_name`, `first_name`, `nickname`, `address`, `sID`, `created_at`, `updated_at`, `approval_status`, `session_token`, `last_login`, `device_info`) VALUES
(1, 'admin', '$2y$10$XUxnQnJJpauo5KO7bMTOa.4ly6qRU/nDnOV/MeHv005tLydfFrmSC', 'admin', 'admin@lostandfound.com', 1, '0912345678', 'A123456789', '陳', '總管', '大陳', '台灣, 台北市, 中正區', 'a0000001', '2025-05-10 08:00:00', '2025-06-12 20:13:34', 'approved', 'om8jmb82keuo8u3sheadnoefh9', '2025-06-12 20:13:34', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 | IP: 127.0.0.1'),
(2, 'user1', '$2y$10$BBVeSXOMROjyqHxhAHi2quFflgElaxRmXmL/w5O4NrzbC6amIQ5be', 'user', 'jvktk@punkproof.com', 1, '0911111111', 'U111111111', '王', '大明', '老王', '美國, 佛羅里達州', 'u0000001', '2025-06-10 04:44:58', '2025-06-12 07:40:39', 'approved', NULL, '2025-06-11 09:23:20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 | IP: 127.0.0.1'),
(3, 'user2', '$2y$10$xkgRa4YahqaCwTUizbbNyOxc1EdIIvaQbwPHcJmsz3H.O6NpCduIm', 'user', 'g6xof@punkproof.com', 1, '0922222222', 'U222222222', '張', '大明', '張超', '日本, 京都府, 四条河原町', 'u0000002', '2025-06-10 04:49:19', '2025-06-12 08:04:50', 'approved', '18gsvt0r4gp9454nhrkd2h5nfe', '2025-06-12 08:04:50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 | IP: 127.0.0.1'),
(4, 'user3', '$2y$10$WNGRXf74FvEuwnQMSx.Yqebfa7zxnYuUreR5iL8QgbMFRCBPjAuom', 'user', 'a76l9@punkproof.com', 1, '0933333333', 'U333333333', 'andy', 'hang', 'andy', '加拿大 多倫多', 'u0000003', '2025-06-10 04:52:14', '2025-06-11 08:23:41', 'pending', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`announcement_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_user_unique` (`item_id`,`user_id`),
  ADD KEY `claims_user_fk` (`user_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_fk` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uID` (`uID`),
  ADD UNIQUE KEY `sID` (`sID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1476;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lost_items`
--
ALTER TABLE `lost_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=296;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_item_fk` FOREIGN KEY (`item_id`) REFERENCES `lost_items` (`id`),
  ADD CONSTRAINT `claims_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD CONSTRAINT `lost_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
