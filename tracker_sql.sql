-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 06, 2025 at 08:46 PM
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
-- Database: `tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_programs`
--

CREATE TABLE `affiliate_programs` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `affiliate_programs`
--

INSERT INTO `affiliate_programs` (`id`, `name`, `api_key`, `api_secret`, `created_at`, `updated_at`) VALUES
(1, 'oponia', '50f3cb9bc4be9e08bc6db9114f9014644c498f65c609e03575e6a7646a2b20e9', NULL, '2025-12-05 11:19:23', '2025-12-05 11:19:23'),
(2, 'Yieldkit', '123', '123', '2025-12-05 11:19:23', '2025-12-05 11:19:23');

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `country` char(2) NOT NULL,
  `traffic_source_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaigns`
--

INSERT INTO `campaigns` (`id`, `name`, `country`, `traffic_source_id`, `created_at`, `updated_at`) VALUES
(1, '1t', 'AF', 1, '2025-12-05 11:17:52', '2025-12-05 11:28:51'),
(3, '12315', 'AF', 1, '2025-12-05 11:17:52', '2025-12-05 11:17:52'),
(5, 'Google Campaign', 'AF', 1, '2025-12-06 17:31:07', '2025-12-06 17:31:15'),
(6, '12341', 'AM', 1, '2025-12-06 18:33:16', '2025-12-06 18:33:16');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_external_ids`
--

CREATE TABLE `campaign_external_ids` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `external_campaign_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaign_external_ids`
--

INSERT INTO `campaign_external_ids` (`id`, `campaign_id`, `external_campaign_id`) VALUES
(1, 6, '123123'),
(2, 6, '15123');

-- --------------------------------------------------------

--
-- Table structure for table `campaign_offers`
--

CREATE TABLE `campaign_offers` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `current_views` int(11) DEFAULT NULL,
  `cap` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaign_offers`
--

INSERT INTO `campaign_offers` (`id`, `campaign_id`, `offer_id`, `current_views`, `cap`, `created_at`, `updated_at`) VALUES
(11, 3, 1, 6, 105, '2025-12-06 13:20:39', '2025-12-06 17:58:32'),
(14, 1, 1, 4, 5, '2025-12-06 17:21:04', '2025-12-06 17:25:51'),
(18, 5, 4, 1, 10, '2025-12-06 17:34:51', '2025-12-06 18:24:27'),
(21, 5, 5, 4, 40, '2025-12-06 18:17:58', '2025-12-06 18:24:36');

-- --------------------------------------------------------

--
-- Table structure for table `clicks`
--

