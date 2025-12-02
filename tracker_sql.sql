-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 01, 2025 at 10:55 PM
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
  `api_secret` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `affiliate_programs`
--

INSERT INTO `affiliate_programs` (`id`, `name`, `api_key`, `api_secret`) VALUES
(1, 'oponia', '50f3cb9bc4be9e08bc6db9114f9014644c498f65c609e03575e6a7646a2b20e9', NULL),
(2, 'Yieldkit', '123', '123');

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `external_campaign_id` int(20) NOT NULL,
  `country` char(2) NOT NULL,
  `traffic_source_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaigns`
--

INSERT INTO `campaigns` (`id`, `name`, `external_campaign_id`, `country`, `traffic_source_id`) VALUES
(1, '1', 1, '1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `campaign_offers`
--

CREATE TABLE `campaign_offers` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `cap` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campaign_offers`
--

INSERT INTO `campaign_offers` (`id`, `campaign_id`, `offer_id`, `cap`) VALUES
(1, 1, 1, 100);

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
  `brower` varchar(50) NOT NULL,
  `zone_id` varchar(64) NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clicks`
--

INSERT INTO `clicks` (`id`, `click_id`, `offer_id`, `campaign_id`, `country`, `payout`, `status`, `OS`, `brower`, `zone_id`, `ip`, `cost`, `created_at`, `updated_at`) VALUES
(10, 'c692b55d59e3e36.95663579', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:21:41', '2025-11-29 20:21:41'),
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
(21, 'c692b5a27d56612.02595602', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-11-29 20:40:07', '2025-11-29 20:40:07'),
(22, 'cid692d349c2b11c9.66274592', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 06:24:28', '2025-12-01 06:24:28'),
(23, 'cid692d79b437f791.06530297', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:19:16', '2025-12-01 11:19:16'),
(24, 'cid692d7d26dd93a7.34260853', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:33:58', '2025-12-01 11:33:58'),
(25, 'cid692d7d7930c9a1.31939592', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 11:35:21', '2025-12-01 11:35:21'),
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
(36, 'cid692d9c1100ef53.81824601', 1, 1, 'UN', 0.00, NULL, 'Unknown', 'Unknown', 'default', 0x00000000000000000000000000000001, 0.00, '2025-12-01 13:45:53', '2025-12-01 13:45:53');

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
  `website_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offers`
--

INSERT INTO `offers` (`id`, `name`, `affiliate_program_id`, `affiliate_link`, `country`, `website_id`) VALUES
(1, '1', 1, 'youtube.com', '1', 1);

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
(38, 'Missing parameters', 'click_id: , payout: , status: ', NULL, '[]', 0x00000000000000000000000000000001, '2025-12-01 21:14:39');

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
(18, '', NULL, '1', '{\"cid\":\"1\"}', 0x00000000000000000000000000000001, '2025-11-29 20:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `traffic_sources`
--

CREATE TABLE `traffic_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `api_key` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `traffic_sources`
--

INSERT INTO `traffic_sources` (`id`, `name`, `api_key`) VALUES
(1, 'PropellerAds', 'ac91949401ab41b82649bd85a81434b98e8e3304f66f2411');

-- --------------------------------------------------------

--
-- Table structure for table `websites`
--

CREATE TABLE `websites` (
  `id` int(11) NOT NULL,
  `domain` varchar(64) NOT NULL,
  `country` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `websites`
--

INSERT INTO `websites` (`id`, `domain`, `country`) VALUES
(1, 'wisemindvibe.com', 'WW');

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campaign_offers`
--
ALTER TABLE `campaign_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `offer_id` (`offer_id`);

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
  ADD KEY `idx_website` (`website_id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `websites`
--
ALTER TABLE `websites`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `campaign_offers`
--
ALTER TABLE `campaign_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clicks`
--
ALTER TABLE `clicks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `offers_artlcles`
--
ALTER TABLE `offers_artlcles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `postback_logs`
--
ALTER TABLE `postback_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `redirect_logs`
--
ALTER TABLE `redirect_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

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
