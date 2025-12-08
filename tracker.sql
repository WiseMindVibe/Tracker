-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 08:11 PM
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

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `country` char(2) NOT NULL,
  `traffic_source_id` int(11) NOT NULL,
  `tester` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_external_ids`
--

CREATE TABLE `campaign_external_ids` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `external_campaign_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `log_campaign`
--

CREATE TABLE `log_campaign` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `roi` float DEFAULT NULL,
  `log_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `log_campaign`
--
ALTER TABLE `log_campaign`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_external_ids`
--
ALTER TABLE `campaign_external_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `campaign_offers`
--
ALTER TABLE `campaign_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clicks`
--
ALTER TABLE `clicks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_campaign`
--
ALTER TABLE `log_campaign`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offers_artlcles`
--
ALTER TABLE `offers_artlcles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `postback_logs`
--
ALTER TABLE `postback_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `redirect_logs`
--
ALTER TABLE `redirect_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `traffic_sources`
--
ALTER TABLE `traffic_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `websites`
--
ALTER TABLE `websites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `websites_buffers`
--
ALTER TABLE `websites_buffers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
