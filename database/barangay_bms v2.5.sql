-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2026 at 03:09 PM
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
-- Database: `barangay_bms`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `category` enum('health','events','ordinance','programs','emergency','notice','general') NOT NULL DEFAULT 'general',
  `body` longtext NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `slug`, `category`, `body`, `thumbnail`, `is_published`, `published_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Aso mo, Bakuna ko', 'aso-mo-bakuna-ko', 'programs', 'Libreng bakuna para sa ating mga alagang aso sa ating covered court', '', 1, '2026-06-11 12:19:41', 6, '2026-06-11 12:19:41', '2026-06-11 12:19:41');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `table_name` varchar(60) DEFAULT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 11:23:41'),
(2, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 11:27:05'),
(3, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 11:55:48'),
(4, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 12:06:10'),
(5, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 12:21:29'),
(6, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 12:22:51'),
(7, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:30:20'),
(8, 1, 'Approved resident registration', 'residents', 1, NULL, '{\"registration_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:30:37'),
(9, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:33:43'),
(10, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:35:12'),
(11, 1, 'Processed document request', 'document_requests', 1, NULL, '{\"status\":\"processing\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:36:32'),
(12, 1, 'Sent request for Captain approval', 'document_requests', 1, NULL, '{\"status\":\"for_approval\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:36:47'),
(13, 1, 'doc_approved', 'document_requests', 1, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:38:07'),
(14, 1, 'Released document request', 'document_requests', 1, NULL, '{\"status\":\"released\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:38:44'),
(15, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-07 13:39:55'),
(16, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 04:23:06'),
(17, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 11:02:48'),
(18, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 11:08:25'),
(19, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 11:10:17'),
(20, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-08 12:52:20'),
(21, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 12:55:38'),
(22, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 14:02:17'),
(23, 5, 'login_success', 'users', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 14:39:02'),
(24, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 15:03:54'),
(25, 1, 'Processed document request', 'document_requests', 3, NULL, '{\"status\":\"processing\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 15:34:28'),
(26, 1, 'Sent request for Captain approval', 'document_requests', 3, NULL, '{\"status\":\"for_approval\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 15:35:21'),
(27, 1, 'doc_approved', 'document_requests', 3, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-08 15:35:35'),
(28, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-09 11:53:47'),
(29, 1, 'admin_account_created', 'users', 6, NULL, '{\"role\":\"kagawad\",\"email\":\"rey@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-09 12:09:51'),
(30, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-09 12:11:57'),
(31, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-09 13:06:31'),
(32, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-09 13:54:52'),
(33, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-06-09 15:09:21'),
(34, 3, 'login_success', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-09 15:17:03'),
(35, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 10:57:18'),
(36, 6, 'project_archived', 'projects', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 11:03:56'),
(37, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 13:05:01'),
(38, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 14:20:47'),
(39, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 14:21:18'),
(40, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 14:27:22'),
(41, 1, 'login_success', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 14:28:21'),
(42, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 14:31:25'),
(43, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-10 14:33:50'),
(44, 6, 'progress_update', 'projects', 5, NULL, '{\"progress_percent\":null}', NULL, NULL, '2026-06-10 16:01:49'),
(45, 6, 'progress_update', 'projects', 5, NULL, '{\"progress_percent\":null}', NULL, NULL, '2026-06-10 16:03:56'),
(46, 6, 'progress_update', 'projects', 5, NULL, '{\"progress_percent\":null}', NULL, NULL, '2026-06-10 16:05:25'),
(47, 6, 'progress_update', 'projects', 5, NULL, '{\"status\":\"Completed\"}', NULL, NULL, '2026-06-10 16:08:27'),
(48, 6, 'progress_update', 'projects', 5, NULL, '{\"progress_percent\":0}', NULL, NULL, '2026-06-10 16:08:34'),
(49, 6, 'progress_update', 'projects', 5, NULL, '{\"progress_percent\":0}', NULL, NULL, '2026-06-10 16:08:40'),
(50, 6, 'progress_update', 'projects', 5, NULL, '{\"status\":\"Completed\"}', NULL, NULL, '2026-06-10 16:10:17'),
(51, 6, 'login_success', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-11 09:37:16'),
(52, 6, 'Created announcement', 'announcements', 1, NULL, '{\"published\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-11 12:19:41');

-- --------------------------------------------------------

--
-- Table structure for table `blotter_cases`
--

CREATE TABLE `blotter_cases` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_number` varchar(20) NOT NULL,
  `incident_date` datetime NOT NULL,
  `incident_type` varchar(80) NOT NULL,
  `incident_place` varchar(150) NOT NULL,
  `narrative` text NOT NULL,
  `status` enum('open','under_mediation','settled','escalated','closed') NOT NULL DEFAULT 'open',
  `resolution` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blotter_evidence`
--

CREATE TABLE `blotter_evidence` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(80) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blotter_hearings`
--

