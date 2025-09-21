-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 07, 2025 at 10:13 AM
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
-- Database: `workconnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `customer_matching_preferences`
--

CREATE TABLE `customer_matching_preferences` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `skill_weight` decimal(3,2) DEFAULT 0.30,
  `location_weight` decimal(3,2) DEFAULT 0.20,
  `budget_weight` decimal(3,2) DEFAULT 0.20,
  `experience_weight` decimal(3,2) DEFAULT 0.15,
  `rating_weight` decimal(3,2) DEFAULT 0.10,
  `availability_weight` decimal(3,2) DEFAULT 0.05,
  `max_distance_km` int(11) DEFAULT 50,
  `min_rating` decimal(3,2) DEFAULT 3.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `proposed_rate` decimal(10,2) NOT NULL,
  `proposed_timeline` varchar(100) DEFAULT NULL,
  `cover_message` text NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_applications`
--

INSERT INTO `job_applications` (`id`, `job_id`, `worker_id`, `proposed_rate`, `proposed_timeline`, `cover_message`, `status`, `applied_at`, `updated_at`) VALUES
(1, 1, 1, 1200.00, '1 week', 'dfwsdf sadfasf asf asfdasf asfasf', 'accepted', '2025-08-28 22:05:14', '2025-09-03 17:43:33'),
(2, 2, 1, 5000.00, '2 days', 'i can fix this but need to look at the issue first so contact me asap', 'accepted', '2025-09-04 17:09:28', '2025-09-04 19:59:44'),
(3, 2, 2, 4000.00, '2 days', 'Can fix this but have to take a look first, then we have to discuss an price that is fair, if youre intrested contact me asap', 'rejected', '2025-09-04 17:12:39', '2025-09-04 19:59:44'),
(4, 3, 2, 4000.00, '2 days', 'I CAN FIX THIS , NEEDS TO TAKE A LOOK AT THE ISSUE FIRST', 'accepted', '2025-09-06 06:35:50', '2025-09-06 06:37:42'),
(5, 4, 2, 4000.00, '2 days', 'HAVE TO LOOK AT THE ISSUE FIRST', 'pending', '2025-09-06 17:55:21', '2025-09-06 17:55:21');

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `location_address` text NOT NULL,
  `budget_min` decimal(10,2) DEFAULT NULL,
  `budget_max` decimal(10,2) DEFAULT NULL,
  `budget_type` enum('fixed','hourly','negotiable') DEFAULT 'negotiable',
  `urgency` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','assigned','completed','cancelled','paused') DEFAULT 'open',
  `assigned_worker_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`id`, `client_id`, `service_id`, `title`, `description`, `location_address`, `budget_min`, `budget_max`, `budget_type`, `urgency`, `status`, `assigned_worker_id`, `created_at`, `updated_at`) VALUES
(1, 2, 4, 'Clean the roof top', 'Example Desc', 'Kurunegala', 5000.00, 10000.00, 'fixed', 'medium', 'completed', 1, '2025-08-28 22:00:53', '2025-09-04 04:05:00'),
(2, 2, 19, 'Fix the roof top', 'Need to repair the top of the roof, its leaking when it is raining, some sheets are broken.', 'Kurunegala', 5000.00, 6000.00, 'fixed', 'medium', 'completed', 1, '2025-09-04 17:08:13', '2025-09-04 20:00:12'),
(3, 2, 7, 'Fix Leaking Kitchen', 'Need to fix my kitchen faucet, its leaking', 'Kurunegala', NULL, NULL, 'negotiable', 'high', 'completed', 2, '2025-09-06 06:34:38', '2025-09-06 06:40:19'),
(4, 2, 19, 'Fix roof', 'fix roof fix roof fix roof', 'Kurunegala', 5000.00, 6000.00, 'fixed', 'medium', 'open', NULL, '2025-09-06 17:47:15', '2025-09-06 17:47:15');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `job_id`, `message`, `is_read`, `sent_at`) VALUES
(1, 2, 3, NULL, 'hi', 1, '2025-09-03 16:23:41'),
(2, 2, 4, NULL, 'hey im intrested in your offer please contact me', 1, '2025-09-04 18:32:17'),
(3, 4, 2, 3, 'Hi! I\'ve completed the job \'Fix Leaking Kitchen\' and would greatly appreciate your feedback and rating. Your review helps me build my reputation and serve future clients better. Thank you!', 1, '2025-09-06 08:46:01'),
(4, 4, 2, NULL, 'Hi, I&#039;m interested in your roofing job. When can we discuss?', 1, '2025-09-06 18:00:55');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewee_id` int(11) NOT NULL,
  `reviewer_type` enum('customer','worker') NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `quality_rating` int(1) DEFAULT NULL,
  `timeliness_rating` int(1) DEFAULT NULL,
  `communication_rating` int(1) DEFAULT NULL,
  `payment_rating` int(1) DEFAULT NULL,
  `clarity_rating` int(1) DEFAULT NULL,
  `recommend` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `job_id`, `reviewer_id`, `reviewee_id`, `reviewer_type`, `rating`, `review_text`, `quality_rating`, `timeliness_rating`, `communication_rating`, `payment_rating`, `clarity_rating`, `recommend`, `created_at`) VALUES