CREATE TABLE `clicks` (
  `id` bigint(20) NOT NULL,
  `click_id` varchar(64) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `country` char(2) NOT NULL,
  `payout` decimal(10,2) NOT NULL,
  `status` enum('Open','Confirmed','Paid','Rejected') DEFAULT NULL,
  `OS` varchar(50) NOT NULL,
  `browser` varchar(50) NOT NULL,
  `zone_id` varchar(64) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clicks`
--

INSERT INTO `clicks` (`id`, `click_id`, `offer_id`, `campaign_id`, `country`, `payout`, `status`, `OS`, `browser`, `zone_id`, `ip`, `cost`, `created_at`, `updated_at`) VALUES
(10, 'c692b55d59e3e36.95663579', 2, 1, 'CA', 10.00, NULL, 'IOS', 'Safari', 'default', 0x00000000000000000000000000000001, 2.24, '2025-11-29 20:21:41', '2025-12-06 19:40:20'),
(11, 'c692b5616a9f930.73193426', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:22:46', '2025-11-29 20:22:46'),
(12, 'c692b5674958d78.60040482', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:24:20', '2025-11-29 20:24:20'),
(13, 'c692b56973d7f27.06268434', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:24:55', '2025-11-29 20:24:55'),
(14, 'c692b5779f14681.13304546', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:28:41', '2025-11-29 20:28:41'),
(15, 'c692b57c7da9e75.74089244', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:29:59', '2025-11-29 20:29:59'),
(16, 'c692b58b4cbd478.13824385', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:33:56', '2025-11-29 20:33:56'),
(17, 'c692b591ddd7db8.65867660', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:35:41', '2025-11-29 20:35:41'),
(18, 'c692b59342711b3.09560577', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:36:04', '2025-11-29 20:36:04'),
(19, 'c692b59614b85b2.35577266', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:36:49', '2025-11-29 20:36:49'),
(20, 'c692b5a1180de36.67978458', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:39:45', '2025-11-29 20:39:45'),
(21, 'c692b5a27d56612.02595602', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-10-01 19:40:07', '2025-12-04 05:32:23'),
(22, 'cid692d349c2b11c9.66274592', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 06:24:28', '2025-12-01 06:24:28'),
(23, 'cid692d79b437f791.06530297', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:19:16', '2025-12-01 11:19:16'),
(24, 'cid692d7d26dd93a7.34260853', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:33:58', '2025-12-01 11:33:58'),
(25, 'cid692d7d7930c9a1.31939592', 1, 1, 'GB', 1.00, 'Open', 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 5.00, '2025-12-01 11:35:21', '2025-12-04 05:30:38'),
(26, 'cid692d7e51575cd6.72772441', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:38:57', '2025-12-01 11:38:57'),
(27, 'cid692d7f2e8f8904.31222034', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:42:38', '2025-12-01 11:42:38'),
(28, 'cid692d7fc8e7ca27.68154586', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:45:12', '2025-12-01 11:45:12'),
(29, 'cid692d8004721821.19574595', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:46:12', '2025-12-01 11:46:12'),
(30, 'cid692d8072982377.53844367', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:48:02', '2025-12-01 11:48:02'),
(31, 'cid692d81bee9ada6.08248631', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:53:34', '2025-12-01 11:53:34'),
(32, 'cid692d9b09683df5.41875212', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 13:41:29', '2025-12-01 13:41:29'),
(33, 'cid692d9b3236a708.65021148', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 13:42:10', '2025-12-01 13:42:10'),
(34, 'cid692d9ba11b17f7.03957623', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 13:44:01', '2025-12-01 13:44:01'),
(35, 'cid692d9bb5cf7b53.62103374', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 13:44:21', '2025-12-01 13:44:21'),
(36, 'cid692d9c1100ef53.81824601', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 13:45:53', '2025-12-01 13:45:53'),
(37, 'cid692f3ba6c956a1.47828470', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-02 19:19:02', '2025-12-02 19:19:02'),
(38, 'cid692f4faea80f62.99584247', 1, 1, '{C', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-02 20:44:30', '2025-12-02 20:44:30'),
(39, 'cid692f521192e6f6.00737952', 1, 1, 'US', 1.00, NULL, 'IOS', 'Chorme', '123151', 0x00000000000000000000000000000001, 0.03, '2025-12-02 20:54:41', '2025-12-04 05:29:30'),
(40, 'cid692f522e44f271.83288407', 1, 1, '{C', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-02 20:55:10', '2025-12-02 20:55:10'),
(41, 'cid6931f586aab685.32006168', 1, 1, '{C', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-04 20:56:38', '2025-12-04 20:56:38'),
(42, 'cid6931f5d89dabc2.97621604', 1, 1, '{C', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-04 20:58:00', '2025-12-04 20:58:00'),
(43, 'cid6931f5d9aa0f60.31534018', 1, 1, '{C', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-04 20:58:01', '2025-12-04 20:58:01'),
(44, 'cid6931f5f6c9d684.27154327', 2, 1, 'US', 1.00, 'Open', 'IOS', 'Chorme', 'Unknown', 0x00000000000000000000000000000001, 0.10, '2025-12-04 20:58:30', '2025-12-06 19:40:20'),
(45, 'cid693209659b2b55.24454119', 1, 3, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-04 22:21:25', '2025-12-04 22:21:25'),
(46, 'cid69329078a75850.95386227', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 07:57:44', '2025-12-05 07:57:44'),
(47, 'cid69329125ab6384.77300873', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 08:00:37', '2025-12-05 08:00:37'),
(48, 'cid69329496c39c84.69895365', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 08:15:18', '2025-12-05 08:15:18'),
(49, 'cid693294a3b27d76.93491190', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 08:15:31', '2025-12-05 08:15:31'),
(50, 'cid6932c7958d9b52.53019960', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 11:52:53', '2025-12-05 11:52:53'),
(51, 'cid6932cd5d147633.44488816', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:17:33', '2025-12-05 12:17:33'),
(52, 'cid6932d19bbd16d3.87086115', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:35:39', '2025-12-05 12:35:39'),
(53, 'cid6932d463cc6e38.32541154', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:47:31', '2025-12-05 12:47:31'),
(54, 'cid6932d53f44bbe1.58616269', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:51:11', '2025-12-05 12:51:11'),
(55, 'cid6932d55e6f0750.41846220', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:51:42', '2025-12-05 12:51:42'),
(56, 'cid6932d5e7a713c3.03274130', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:53:59', '2025-12-05 12:53:59'),
(57, 'cid6932d61109a221.25659075', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 12:54:41', '2025-12-05 12:54:41'),
(58, 'click_id6932e051becd14.07436003', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:38:25', '2025-12-05 13:38:25'),
(59, 'click_id6932e0967c3480.16910109', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:39:34', '2025-12-05 13:39:34'),
(60, 'click_id6932e0c6452223.89908512', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:40:22', '2025-12-05 13:40:22'),
(61, 'click_id6932e0d1efc8e6.49872930', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:40:33', '2025-12-05 13:40:33'),
(62, 'click_id6932e0eb8a5cb7.95446104', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:40:59', '2025-12-05 13:40:59'),
(63, 'cid6932e49b7d9326.04393499', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:56:43', '2025-12-05 13:56:43'),
(64, 'cid6932e4c0d445d1.80867293', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:57:20', '2025-12-05 13:57:20'),
(65, 'cid6932e4dc96c8e4.52555410', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:57:48', '2025-12-05 13:57:48'),
(66, 'cid6932e539d961f2.43505627', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 13:59:21', '2025-12-05 13:59:21'),
(67, 'cid6932e7b14193d9.45656538', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:09:53', '2025-12-05 14:09:53'),
(68, 'cid6932e7d1386b77.05484955', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:10:25', '2025-12-05 14:10:25'),
(69, 'cid6932e7d701f523.78499924', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:10:31', '2025-12-05 14:10:31'),
(70, 'cid6932e7e54927d9.25875208', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:10:45', '2025-12-05 14:10:45'),
(71, 'cid6932e911b65170.05139400', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:15:45', '2025-12-05 14:15:45'),
(72, 'cid6932e9c3846cd5.75393237', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:18:43', '2025-12-05 14:18:43'),
(73, 'cid6932ece77bbab6.06540602', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:32:07', '2025-12-05 14:32:07'),
(74, 'cid6932ecf23bac22.67901814', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:32:18', '2025-12-05 14:32:18'),
(75, 'cid6932edd966ee31.03788750', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:36:09', '2025-12-05 14:36:09'),
(76, 'cid6932ede2461932.59613388', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:36:18', '2025-12-05 14:36:18'),
(77, 'cid6932edfb0ce373.17847791', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-05 14:36:43', '2025-12-05 14:36:43'),
(78, 'cid6933d117339727.12793212', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 06:45:43', '2025-12-06 06:45:43'),
(79, 'cid6933d12b80e0a2.15180875', 1, 1, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 06:46:03', '2025-12-06 06:46:03'),
(80, 'cid6934281d0df471.83403715', 1, 1, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 12:57:01', '2025-12-06 12:57:01'),
(81, 'cid693465b7dab3d9.90974280', 3, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:19:51', '2025-12-06 17:19:51'),
(82, 'cid693465cbd60104.90004543', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:20:11', '2025-12-06 17:20:11'),
(83, 'cid693465d4bdf115.77126729', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:20:20', '2025-12-06 17:20:20'),
(84, 'cid693465f20611b0.89351907', 1, 1, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:20:50', '2025-12-06 17:20:50'),
(85, 'cid693466563a6062.61117356', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:22:30', '2025-12-06 17:22:30'),
(86, 'cid69346698152826.92876028', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:23:36', '2025-12-06 17:23:36'),
(87, 'cid693466a3d07766.57371225', 3, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:23:47', '2025-12-06 17:23:47'),
(88, 'cid693466b2940842.15509039', 2, 3, 'US', 0.33, NULL, 'IOS', 'Safari', 'Unknown', 0x00000000000000000000000000000001, 0.21, '2025-12-06 17:24:02', '2025-12-06 19:40:20'),
(89, 'cid693467073363a2.03082064', 1, 1, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:25:27', '2025-12-06 17:25:27'),
(90, 'cid6934671189c394.00830501', 1, 1, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:25:37', '2025-12-06 17:25:37'),
(91, 'cid6934672b45c737.03322690', 2, 3, 'US', 1.50, NULL, 'Android', 'Brave', 'Unknown', 0x00000000000000000000000000000001, 0.50, '2025-12-06 17:26:03', '2025-12-06 19:40:20'),
(92, 'cid6934674b460955.02450843', 3, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:26:35', '2025-12-06 17:26:35'),
(93, 'cid693467968ec3a6.79865599', 3, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:27:50', '2025-12-06 17:27:50'),
(94, 'cid693467a9f16325.70074023', 3, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:28:09', '2025-12-06 17:28:09'),
(95, 'cid693467bca957e5.67195173', 1, 3, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:28:28', '2025-12-06 17:28:28'),
(96, 'cid69346882600b68.23819573', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:31:46', '2025-12-06 17:31:46'),
(97, 'cid693468920846f5.60192668', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:32:02', '2025-12-06 17:32:02'),
(98, 'cid69346893c9d008.36290826', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:32:03', '2025-12-06 17:32:03'),
(99, 'cid6934689540f7d2.93107214', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:32:05', '2025-12-06 17:32:05'),
(100, 'cid693468e1ef3a48.34324953', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:33:21', '2025-12-06 17:33:21'),
(101, 'cid693468e5888be6.12196253', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:33:25', '2025-12-06 17:33:25'),
(102, 'cid693468e9394328.74396663', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:33:29', '2025-12-06 17:33:29'),
(103, 'cid693468ed0035f3.95810741', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:33:33', '2025-12-06 17:33:33'),
(104, 'cid69346967d6ca94.01306207', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:35:35', '2025-12-06 17:35:35'),
(105, 'cid69346972068d90.80394859', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:35:46', '2025-12-06 17:35:46'),
(106, 'cid69346994220115.29490660', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:36:20', '2025-12-06 17:36:20'),
(107, 'cid693469998964b9.58780172', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:36:25', '2025-12-06 17:36:25'),
(108, 'cid6934699c13aa90.45383094', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:36:28', '2025-12-06 17:36:28'),
(109, 'cid69346a32982ad2.89572146', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:38:58', '2025-12-06 17:38:58'),
(110, 'cid69346a34e2ab40.92738367', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:39:00', '2025-12-06 17:39:00'),
(111, 'cid69346a44649c26.31126016', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:39:16', '2025-12-06 17:39:16'),
(112, 'cid69346a45b55252.84034759', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:39:17', '2025-12-06 17:39:17'),
(113, 'cid69346aae8b1287.47000609', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:41:02', '2025-12-06 17:41:02'),
(114, 'cid69346aafd1b296.17795416', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:41:03', '2025-12-06 17:41:03'),
(115, 'cid69346bf18656f0.54638006', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:46:25', '2025-12-06 17:46:25'),
(116, 'cid69346bf31329f2.68221313', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:46:27', '2025-12-06 17:46:27'),
(117, 'cid69346c16676264.63925956', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:47:02', '2025-12-06 17:47:02'),
(118, 'cid69346cad227fd3.13192329', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:49:33', '2025-12-06 17:49:33'),
(119, 'cid69346cb1698b20.02628931', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 17:49:37', '2025-12-06 17:49:37'),
(120, 'cid69347367388742.24292642', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:18:15', '2025-12-06 18:18:15'),
(121, 'cid693473692dc4f3.56858737', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:18:17', '2025-12-06 18:18:17'),
(122, 'cid6934744d25ab55.35772235', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:22:05', '2025-12-06 18:22:05'),
(123, 'cid6934749ee0aab4.19539514', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:23:26', '2025-12-06 18:23:26'),
(124, 'cid693474a5a13942.04169652', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:23:33', '2025-12-06 18:23:33'),
(125, 'cid693474a8a01693.02696999', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:23:36', '2025-12-06 18:23:36'),
(126, 'cid693474aa7feb01.07810050', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:23:38', '2025-12-06 18:23:38'),
(127, 'cid693474bdb86813.31322537', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:23:57', '2025-12-06 18:23:57'),
(128, 'cid693474bfbc39c6.37310262', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:23:59', '2025-12-06 18:23:59'),
(129, 'cid693474c24ef439.70320344', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:24:02', '2025-12-06 18:24:02'),
(130, 'cid693474db3a0dd2.22015697', 4, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:24:27', '2025-12-06 18:24:27'),
(131, 'cid693474ddaffe64.84419308', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:24:29', '2025-12-06 18:24:29'),
(132, 'cid693474dfae96f3.71208679', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:24:31', '2025-12-06 18:24:31'),
(133, 'cid693474e1918334.44648597', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:24:33', '2025-12-06 18:24:33'),
(134, 'cid693474e44fb102.30445111', 5, 5, '{c', 0.00, NULL, '{os}', '{browser}', 'Unknown', 0x00000000000000000000000000000001, 0.00, '2025-12-06 18:24:36', '2025-12-06 18:24:36');

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `affiliate_program_id` int(11) NOT NULL,
  `affiliate_link` varchar(2083) NOT NULL,
  `country` char(2) NOT NULL,
  `website_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offers`
--

INSERT INTO `offers` (`id`, `name`, `affiliate_program_id`, `affiliate_link`, `country`, `website_id`, `created_at`, `updated_at`) VALUES
(1, '1', 1, 'youtube.com', '1', 1, '2025-12-05 11:22:19', '2025-12-05 11:22:19'),
(2, 'example YK', 2, 'https://example.com', 'AF', 1, '2025-12-05 11:22:19', '2025-12-06 12:56:04'),
(3, '123', 2, 'https://wisemindvibe.com', 'US', 1, '2025-12-05 11:22:19', '2025-12-05 11:22:19'),
(4, 'Google OP', 1, 'https://google.com', 'US', 1, '2025-12-06 17:30:40', '2025-12-06 17:30:40'),
(5, 'Google YK', 2, 'https://google.com', 'GB', 1, '2025-12-06 17:30:52', '2025-12-06 17:30:52');

-- --------------------------------------------------------

--
-- Table structure for table `offers_artlcles`
--

CREATE TABLE `offers_artlcles` (
  `id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `article_url` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `postback_logs`
--

CREATE TABLE `postback_logs` (
  `id` bigint(20) NOT NULL,
  `status` enum('Missing parameters','Missing click_id','Invalid status','Invalid payout','Click not found') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `click_id` varchar(64) DEFAULT NULL,
  `raw_query` varchar(100) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `postback_logs`
--

INSERT INTO `postback_logs` (`id`, `status`, `reason`, `click_id`, `raw_query`, `ip`, `created_at`) VALUES
(38, 'Missing parameters', 'click_id: , payout: , status: ', NULL, '[]', 0x00000000000000000000000000000001, '2025-12-01 21:14:39'),
(39, 'Missing parameters', 'click_id: e015149eec1b0b506ad9c053d9be866e, payout: , status: open', 'e015149eec1b0b506ad9c053d9be866e', '{\"click_id\":\"e015149eec1b0b506ad9c053d9be866e\",\"status\":\"opEn\"}', 0x00000000000000000000000000000001, '2025-12-05 07:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `redirect_logs`
--

CREATE TABLE `redirect_logs` (
  `id` bigint(20) NOT NULL,
  `status` enum('Missing campaign_id','Campaign not found') DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `campaign_id` varchar(64) NOT NULL,
  `raw_query` varchar(100) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `redirect_logs`
--

INSERT INTO `redirect_logs` (`id`, `status`, `reason`, `campaign_id`, `raw_query`, `ip`, `created_at`) VALUES
(8, 'Campaign not found', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:06:12'),
(9, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:06:41'),
(10, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:06:58'),
(11, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:06:58'),
(12, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:07:08'),
(13, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:07:08'),
(14, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:13:06'),
(15, 'Campaign not found', NULL, '2', '{\"cid\":\"2\"}', 0x00000000000000000000000000000001, '2025-11-29 20:22:24'),
(16, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:24:36'),
(17, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:24:39'),
(18, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:39:32'),
(19, '', NULL, '1', '{\"cid\":\"1\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-04 22:21:53'),
(20, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-05 08:02:44'),
(21, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-05 08:15:38'),
(22, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-05 08:16:30'),
(23, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-05 08:17:24'),
(24, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-05 08:17:29'),
(25, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x7f000001, '2025-12-05 10:46:15'),
(26, '', NULL, '3', '{\"cid\":\"3\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-05 13:39:57'),
(27, '', NULL, '1', '{\"cid\":\"1\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-06 06:45:50'),
(28, '', NULL, '5', '{\"cid\":\"5\",\"clickid\":\"${SUBID}\",\"campaignid\":\"{campaignid}\",\"country\":\"{country}\",\"os\":\"{os}\",\"brows', 0x00000000000000000000000000000001, '2025-12-06 17:31:20');

-- --------------------------------------------------------

--
-- Table structure for table `traffic_sources`
--

CREATE TABLE `traffic_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `traffic_sources`
--

INSERT INTO `traffic_sources` (`id`, `name`, `api_key`, `created_at`, `updated_at`) VALUES
(1, 'PropellerAds', 'ac91949401ab41b82649bd85a81434b98e8e3304f66f2411', '2025-12-05 11:23:13', '2025-12-05 11:52:46');

-- --------------------------------------------------------

--
-- Table structure for table `websites`
--

CREATE TABLE `websites` (
  `id` int(11) NOT NULL,
  `domain` varchar(64) NOT NULL,
  `country` char(2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `websites`
--

INSERT INTO `websites` (`id`, `domain`, `country`, `created_at`, `updated_at`) VALUES
(1, 'wisemindvibe.com', 'WW', '2025-12-05 11:23:13', '2025-12-05 11:23:13');

-- --------------------------------------------------------

--
-- Table structure for table `websites_buffers`
--

CREATE TABLE `websites_buffers` (
  `id` int(11) NOT NULL,
  `website_id` int(11) NOT NULL,
  `type` varchar(25) NOT NULL,
  `buffer_url` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `websites_buffers`
--

INSERT INTO `websites_buffers` (`id`, `website_id`, `type`, `buffer_url`) VALUES
(1, 1, 'x.com', 't.co/hRM2vNRhdZ');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `affiliate_programs`
--
ALTER TABLE `affiliate_programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `campaign_external_ids`
--
ALTER TABLE `campaign_external_ids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indexes for table `campaign_offers`
--
ALTER TABLE `campaign_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `offer_id` (`offer_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `clicks`
--
ALTER TABLE `clicks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offer_id` (`offer_id`),
  ADD KEY `idx_campaign_id` (`campaign_id`),
  ADD KEY `idx_click_id` (`click_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_created_time` (`created_at`),
  ADD KEY `idx_updated_time` (`updated_at`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_affiliate_program` (`affiliate_program_id`),
  ADD KEY `idx_website` (`website_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `offers_artlcles`
--
ALTER TABLE `offers_artlcles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `postback_logs`
--
ALTER TABLE `postback_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_click_id` (`click_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `redirect_logs`
--
ALTER TABLE `redirect_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_campaign_id` (`campaign_id`) USING BTREE;

--
-- Indexes for table `traffic_sources`
--
ALTER TABLE `traffic_sources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `websites`
--
ALTER TABLE `websites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `websites_buffers`
--
ALTER TABLE `websites_buffers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_website2` (`website_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `affiliate_programs`
--
ALTER TABLE `affiliate_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `campaign_external_ids`
--
ALTER TABLE `campaign_external_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `campaign_offers`
--
ALTER TABLE `campaign_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `clicks`
--
ALTER TABLE `clicks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `offers_artlcles`
--
ALTER TABLE `offers_artlcles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `postback_logs`
--
ALTER TABLE `postback_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `redirect_logs`
--
ALTER TABLE `redirect_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `traffic_sources`
--
ALTER TABLE `traffic_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `websites`
--
ALTER TABLE `websites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `websites_buffers`
--
ALTER TABLE `websites_buffers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `campaign_external_ids`
--
ALTER TABLE `campaign_external_ids`
  ADD CONSTRAINT `campaign_external_ids_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campaign_offers`
--
ALTER TABLE `campaign_offers`
  ADD CONSTRAINT `campaign_offers_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `campaign_offers_ibfk_2` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`);

--
-- Constraints for table `clicks`
--
ALTER TABLE `clicks`
  ADD CONSTRAINT `fk_click_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `fk_click_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`);

--
-- Constraints for table `offers`
--
ALTER TABLE `offers`
  ADD CONSTRAINT `fk_affiliate_program` FOREIGN KEY (`affiliate_program_id`) REFERENCES `affiliate_programs` (`id`),
  ADD CONSTRAINT `fk_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`);

--
-- Constraints for table `websites_buffers`
--
ALTER TABLE `websites_buffers`
  ADD CONSTRAINT `fk_website2` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
