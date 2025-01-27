-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2025 at 06:01 PM
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
-- Database: `signease`
--

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `signed_file_path` varchar(255) DEFAULT NULL,
  `drive_link` varchar(255) DEFAULT NULL,
  `requirements` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `signed_at` timestamp NULL DEFAULT NULL,
  `status` enum('sent','pending','signed','completed') NOT NULL DEFAULT 'sent',
  `due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_metadata`
--

CREATE TABLE `document_metadata` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `author` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `original_filename` varchar(255) NOT NULL,
  `metadata_version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_details`
--

CREATE TABLE `login_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_details`
--

INSERT INTO `login_details` (`id`, `user_id`, `login_time`) VALUES
(320, 54, '2025-01-16 14:40:54'),
(321, 54, '2025-01-16 15:32:17'),
(322, 54, '2025-01-19 17:27:07'),
(323, 56, '2025-01-19 17:28:39'),
(324, 65, '2025-01-19 19:42:36'),
(325, 66, '2025-01-19 20:36:22'),
(326, 54, '2025-01-20 04:17:43'),
(327, 66, '2025-01-20 04:51:50'),
(328, 65, '2025-01-21 09:30:47'),
(329, 66, '2025-01-21 09:31:59'),
(330, 56, '2025-01-21 09:34:25'),
(331, 67, '2025-01-21 09:34:53'),
(332, 54, '2025-01-26 16:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires`) VALUES
(1, 'sandunlb2001@gmail.com', '67f282a3c4b4cd8bfea52647da5f4012e2cd6e82982d20983994073704387ee1497f14345318f319d6699a1dcece5d603e4c', '2025-01-26 18:22:39'),
(2, 'sandunlb2001@gmail.com', 'bcdcf7054d5b8cadd22a776b534ad323bd7d269b433b9c90a246a18220d0d8c8fb45294ed478d2fe187f7bc26c146d1b26aa', '2025-01-26 18:24:15'),
(3, 'sandunlb2001@gmail.com', '42b9f77381ac42dfc8534c814af90f98910ba437edab492c03c54d3b4bc40ee8aced9a44af0877dad81323717e1de0cbdebf', '2025-01-26 18:29:55'),
(4, 'test1@gmail.com', 'b74f9a86bb086f927d6eca142c4d1578b66ca7c0f3c691e5b03f886f7f3bd931ffb50cb582dab73841986f09050ee8948186', '2025-01-26 18:35:00'),
(5, 'sandunlb2001@gmail.com', 'fb7e1806290fb0c0e3dcdbf02a141be540fe9d23dbc9c804e917d80b848003f98a1cf32805bb5efb7b4a8fcdf730e43eb537', '2025-01-26 18:50:40'),
(6, 'test1@gmail.com', '9cfa176566eb9ca62a09d41c4c8f412b342d41379b0d540332cd8bd4fb1a27125f1193f685a26666e67002a8b70507aa427d', '2025-01-26 18:51:35'),
(7, 'test1@gmail.com', 'fb3fd21e58a95acdc9ee2a5867a5dc726ccf9e56585888f285ba5be918342446a24edd3e3fc1c5176d302ef68c9d7152f73f', '2025-01-26 18:54:53'),
(8, 'sandunlb2001@gmail.com', 'd9159fa20f107ddf50aa83086a07b8a62101bda02a78277e59fd96905026030ef0120bd9d1167c793d88cad87cafc477db11', '2025-01-26 18:55:29'),
(9, 'sandunlb2001@gmail.com', '3fd5f79548e9629ae4b84b18eaa4e88ef2d414ab9eedaefb51d33b3bc33a2c6720eb92613551aa9f63df3cfa4fee48654f32', '2025-01-26 18:58:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nic` varchar(12) NOT NULL,
  `position` varchar(255) NOT NULL,
  `faculty` varchar(255) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `name` varchar(255) NOT NULL,
  `employee_number` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `nic`, `position`, `faculty`, `mobile`, `name`, `employee_number`, `role`, `status`, `created_at`) VALUES
(54, 'test1', 'test1@gmail.com', '$2y$10$r7VQ1P4/uPPT/tnxsIebZ.PipiCSA/tbajEPEiMayaAA1m7ylK.MW', '123456789', 'vc', 'arts', '123', 'test1', '123', 'user', 'approved', '2024-12-24 07:44:00'),
(55, 'test2', 'test2@gmail.com', '$2y$10$8IUq5E7.b6QpkRdsgr4gYe2vMEpN5TVB83x5vk7JoZi9mXe8oA70a', '1234567890', 'dvc', 'arts', '222', 'test2', '222', 'user', 'approved', '2024-12-24 07:44:00'),
(56, 'Admin', 'admin@admin.com', '$2y$10$BdYJV.tFQiepPNXEs36XzO0PzUoJQ87elx7yUD7av4eQZ4A0J.Mrm', '00000001', 'directors', 'agriculture', '123', 'Administrator', '123', 'admin', 'approved', '2024-12-24 07:44:00'),
(65, 'Sandun Lakshitha', 'sandunlb2001@gmail.com', '$2y$10$8xOhBW636gQ8Noj8zpbDE.2udQB.1iuxYfmhka0muIoxaktxJNcaS', '200135002322', 'directors', 'engineering', '0702325700', 'Sandun Lakshitha', '1212121', 'user', 'approved', '2025-01-19 19:42:00'),
(66, 'Lakshitha Bandara', 'outlawlife1899rdr2@gmail.com', '$2y$10$pQvkwaaXPEQEO42G26u0RuWwEvk0xAnQ7XaJufI3pHV3dxYzk4SOe', '200140002077', 'registrar/AR', 'engineering', '0717875748', 'Lakshitha Bandara', '222', 'user', 'approved', '2025-01-19 20:35:55'),
(67, 'VC', 'vcyberpunkslb2000@gmail.com', '$2y$10$zDuIJuYzgNK3qVOuwKXqzefUeE9owzOWwpIv86MRBWMm2pPDlQU.i', '200210002077', 'directors', 'management', '0123456789', 'VC', '12122', 'user', 'approved', '2025-01-21 09:33:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `document_metadata`
--
ALTER TABLE `document_metadata`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `idx_document_hash` (`hash`);

--
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `login_details`
--
ALTER TABLE `login_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_id_login` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `document_metadata`
--
ALTER TABLE `document_metadata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `login_details`
--
ALTER TABLE `login_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=333;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_metadata`
--
ALTER TABLE `document_metadata`
  ADD CONSTRAINT `document_metadata_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `login_details`
--
ALTER TABLE `login_details`
  ADD CONSTRAINT `fk_user_id_login` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `login_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