(1, 2, 2, 3, 'customer', 4, 'none', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 20:33:31');

-- --------------------------------------------------------

--
-- Table structure for table `salary_predictions`
--

CREATE TABLE `salary_predictions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `predicted_salary` decimal(10,2) NOT NULL,
  `prediction_data` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_predictions`
--

INSERT INTO `salary_predictions` (`id`, `user_id`, `predicted_salary`, `prediction_data`, `created_at`) VALUES
(1, 4, 5893.15, '{\"industry\":\"D\",\"occupation\":3,\"yrs_qual\":12,\"sex\":1,\"highest_qual\":12,\"area_of_study\":1,\"influencing\":2,\"negotiating\":3,\"sector\":1,\"workforce_change\":1,\"no_subordinates\":1,\"choose_hours\":2,\"choose_method\":1,\"job_quals\":10,\"qual_needed\":1,\"experience_needed\":2,\"keeping_current\":2,\"satisfaction\":3,\"advising\":3,\"instructing\":2,\"problem_solving_quick\":4,\"problem_solving_long\":4,\"labour\":1,\"manual_skill\":2,\"computer\":1,\"group_meetings\":1,\"computer_level\":2}', '2025-09-06 00:17:45');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `category_id`, `service_name`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'Furniture Assembly', NULL, 1, '2025-08-28 21:40:11'),
(2, 1, 'Door Installation', NULL, 1, '2025-08-28 21:40:11'),
(3, 1, 'Window Repair', NULL, 1, '2025-08-28 21:40:11'),
(4, 2, 'House Cleaning', NULL, 1, '2025-08-28 21:40:11'),
(5, 2, 'Office Cleaning', NULL, 1, '2025-08-28 21:40:11'),
(6, 2, 'Deep Cleaning', NULL, 1, '2025-08-28 21:40:11'),
(7, 3, 'Pipe Repair', NULL, 1, '2025-08-28 21:40:11'),
(8, 3, 'Drain Cleaning', NULL, 1, '2025-08-28 21:40:11'),
(9, 3, 'Water Heater Installation', NULL, 1, '2025-08-28 21:40:11'),
(10, 4, 'Light Installation', NULL, 1, '2025-08-28 21:40:11'),
(11, 4, 'Outlet Repair', NULL, 1, '2025-08-28 21:40:11'),
(12, 4, 'Ceiling Fan Installation', NULL, 1, '2025-08-28 21:40:11'),
(13, 5, 'Lawn Mowing', NULL, 1, '2025-08-28 21:40:11'),
(14, 5, 'Garden Maintenance', NULL, 1, '2025-08-28 21:40:11'),
(15, 5, 'Landscaping', NULL, 1, '2025-08-28 21:40:11'),
(16, 6, 'Interior Painting', NULL, 1, '2025-08-28 21:40:11'),
(17, 6, 'Exterior Painting', NULL, 1, '2025-08-28 21:40:11'),
(18, 6, 'Wall Preparation', NULL, 1, '2025-08-28 21:40:11'),
(19, 1, 'Roof Repair', 'Roof maintenance and leak repair', 1, '2025-09-04 15:42:05'),
(20, 1, 'Flooring Installation', 'Install tiles, wood, and vinyl flooring', 1, '2025-09-04 15:42:05'),
(21, 1, 'Cabinet Installation', 'Kitchen and bathroom cabinet installation', 1, '2025-09-04 15:42:05'),
(22, 2, 'Carpet Cleaning', 'Professional carpet and upholstery cleaning', 1, '2025-09-04 15:42:05'),
(23, 2, 'Window Cleaning', 'Interior and exterior window cleaning', 1, '2025-09-04 15:42:05'),
(24, 2, 'Pressure Washing', 'High-pressure cleaning of driveways and walls', 1, '2025-09-04 15:42:05'),
(25, 3, 'Bathroom Installation', 'Complete bathroom fixture installation', 1, '2025-09-04 15:42:05'),
(26, 3, 'Septic Tank Cleaning', 'Septic system maintenance and cleaning', 1, '2025-09-04 15:42:05'),
(27, 4, 'Solar Panel Installation', 'Solar energy system installation', 1, '2025-09-04 15:42:05'),
(28, 4, 'Electrical Inspection', 'Safety inspection of electrical systems', 1, '2025-09-04 15:42:05'),
(29, 4, 'Generator Installation', 'Backup generator setup and maintenance', 1, '2025-09-04 15:42:05'),
(30, 5, 'Tree Removal', 'Safe removal of large trees and stumps', 1, '2025-09-04 15:42:05'),
(31, 5, 'Irrigation Installation', 'Garden watering system installation', 1, '2025-09-04 15:42:05'),
(32, 5, 'Pest Control', 'Garden and home pest management', 1, '2025-09-04 15:42:05'),
(33, 6, 'Roof Painting', 'Exterior roof painting and weatherproofing', 1, '2025-09-04 15:42:05'),
(34, 6, 'Texture Painting', 'Decorative wall texturing and finishing', 1, '2025-09-04 15:42:05'),
(35, 7, 'Refrigerator Repair', 'Repair and maintenance of refrigerators', 1, '2025-09-04 15:42:05'),
(36, 7, 'Washing Machine Repair', 'Washing machine troubleshooting and repair', 1, '2025-09-04 15:42:05'),
(37, 7, 'Air Conditioner Repair', 'AC unit repair and maintenance', 1, '2025-09-04 15:42:05'),
(38, 7, 'Microwave Repair', 'Microwave oven repair services', 1, '2025-09-04 15:42:05'),
(39, 7, 'Water Pump Repair', 'Water pump installation and repair', 1, '2025-09-04 15:42:05'),
(40, 8, 'Computer Repair', 'Desktop and laptop computer repair', 1, '2025-09-04 15:42:05'),
(41, 8, 'Network Setup', 'Home and office network installation', 1, '2025-09-04 15:42:05'),
(42, 8, 'Software Installation', 'Software setup and troubleshooting', 1, '2025-09-04 15:42:05'),
(43, 8, 'Data Recovery', 'Recovery of lost digital data', 1, '2025-09-04 15:42:05'),
(44, 8, 'CCTV Installation', 'Security camera system installation', 1, '2025-09-04 15:42:05'),
(45, 9, 'House Moving', 'Complete household moving services', 1, '2025-09-04 15:42:05'),
(46, 9, 'Office Relocation', 'Business and office moving services', 1, '2025-09-04 15:42:05'),
(47, 9, 'Furniture Delivery', 'Furniture transport and delivery', 1, '2025-09-04 15:42:05'),
(48, 9, 'Package Delivery', 'Local package and document delivery', 1, '2025-09-04 15:42:05'),
(49, 10, 'Dog Walking', 'Regular dog walking services', 1, '2025-09-04 15:42:05'),
(50, 10, 'Pet Sitting', 'Pet care while owners are away', 1, '2025-09-04 15:42:05'),
(51, 10, 'Pet Grooming', 'Professional pet bathing and grooming', 1, '2025-09-04 15:42:05'),
(52, 10, 'Veterinary Transport', 'Pet transportation to vet appointments', 1, '2025-09-04 15:42:05'),
(53, 11, 'Car Washing', 'Professional vehicle cleaning services', 1, '2025-09-04 15:42:05'),
(54, 11, 'Basic Car Repair', 'Minor automotive repairs and maintenance', 1, '2025-09-04 15:42:05'),
(55, 11, 'Tire Change', 'Tire replacement and repair services', 1, '2025-09-04 15:42:05'),
(56, 11, 'Car Interior Cleaning', 'Deep cleaning of vehicle interiors', 1, '2025-09-04 15:42:05'),
(57, 12, 'Security Guard', 'Professional security personnel services', 1, '2025-09-04 15:42:05'),
(58, 12, 'Lock Installation', 'Door and window lock installation', 1, '2025-09-04 15:42:05'),
(59, 12, 'Alarm System Setup', 'Home security system installation', 1, '2025-09-04 15:42:05'),
(60, 13, 'Math Tutoring', 'Mathematics tutoring for all levels', 1, '2025-09-04 15:42:05'),
(61, 13, 'English Tutoring', 'English language tutoring services', 1, '2025-09-04 15:42:05'),
(62, 13, 'Computer Training', 'Basic computer skills training', 1, '2025-09-04 15:42:05'),
(63, 13, 'Music Lessons', 'Private music instruction', 1, '2025-09-04 15:42:05'),
(64, 14, 'Party Planning', 'Birthday and celebration party planning', 1, '2025-09-04 15:42:05'),
(65, 14, 'Catering Service', 'Food catering for events', 1, '2025-09-04 15:42:05'),
(66, 14, 'Photography', 'Event and portrait photography', 1, '2025-09-04 15:42:05'),
(67, 14, 'DJ Services', 'Music and entertainment for events', 1, '2025-09-04 15:42:05'),
(68, 15, 'Massage Therapy', 'Professional therapeutic massage', 1, '2025-09-04 15:42:05'),
(69, 15, 'Personal Training', 'Fitness coaching and training', 1, '2025-09-04 15:42:05'),
(70, 15, 'Yoga Instruction', 'Private yoga lessons', 1, '2025-09-04 15:42:05'),
(71, 15, 'Elderly Care', 'Companion care for elderly persons', 1, '2025-09-04 15:42:05'),
(72, 16, 'Notary Services', 'Document notarization services', 1, '2025-09-04 15:42:05'),
(73, 16, 'Translation Services', 'Document and verbal translation', 1, '2025-09-04 15:42:05'),
(74, 16, 'Tailoring', 'Clothing alterations and custom tailoring', 1, '2025-09-04 15:42:05'),
(75, 16, 'Gift Wrapping', 'Professional gift wrapping services', 1, '2025-09-04 15:42:05');

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-tools',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `name`, `description`, `icon`, `is_active`, `created_at`) VALUES
(1, 'Home Repairs', 'General home maintenance and repairs', 'fas fa-tools', 1, '2025-08-28 21:40:10'),
(2, 'Cleaning', 'House and office cleaning services', 'fas fa-broom', 1, '2025-08-28 21:40:10'),
(3, 'Plumbing', 'Plumbing installation and repairs', 'fas fa-wrench', 1, '2025-08-28 21:40:10'),
(4, 'Electrical', 'Electrical work and installations', 'fas fa-bolt', 1, '2025-08-28 21:40:10'),
(5, 'Gardening', 'Landscaping and garden maintenance', 'fas fa-leaf', 1, '2025-08-28 21:40:10'),
(6, 'Painting', 'Interior and exterior painting', 'fas fa-paint-brush', 1, '2025-08-28 21:40:10'),
(7, 'Appliance Repair', 'Repair and maintenance of household appliances', 'fas fa-cogs', 1, '2025-09-04 15:41:54'),
(8, 'IT & Tech Support', 'Technology support and computer services', 'fas fa-laptop-code', 1, '2025-09-04 15:41:54'),
(9, 'Moving & Delivery', 'Moving, transport and delivery services', 'fas fa-truck-moving', 1, '2025-09-04 15:41:54'),
(10, 'Pet Care', 'Pet care and animal services', 'fas fa-dog', 1, '2025-09-04 15:41:54'),
(11, 'Automotive', 'Vehicle maintenance and repair services', 'fas fa-car', 1, '2025-09-04 15:41:54'),
(12, 'Security', 'Security and safety services', 'fas fa-shield-alt', 1, '2025-09-04 15:41:54'),
(13, 'Tutoring', 'Educational and tutoring services', 'fas fa-graduation-cap', 1, '2025-09-04 15:41:54'),
(14, 'Event Services', 'Event planning and party services', 'fas fa-calendar-alt', 1, '2025-09-04 15:41:54'),
(15, 'Health & Wellness', 'Health, fitness and wellness services', 'fas fa-heartbeat', 1, '2025-09-04 15:41:54'),
(16, 'Miscellaneous', 'Various other professional services', 'fas fa-ellipsis-h', 1, '2025-09-04 15:41:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer','worker') DEFAULT 'customer',
  `status` enum('active','disabled') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `telephone`, `address`, `password`, `role`, `status`, `profile_image`, `average_rating`, `total_reviews`, `created_at`, `updated_at`) VALUES