CREATE TABLE `blotter_hearings` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(10) UNSIGNED NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `location` varchar(150) NOT NULL DEFAULT 'Barangay Hall',
  `status` enum('scheduled','held','cancelled','rescheduled') NOT NULL DEFAULT 'scheduled',
  `minutes` text DEFAULT NULL,
  `presided_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blotter_parties`
--

CREATE TABLE `blotter_parties` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED DEFAULT NULL,
  `party_type` enum('complainant','respondent','witness') NOT NULL,
  `non_resident_name` varchar(120) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `statement` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_items`
--

CREATE TABLE `budget_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `fiscal_year` year(4) NOT NULL,
  `category` varchar(80) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `allocated_amount` decimal(12,2) NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_items`
--

INSERT INTO `budget_items` (`id`, `fiscal_year`, `category`, `description`, `allocated_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(8, '2026', 'Infrastructure', 'Salaries', 50000.00, 3, '2026-06-08 06:17:23', '2026-06-08 06:17:23');

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `id` int(10) UNSIGNED NOT NULL,
  `or_number` varchar(20) NOT NULL,
  `request_id` int(10) UNSIGNED DEFAULT NULL,
  `resident_id` int(10) UNSIGNED DEFAULT NULL,
  `source_type` enum('document_fee','business_permit','cedula','other') NOT NULL DEFAULT 'document_fee',
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `collected_by` int(10) UNSIGNED NOT NULL,
  `collected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `voided` tinyint(1) NOT NULL DEFAULT 0,
  `void_reason` text DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `collections`
--

INSERT INTO `collections` (`id`, `or_number`, `request_id`, `resident_id`, `source_type`, `amount`, `description`, `collected_by`, `collected_at`, `voided`, `void_reason`, `voided_at`, `voided_by`) VALUES
(1, 'OR-2026-56280', 1, 1, 'document_fee', 75.00, 'Barangay Clearance fee for BR-2026-00001', 1, '2026-06-07 13:38:07', 0, NULL, NULL, NULL),
(2, 'OR-2026-00002', NULL, NULL, 'business_permit', 25.00, 'Fee', 3, '2026-06-06 16:00:00', 0, NULL, NULL, NULL),
(3, 'OR-2026-00003', NULL, NULL, 'document_fee', 500.00, 'Fee', 3, '2026-06-06 16:00:00', 0, NULL, NULL, NULL),
(4, 'OR-2026-09151', 3, 1, 'document_fee', 50.00, 'Certificate of Residency fee for BR-2026-00003', 1, '2026-06-08 15:35:35', 0, NULL, NULL, NULL),
(5, 'OR-2026-00005', NULL, NULL, 'document_fee', 75.00, 'Fee', 3, '2026-06-07 16:00:00', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_no` varchar(20) NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `doc_type_id` int(10) UNSIGNED NOT NULL,
  `purpose` text NOT NULL,
  `extra_details` longtext DEFAULT NULL,
  `status` enum('pending','processing','for_approval','approved','released','cancelled','rejected') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `released_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `reference_no`, `resident_id`, `doc_type_id`, `purpose`, `extra_details`, `status`, `remarks`, `processed_by`, `approved_by`, `released_by`, `processed_at`, `approved_at`, `released_at`, `created_at`, `updated_at`) VALUES
(1, 'BR-2026-00001', 1, 1, 'Utilities', '[]', 'released', NULL, 1, 1, 1, '2026-06-07 13:36:32', '2026-06-07 13:38:07', '2026-06-07 13:38:44', '2026-06-07 13:33:10', '2026-06-07 13:38:44'),
(2, 'BR-2026-00002', 1, 4, 'Employment', '{\"business_name\":\"Maharlika\",\"business_type\":\"Rice Retail\",\"business_address\":\"Basta po\"}', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-07 13:44:10', '2026-06-07 13:44:10'),
(3, 'BR-2026-00003', 1, 2, 'Secret', '[]', 'approved', NULL, 1, 1, NULL, '2026-06-08 15:34:28', '2026-06-08 15:35:35', NULL, '2026-06-07 16:25:55', '2026-06-08 15:35:35'),
(4, 'BR-2026-00004', 1, 2, 'Scholarship', '[]', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-08 15:33:51', '2026-06-08 15:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(40) NOT NULL,
  `fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  `processing_days` tinyint(4) NOT NULL DEFAULT 1,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `template_html` longtext DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `name`, `slug`, `fee`, `processing_days`, `requires_approval`, `template_html`, `description`, `requirements`, `is_active`, `created_at`) VALUES
