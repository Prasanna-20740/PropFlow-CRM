-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 09, 2026 at 09:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `propflow_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `lead_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `booked_by` int(10) UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Confirmed','Cancelled') NOT NULL DEFAULT 'Confirmed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `lead_id`, `unit_id`, `booked_by`, `booking_date`, `amount`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-09-08', 5000000.00, 'Confirmed', 'Initial booking payment received.', '2026-09-08 06:41:52', '2026-09-08 06:41:52'),
(2, 2, 1, 3, '2026-09-08', 4500000.00, 'Confirmed', 'Booking for Arun Kumar - Lead 2', '2026-09-08 13:01:01', '2026-09-08 13:01:01'),
(3, 3, 1, 3, '2026-09-08', 3500000.00, 'Confirmed', 'Booking for Prasanna P', '2026-09-08 13:02:09', '2026-09-08 13:02:09'),
(4, 4, 1, 3, '2026-09-08', 5200000.00, 'Confirmed', 'Booking for Vinoth', '2026-09-08 13:02:09', '2026-09-08 13:02:09'),
(6, 3, 2, 1, '2026-09-09', 9000000.00, 'Confirmed', NULL, '2026-09-09 07:04:12', '2026-09-09 07:04:12');

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `floors` int(10) UNSIGNED DEFAULT 1,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `project_id`, `name`, `floors`, `description`, `status`, `created_at`) VALUES
(1, 1, 'Tower A', 10, 'Main residential tower', 'active', '2026-09-08 06:31:13'),
(2, 2, 'Tower A', 10, 'new', 'active', '2026-09-09 06:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `stage` enum('New','Contacted','Site Visit','Interested','Negotiation','Booked','Lost') NOT NULL DEFAULT 'New',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `name`, `phone`, `email`, `source`, `stage`, `assigned_to`, `follow_up_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Arun Kumar', '0123456789', 'arun@gmail.com', 'Website', 'Interested', 2, '2026-09-09', 'Interested in 2 BHK apartment.', '2026-09-08 05:50:29', '2026-09-08 06:01:53'),
(2, 'Arun Kumar', '0123456789', 'arun@gmail.com', 'Website', 'Booked', 3, '2026-09-09', 'New employeee', '2026-09-08 11:43:55', '2026-09-08 11:48:41'),
(3, 'Prasanna P', '06379567596', 'prasanna@gmail.com', 'Walk-in', 'Booked', 3, '2026-02-20', 'New Employee', '2026-09-08 11:57:43', '2026-09-08 11:58:49'),
(4, 'Vinoth', '1234554123', 'prasanna@gmail.com', 'Referral', 'Booked', 3, '2026-09-09', 'New Em[ployee', '2026-09-08 12:00:10', '2026-09-08 12:47:20');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `location` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `location`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Green Valley Residency', 'Chennai', 'Premium residential apartment project', 'active', '2026-09-08 06:30:12', '2026-09-08 06:30:12'),
(2, 'Sunrise Residency', 'Chennai', 'Tower A', 'active', '2026-09-09 06:57:43', '2026-09-09 06:57:43');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(10) UNSIGNED NOT NULL,
  `building_id` int(10) UNSIGNED NOT NULL,
  `unit_number` varchar(50) NOT NULL,
  `type` enum('1 BHK','2 BHK','3 BHK','4 BHK','Villa','Commercial') NOT NULL,
  `floor` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('available','reserved','booked','blocked') NOT NULL DEFAULT 'available',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `building_id`, `unit_number`, `type`, `floor`, `price`, `status`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'A-101', '2 BHK', 1, 4500000.00, 'booked', 'Main residential tower', '2026-09-08 06:32:05', '2026-09-08 06:41:52'),
(2, 2, 'A-101', '2 BHK', 1, 8500000.00, 'booked', 'new', '2026-09-09 06:59:10', '2026-09-09 07:04:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','sales') NOT NULL DEFAULT 'sales',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'System Admin', 'admin@propflow.com', '$2y$10$cMrknxNjeoA0geYUAT/DvOjvTNWAc7Saq6xPyZE70kGC7/isUsasC', 'admin', 'active', '2026-09-08 05:34:36', '2026-09-08 05:34:36'),
(2, 'Sales Employee', 'sales@propflow.com', '$2y$10$WKEgiBENCHQ/Uui24RGxCOaMRFKERUewPxOUpzlzflcyxCqUWPgk.', 'sales', 'active', '2026-09-08 05:34:36', '2026-09-08 05:34:36'),
(3, 'Sales Executive', 'salese1@propflow.com', '$2y$10$99E5ejjmbcC1VLY1Rd3crOXNJERu6QQocFX8.1xHuNKcb0Z6C2Qhe', 'sales', 'active', '2026-09-08 09:26:19', '2026-09-08 09:26:19'),
(4, 'Vijay C', 'vijay26@gmail.com', '$2y$10$tENOCy748y9TW2Od1mKDVON0RIXM9xprxDQv3mrhYrYGdAqb0UYw6', 'sales', 'active', '2026-09-09 04:42:59', '2026-09-09 04:42:59'),
(5, 'Test Sales', 'testsales@gmail.com', '$2y$10$nP.fUr2df973OMUpZ6fpFOnIrEWuBKu7mmqAv47kZNLkwQ8PsKiri', 'sales', 'active', '2026-09-09 06:34:58', '2026-09-09 06:34:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bookings_user` (`booked_by`),
  ADD KEY `idx_bookings_unit` (`unit_id`),
  ADD KEY `idx_bookings_lead` (`lead_id`),
  ADD KEY `idx_bookings_date` (`booking_date`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_buildings_project` (`project_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leads_stage` (`stage`),
  ADD KEY `idx_leads_assigned` (`assigned_to`),
  ADD KEY `idx_leads_followup` (`follow_up_date`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_unit_building` (`building_id`,`unit_number`),
  ADD KEY `idx_units_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`booked_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `buildings`
--
ALTER TABLE `buildings`
  ADD CONSTRAINT `fk_buildings_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `fk_leads_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `fk_units_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