(2, 'Nazir Mohamed', 'nazir@gmail.com', '0771234567', 'Kurunegala', '$2y$10$PdOuCYF5FZEcGXEeYvlUpegPYUvO2UgJcQX4oJOwS5CjSJp/a7PZK', 'customer', 'active', NULL, 0.00, 0, '2025-08-28 21:54:21', '2025-09-04 04:03:16'),
(3, 'System Admin', 'admin@workconnect.com', '+94771234568', 'Kurunegala', '$2y$10$c5rD06iFom.sYYeCA6ihhOs9L98Zj.z4QURBHhtxSAEu8YbUNEQq2', 'admin', 'active', NULL, 0.00, 0, '2025-08-28 22:02:27', '2025-09-06 00:21:43'),
(4, 'Menad Bandara', 'menad@gmail.com', '+94771234568', 'Kugala', '$2y$10$Vs6SpTGO00c6d05Z/ikopeC.ELZmr.CTW2B72ihOh.jqena4892oS', 'worker', 'active', NULL, 0.00, 0, '2025-09-04 17:11:18', '2025-09-04 17:11:18'),
(5, 'John Doe', 'JohnDoe@gmail.com', '+94771234568', 'Colombo', '$2y$10$3cYYRzxxzdkHq888u050R.qCVNkI41z44ThKLl4FvoyGF747tr4gy', 'worker', 'active', NULL, 0.00, 0, '2025-09-06 17:11:07', '2025-09-06 22:03:31'),
(6, 'John Doe', 'JohnDoe22@gmail.com', '+94771234568', 'Colombo', '$2y$10$qNoGq4VJ1UxDZOWxxg8N5.rf6A0zwhEzeXwU7ddfNEu0TQa9lMsFq', 'worker', 'active', NULL, 0.00, 0, '2025-09-06 17:24:53', '2025-09-06 17:24:53'),
(7, 'John Doe', 'JohjnDoe22@gmail.com', '+94771234568', 'Eren Yeager', '$2y$10$nEOwwc1rZVjtLJgHbo7pj.AApQOH9v8CTi3.mnxunvG1IOnsQbjcS', 'customer', 'active', NULL, 0.00, 0, '2025-09-06 17:25:36', '2025-09-06 17:25:36');