(1, 'Barangay Clearance', 'barangay-clearance', 75.00, 1, 1, NULL, NULL, 'Valid government ID; Proof of residency', 1, '2026-06-04 13:39:14'),
(2, 'Certificate of Residency', 'certificate-residency', 50.00, 1, 1, NULL, NULL, 'Valid government ID; Proof of address or utility bill', 1, '2026-06-04 13:39:14'),
(3, 'Certificate of Indigency', 'certificate-indigency', 0.00, 1, 1, NULL, NULL, 'Valid government ID; Proof of residency; Supporting document for assistance request if available', 1, '2026-06-04 13:39:14'),
(4, 'Business Clearance', 'business-clearance', 300.00, 2, 1, NULL, NULL, 'Valid government ID; Proof of business address; Business registration document if available', 1, '2026-06-04 13:39:14'),
(5, 'Barangay Certification', 'barangay-certification', 50.00, 1, 1, NULL, NULL, 'Valid government ID; Supporting document for the certification type', 1, '2026-06-04 13:39:14'),
(6, 'Blotter Certificate', 'blotter-certificate', 100.00, 2, 1, NULL, NULL, 'Valid government ID; Blotter case reference number', 1, '2026-06-04 13:39:14');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `committee` varchar(100) NOT NULL,
  `event_date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('planned','upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `start_time` time DEFAULT NULL,
  `expected_attendees` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `committee`, `event_date`, `location`, `status`, `created_by`, `created_at`, `updated_at`, `start_time`, `expected_attendees`) VALUES
(1, 'Clean-up Drive', NULL, 'Environment', '2026-06-20', 'Barangay Hall', 'upcoming', NULL, '2026-06-09 14:24:02', '2026-06-09 14:24:02', NULL, NULL),
(2, 'Youth Leadership Seminar', NULL, 'Youth Affairs', '2026-06-25', 'Covered Court', 'upcoming', NULL, '2026-06-09 14:24:02', '2026-06-09 14:24:02', NULL, NULL),
(3, 'Tree Planting Activity', NULL, 'Environment', '2026-07-05', 'Riverside Area', 'planned', NULL, '2026-06-09 14:24:02', '2026-06-09 14:24:02', NULL, NULL),
(4, 'Wash Wash', 'Washing machineeee', 'Health & Sanitation', '2026-06-12', 'Barangay Hall', 'completed', NULL, '2026-06-09 16:52:47', '2026-06-11 12:04:23', '00:00:00', 0),
(5, 'Cleanup Drive', 'Linis linis baga tayo', 'Health & Sanitation', '2026-06-15', 'Covered Court', 'upcoming', 6, '2026-06-11 12:05:25', '2026-06-11 12:05:25', '08:00:00', 100);

-- --------------------------------------------------------

--
-- Table structure for table `expenditures`
--

CREATE TABLE `expenditures` (
  `id` int(10) UNSIGNED NOT NULL,
  `category` varchar(80) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `disbursement_date` date NOT NULL,
  `payee` varchar(120) DEFAULT NULL,
  `supporting_doc_path` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approval_notes` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenditures`
--

INSERT INTO `expenditures` (`id`, `category`, `description`, `amount`, `disbursement_date`, `payee`, `supporting_doc_path`, `approval_status`, `approval_notes`, `approved_by`, `approved_at`, `recorded_by`, `created_at`) VALUES
(1, 'Supplies', 'Office supplies for barangay hall', 500.00, '2026-06-08', 'ABC Supplies', 'uploads/expenditures/doc_6a26448b0cca21.56303879.jpg', 'pending', NULL, NULL, NULL, 3, '2026-06-08 04:26:51'),
(2, 'Supplies', 'Office supplies for barangay hall', 500.00, '2026-06-08', 'ABC Supplies', 'uploads/expenditures/doc_6a26451f6e9750.51904526.jpg', 'approved', NULL, 3, NULL, 3, '2026-06-08 04:29:19');

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(10) UNSIGNED NOT NULL,
  `house_number` varchar(20) DEFAULT NULL,
  `street` varchar(100) NOT NULL,
  `purok` varchar(40) NOT NULL,
  `head_resident_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `households`