-- --------------------------------------------------------

--
-- Table structure for table `worker_matching_scores`
--

CREATE TABLE `worker_matching_scores` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `total_score` decimal(5,2) DEFAULT 0.00,
  `skill_score` decimal(5,2) DEFAULT 0.00,
  `location_score` decimal(5,2) DEFAULT 0.00,
  `budget_score` decimal(5,2) DEFAULT 0.00,
  `experience_score` decimal(5,2) DEFAULT 0.00,
  `rating_score` decimal(5,2) DEFAULT 0.00,
  `availability_score` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worker_profiles`
--

CREATE TABLE `worker_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `hourly_rate_min` decimal(10,2) DEFAULT NULL,
  `hourly_rate_max` decimal(10,2) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `total_jobs` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `worker_profiles`
--

INSERT INTO `worker_profiles` (`id`, `user_id`, `bio`, `experience_years`, `hourly_rate_min`, `hourly_rate_max`, `is_available`, `average_rating`, `total_jobs`, `total_reviews`, `created_at`) VALUES
(1, 3, NULL, 1, 1500.00, NULL, 1, 4.00, 2, 0, '2025-08-28 22:02:27'),
(2, 4, NULL, 1, 1500.00, NULL, 1, 0.00, 1, 0, '2025-09-04 17:11:18'),
(3, 6, NULL, 1, 1500.00, NULL, 1, 0.00, 0, 0, '2025-09-06 17:24:53'),
(4, 5, NULL, 0, NULL, NULL, 1, 0.00, 0, 0, '2025-09-06 22:03:39');