--

INSERT INTO `households` (`id`, `house_number`, `street`, `purok`, `head_resident_id`, `created_at`) VALUES
(1, 'S8 BLK3 L2', 'Basta', 'Purok 2', NULL, '2026-06-07 13:30:37');

-- --------------------------------------------------------

--
-- Table structure for table `issued_documents`
--

CREATE TABLE `issued_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `doc_number` varchar(30) NOT NULL,
  `qr_token` varchar(80) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `issued_documents`
--

INSERT INTO `issued_documents` (`id`, `request_id`, `doc_number`, `qr_token`, `pdf_path`, `issued_by`, `issued_at`) VALUES
(1, 1, 'DOC-2026-28405', 'a68ff0d46930950c1446e1644f162c1a4042cf192c0fbb9c6c9617e4c1dd9400', NULL, 1, '2026-06-07 13:38:07'),
(2, 3, 'DOC-2026-05220', '7f77b24561d407302063acc90d2fe2f2171e165d8533b9809bd2f0f506b39abe', NULL, 1, '2026-06-08 15:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(40) NOT NULL,
  `title` varchar(120) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(3, 1, 'captain_approval', 'Request awaiting approval', 'BR-2026-00001 (Barangay Clearance) is ready for Captain approval.', 'dashboard.php', 0, '2026-06-07 13:36:47'),
(7, 4, 'request_submitted', 'Document request submitted', 'Your request BR-2026-00002 has been submitted and is being reviewed.', 'request-detail.php?id=2', 0, '2026-06-07 13:44:10'),
(8, 4, 'request_submitted', 'Document request submitted', 'Your request BR-2026-00003 has been submitted and is being reviewed.', 'request-detail.php?id=3', 0, '2026-06-07 16:25:55'),
(9, 4, 'request_submitted', 'Document request submitted', 'Your request BR-2026-00004 has been submitted and is being reviewed.', 'request-detail.php?id=4', 0, '2026-06-08 15:33:51'),
(10, 1, 'captain_approval', 'Request awaiting approval', 'BR-2026-00003 (Certificate of Residency) is ready for Captain approval.', 'dashboard.php', 0, '2026-06-08 15:35:21'),
(11, 4, 'request_status', 'Request status updated', 'Your request BR-2026-00003 is awaiting Barangay Captain approval.', 'portal/request-detail.php?id=3', 0, '2026-06-08 15:35:21'),
(12, 4, 'request_status', 'Request status updated', 'Your Certificate of Residency is ready for pickup at the barangay hall.', 'portal/request-detail.php?id=3', 0, '2026-06-08 15:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `officials`
--

CREATE TABLE `officials` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `position` enum('captain','secretary','treasurer','kagawad','sk_chair','sk_kagawad') NOT NULL,
  `committee` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `term_start` date NOT NULL,
  `term_end` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `officials`
--

INSERT INTO `officials` (`id`, `user_id`, `position`, `committee`, `photo_path`, `term_start`, `term_end`, `is_active`, `created_at`) VALUES
(1, 6, 'kagawad', 'Health & Sanitation', NULL, '2026-06-09', '2027-06-09', 1, '2026-06-09 12:09:51');

-- --------------------------------------------------------

--
-- Table structure for table `pending_resident_registrations`
--

CREATE TABLE `pending_resident_registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `last_name` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `mobile_number` varchar(11) NOT NULL,
  `birth_date` date NOT NULL,
  `birth_place` varchar(120) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `civil_status` enum('single','married','widowed','separated','annulled') NOT NULL DEFAULT 'single',
  `nationality` varchar(60) NOT NULL DEFAULT 'Filipino',
  `occupation` varchar(80) DEFAULT NULL,
  `house_number` varchar(20) DEFAULT NULL,
  `street_name` varchar(100) NOT NULL,
  `purok_zone` varchar(40) DEFAULT NULL,
  `valid_id_path` varchar(255) NOT NULL,
  `valid_id_original_name` varchar(200) NOT NULL,
  `valid_id_mime_type` varchar(80) NOT NULL,
  `valid_id_size` int(10) UNSIGNED NOT NULL,
  `terms_agreed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_resident_registrations`
--

INSERT INTO `pending_resident_registrations` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `email`, `mobile_number`, `birth_date`, `birth_place`, `sex`, `civil_status`, `nationality`, `occupation`, `house_number`, `street_name`, `purok_zone`, `valid_id_path`, `valid_id_original_name`, `valid_id_mime_type`, `valid_id_size`, `terms_agreed_at`, `status`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 4, 'Akai', NULL, 'Shuichi', 'akaii9158@gmail.com', '09123456789', '2002-07-16', 'Trece Martires', 'male', 'single', 'Filipino', 'Astro', 'S8 BLK3 L2', 'Basta', 'Purok 2', 'uploads/valid_ids/valid_id_20260607_152951_ef8ac851d2da430d.jpg', 'nationalID.jpg', 'image/jpeg', 125970, '2026-06-07 07:29:51', 'approved', 1, '2026-06-07 13:30:37', '2026-06-07 13:29:51', '2026-06-07 13:30:37');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `committee` varchar(100) NOT NULL,
  `assigned_user_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(60) NOT NULL,
  `description` text NOT NULL,
  `status` enum('planning','ongoing','completed','on_hold') NOT NULL DEFAULT 'planning',
  `start_date` date NOT NULL,
  `target_end_date` date NOT NULL,
  `estimated_budget` decimal(12,2) DEFAULT NULL,
  `progress_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `target_beneficiaries` varchar(255) DEFAULT NULL,
  `photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`photos`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `committee`, `assigned_user_id`, `category`, `description`, `status`, `start_date`, `target_end_date`, `estimated_budget`, `progress_percent`, `archived_at`, `created_by`, `updated_by`, `created_at`, `updated_at`, `target_beneficiaries`, `photos`) VALUES
(1, 'Clean Up Drive', 'Environment', 6, 'Activity', 'Barangay clean-up project', 'ongoing', '2026-06-01', '2026-06-30', 5000.00, 30, NULL, 6, 6, '2026-06-09 14:33:22', '2026-06-09 14:33:22', NULL, NULL),
(2, 'Tree Planting Program', 'Environment', 6, 'Project', 'Plant trees in barangay area', 'completed', '2026-05-01', '2026-05-20', 8000.00, 100, NULL, 6, 6, '2026-06-09 14:33:22', '2026-06-09 14:33:22', NULL, NULL),
(3, 'Youth Sports League', 'Youth', 6, 'Event', 'Basketball league for youth', 'ongoing', '2026-06-10', '2026-07-10', 12000.00, 40, NULL, 6, 6, '2026-06-09 14:33:22', '2026-06-09 14:33:22', NULL, NULL),
(4, 'Health Awareness Drive', 'Health & Sanitation', 6, 'Program', 'Free check-up for residents', 'ongoing', '2026-06-01', '2026-06-30', 5000.00, 40, '2026-06-10 11:03:56', 6, 6, '2026-06-09 14:37:37', '2026-06-10 11:03:56', NULL, NULL),
(5, 'Clean Water Project', 'Health & Sanitation', 6, 'Project', 'Water testing and cleanup', 'completed', '2026-05-01', '2026-05-20', 8000.00, 0, NULL, 6, 6, '2026-06-09 14:37:37', '2026-06-10 16:08:34', NULL, NULL),
(6, 'Linisin mo basora mo', 'Health & Sanitation', NULL, 'Health', 'Linis linis pag may time baga', 'planning', '2026-06-15', '2026-06-29', 10000.00, 61, NULL, 6, NULL, '2026-06-10 14:58:15', '2026-06-10 15:01:32', '', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `project_photos`
--

CREATE TABLE `project_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(200) NOT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_attachments`
--

CREATE TABLE `request_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(200) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(60) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `request_attachments`
--