-- --------------------------------------------------------

--
-- Table structure for table `worker_skills`
--

CREATE TABLE `worker_skills` (
  `id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `skill_level` enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer_matching_preferences`
--
ALTER TABLE `customer_matching_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`job_id`,`worker_id`),
  ADD KEY `worker_id` (`worker_id`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `assigned_worker_id` (`assigned_worker_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`job_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `reviewee_id` (`reviewee_id`);

--
-- Indexes for table `salary_predictions`
--
ALTER TABLE `salary_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `worker_matching_scores`
--
ALTER TABLE `worker_matching_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_worker_unique` (`job_id`,`worker_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `worker_id` (`worker_id`);

--
-- Indexes for table `worker_profiles`
--
ALTER TABLE `worker_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `worker_skills`
--
ALTER TABLE `worker_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_worker_service` (`worker_id`,`service_id`),
  ADD KEY `service_id` (`service_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer_matching_preferences`
--
ALTER TABLE `customer_matching_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `salary_predictions`
--
ALTER TABLE `salary_predictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `worker_matching_scores`
--
ALTER TABLE `worker_matching_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `worker_profiles`
--
ALTER TABLE `worker_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `worker_skills`
--
ALTER TABLE `worker_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_matching_preferences`
--
ALTER TABLE `customer_matching_preferences`
  ADD CONSTRAINT `customer_matching_preferences_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`);

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `job_postings_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `job_postings_ibfk_3` FOREIGN KEY (`assigned_worker_id`) REFERENCES `worker_profiles` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `salary_predictions`
--
ALTER TABLE `salary_predictions`
  ADD CONSTRAINT `salary_predictions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`);

--
-- Constraints for table `worker_matching_scores`
--
ALTER TABLE `worker_matching_scores`
  ADD CONSTRAINT `worker_matching_scores_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `worker_matching_scores_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `worker_profiles`
--
ALTER TABLE `worker_profiles`
  ADD CONSTRAINT `worker_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `worker_skills`
--
ALTER TABLE `worker_skills`
  ADD CONSTRAINT `worker_skills_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `worker_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `worker_skills_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