INSERT INTO `request_attachments` (`id`, `request_id`, `file_name`, `file_path`, `file_type`, `file_size`, `uploaded_at`) VALUES
(1, 1, 'nationalID.jpg', 'uploads/request_attachments/2026/request_20260607_213310_0bb2f869f137.jpg', 'image/jpeg', 125970, '2026-06-07 13:33:10'),
(2, 2, 'nationalID.jpg', 'uploads/request_attachments/2026/request_20260607_214410_61967e15691a.jpg', 'image/jpeg', 125970, '2026-06-07 13:44:10'),
(3, 3, 'nationalID.jpg', 'uploads/request_attachments/2026/request_20260608_002555_f4f7a15523c1.jpg', 'image/jpeg', 125970, '2026-06-07 16:25:55'),
(4, 4, 'nationalID.jpg', 'uploads/request_attachments/2026/request_20260608_233351_97a132110e39.jpg', 'image/jpeg', 125970, '2026-06-08 15:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `household_id` int(10) UNSIGNED DEFAULT NULL,
  `last_name` varchar(60) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `birth_place` varchar(120) NOT NULL,
  `sex` enum('male','female') NOT NULL,
  `civil_status` enum('single','married','widowed','separated','annulled') NOT NULL DEFAULT 'single',
  `nationality` varchar(60) NOT NULL DEFAULT 'Filipino',
  `religion` varchar(60) DEFAULT NULL,
  `occupation` varchar(80) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `philsys_id` varchar(30) DEFAULT NULL,
  `is_voter` tinyint(1) NOT NULL DEFAULT 0,
  `is_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `is_solo_parent` tinyint(1) NOT NULL DEFAULT 0,
  `is_4ps` tinyint(1) NOT NULL DEFAULT 0,
  `is_senior` tinyint(1) NOT NULL DEFAULT 0,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `status` enum('active','deceased','transferred') NOT NULL DEFAULT 'active',
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `user_id`, `household_id`, `last_name`, `first_name`, `middle_name`, `suffix`, `birth_date`, `birth_place`, `sex`, `civil_status`, `nationality`, `religion`, `occupation`, `contact_number`, `email`, `philsys_id`, `is_voter`, `is_pwd`, `is_solo_parent`, `is_4ps`, `is_senior`, `valid_id_path`, `status`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 'Shuichi', 'Akai', NULL, NULL, '2002-07-16', 'Trece Martires', 'male', 'single', 'Filipino', NULL, 'Astro', '09123456789', 'akaii9158@gmail.com', NULL, 0, 0, 0, 0, 0, 'uploads/valid_ids/valid_id_20260607_152951_ef8ac851d2da430d.jpg', 'active', 1, '2026-06-07 13:30:37', '2026-06-07 13:30:37', '2026-06-07 13:30:37');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` varchar(40) NOT NULL,
  `module` varchar(80) NOT NULL,
  `can_read` tinyint(1) NOT NULL DEFAULT 0,
  `can_write` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `fullname` varchar(150) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `purok` varchar(40) DEFAULT NULL,
  `role` enum('captain','secretary','treasurer','kagawad','resident') NOT NULL DEFAULT 'resident',
  `status` enum('active','pending','suspended') NOT NULL DEFAULT 'pending',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `fullname`, `contact`, `purok`, `role`, `status`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin_captain', 'captain@starosa1.gov.ph', '$2y$10$NxUkh6fsfW6BqjTvz7EQ4u/GQ6dsA17rQ3af5kj.nkBeLCnM0cea.', 'Juan Reyes', NULL, NULL, 'captain', 'active', '2026-06-10 14:28:21', '2026-06-04 13:39:14', '2026-06-10 14:28:21'),
(2, 'cena', 'cena@gmail.com', '$2y$10$NxUkh6fsfW6BqjTvz7EQ4u/GQ6dsA17rQ3af5kj.nkBeLCnM0cea.', 'John Cena', '09123456789', 'Purok 2', 'resident', 'active', NULL, '2026-06-04 13:40:58', '2026-06-04 13:41:53'),
(3, 'treasurer', 'treasurer@gmail.com', '$2y$10$NxUkh6fsfW6BqjTvz7EQ4u/GQ6dsA17rQ3af5kj.nkBeLCnM0cea.', 'Maria Makiling', '0987654321', 'Purok 2', 'treasurer', 'active', '2026-06-09 15:17:02', '2026-06-06 12:30:13', '2026-06-09 15:17:02'),
(4, 'akaii9158@gmail.com', 'akaii9158@gmail.com', '$2y$10$NxUkh6fsfW6BqjTvz7EQ4u/GQ6dsA17rQ3af5kj.nkBeLCnM0cea.', 'Akai Shuichi', '09123456789', 'Purok 2', 'resident', 'active', NULL, '2026-06-07 13:29:51', '2026-06-11 09:36:17'),
(5, 'secretary', 'sec@gmail.com', '$2y$10$NxUkh6fsfW6BqjTvz7EQ4u/GQ6dsA17rQ3af5kj.nkBeLCnM0cea.', 'Minami Fujiwara', '09723123118', '2', 'secretary', 'active', '2026-06-08 14:39:01', '2026-06-08 14:37:45', '2026-06-08 14:39:01'),
(6, 'rey', 'rey@gmail.com', '$2y$10$NxUkh6fsfW6BqjTvz7EQ4u/GQ6dsA17rQ3af5kj.nkBeLCnM0cea.', 'Rey Mysterio', NULL, NULL, 'kagawad', 'active', '2026-06-11 09:37:16', '2026-06-09 12:09:51', '2026-06-11 09:37:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ann_slug` (`slug`),
  ADD KEY `idx_ann_published` (`is_published`),
  ADD KEY `idx_ann_category` (`category`),
  ADD KEY `fk_ann_creator` (`created_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_date` (`created_at`);

--
-- Indexes for table `blotter_cases`
--
ALTER TABLE `blotter_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blotter_case_no` (`case_number`),
  ADD KEY `idx_blotter_status` (`status`),
  ADD KEY `idx_blotter_date` (`incident_date`),
  ADD KEY `fk_blotter_recorder` (`recorded_by`);

--
-- Indexes for table `blotter_evidence`
--
ALTER TABLE `blotter_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blotter_evidence_case` (`case_id`),
  ADD KEY `fk_blotter_evidence_user` (`uploaded_by`);

--
-- Indexes for table `blotter_hearings`
--
ALTER TABLE `blotter_hearings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hearings_case` (`case_id`),
  ADD KEY `idx_hearings_status` (`status`),
  ADD KEY `fk_hearings_presider` (`presided_by`);

--
-- Indexes for table `blotter_parties`
--
ALTER TABLE `blotter_parties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parties_case` (`case_id`),
  ADD KEY `idx_parties_resident` (`resident_id`);

--
-- Indexes for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_budget_year` (`fiscal_year`),
  ADD KEY `idx_budget_category` (`category`),
  ADD KEY `fk_budget_creator` (`created_by`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_collections_or` (`or_number`),
  ADD KEY `idx_collections_request` (`request_id`),
  ADD KEY `idx_collections_resident` (`resident_id`),
  ADD KEY `idx_collections_date` (`collected_at`),
  ADD KEY `fk_collections_collector` (`collected_by`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requests_ref` (`reference_no`),
  ADD KEY `idx_requests_resident` (`resident_id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_type` (`doc_type_id`),
  ADD KEY `fk_requests_proc` (`processed_by`),
  ADD KEY `fk_requests_appr` (`approved_by`),
  ADD KEY `fk_requests_rel` (`released_by`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_doc_types_slug` (`slug`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_events_user` (`created_by`);

--
-- Indexes for table `expenditures`
--
ALTER TABLE `expenditures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expenditures_date` (`disbursement_date`),
  ADD KEY `idx_expenditures_category` (`category`),
  ADD KEY `fk_expenditures_approver` (`approved_by`),
  ADD KEY `fk_expenditures_recorder` (`recorded_by`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_households_purok` (`purok`),
  ADD KEY `fk_households_head` (`head_resident_id`);

--
-- Indexes for table `issued_documents`
--
ALTER TABLE `issued_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_issued_doc_no` (`doc_number`),
  ADD UNIQUE KEY `uq_issued_qr` (`qr_token`),
  ADD UNIQUE KEY `uq_issued_request` (`request_id`),
  ADD KEY `fk_issued_by` (`issued_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_is_read` (`is_read`);

--
-- Indexes for table `officials`
--
ALTER TABLE `officials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_officials_active` (`is_active`),
  ADD KEY `fk_officials_user` (`user_id`);

--
-- Indexes for table `pending_resident_registrations`
--
ALTER TABLE `pending_resident_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pending_reg_user` (`user_id`),
  ADD KEY `idx_pending_reg_status` (`status`),
  ADD KEY `idx_pending_reg_email` (`email`),
  ADD KEY `fk_pending_reg_reviewer` (`reviewed_by`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_committee` (`committee`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `idx_projects_assigned` (`assigned_user_id`);

--
-- Indexes for table `project_photos`
--
ALTER TABLE `project_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_photos_project` (`project_id`);

--
-- Indexes for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attach_request` (`request_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_residents_last` (`last_name`),
  ADD KEY `idx_residents_status` (`status`),
  ADD KEY `idx_residents_user` (`user_id`),
  ADD KEY `idx_residents_hh` (`household_id`),
  ADD KEY `fk_residents_verifier` (`verified_by`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_module` (`role`,`module`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `blotter_cases`
--
ALTER TABLE `blotter_cases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blotter_evidence`
--
ALTER TABLE `blotter_evidence`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blotter_hearings`
--
ALTER TABLE `blotter_hearings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blotter_parties`
--
ALTER TABLE `blotter_parties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_items`
--
ALTER TABLE `budget_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenditures`
--
ALTER TABLE `expenditures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `issued_documents`
--
ALTER TABLE `issued_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `officials`
--
ALTER TABLE `officials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pending_resident_registrations`
--
ALTER TABLE `pending_resident_registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_photos`
--
ALTER TABLE `project_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `request_attachments`
--
ALTER TABLE `request_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blotter_cases`
--
ALTER TABLE `blotter_cases`
  ADD CONSTRAINT `fk_blotter_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `blotter_evidence`
--
ALTER TABLE `blotter_evidence`
  ADD CONSTRAINT `fk_blotter_evidence_case` FOREIGN KEY (`case_id`) REFERENCES `blotter_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blotter_evidence_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blotter_hearings`
--
ALTER TABLE `blotter_hearings`
  ADD CONSTRAINT `fk_hearings_case` FOREIGN KEY (`case_id`) REFERENCES `blotter_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hearings_presider` FOREIGN KEY (`presided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blotter_parties`
--
ALTER TABLE `blotter_parties`
  ADD CONSTRAINT `fk_parties_case` FOREIGN KEY (`case_id`) REFERENCES `blotter_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parties_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `budget_items`
--
ALTER TABLE `budget_items`
  ADD CONSTRAINT `fk_budget_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `collections`
--
ALTER TABLE `collections`
  ADD CONSTRAINT `fk_collections_collector` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_collections_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_collections_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `fk_requests_appr` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_proc` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_rel` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_type` FOREIGN KEY (`doc_type_id`) REFERENCES `document_types` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenditures`
--
ALTER TABLE `expenditures`
  ADD CONSTRAINT `fk_expenditures_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_expenditures_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_head` FOREIGN KEY (`head_resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `issued_documents`
--
ALTER TABLE `issued_documents`
  ADD CONSTRAINT `fk_issued_by` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_issued_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `officials`
--
ALTER TABLE `officials`
  ADD CONSTRAINT `fk_officials_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pending_resident_registrations`
--
ALTER TABLE `pending_resident_registrations`
  ADD CONSTRAINT `fk_pending_reg_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_reg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `request_attachments`
--
ALTER TABLE `request_attachments`
  ADD CONSTRAINT `fk_attach_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `fk_residents_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_residents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_residents_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
