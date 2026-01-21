-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 21, 2026 at 04:45 PM
-- Server version: 5.7.40
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `electricity`
--

-- --------------------------------------------------------

--
-- Table structure for table `bill`
--

DROP TABLE IF EXISTS `bill`;
CREATE TABLE IF NOT EXISTS `bill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `units` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `gst` decimal(10,2) DEFAULT NULL,
  `fine` decimal(10,2) DEFAULT NULL,
  `prev_due` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `service_number` varchar(50) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `remaining_due` decimal(10,2) DEFAULT '0.00',
  `last_payment_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `number` (`number`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `bill`
--

INSERT INTO `bill` (`id`, `number`, `month`, `year`, `units`, `amount`, `gst`, `fine`, `prev_due`, `total`, `due_date`, `status`, `service_number`, `paid_amount`, `remaining_due`, `last_payment_date`) VALUES
(4, 1331, 3, 2025, '200.00', '425.00', '76.50', '0.00', '0.00', '501.50', '2025-04-15', 'partially_paid', '202503-1331', '166.76', '334.74', '2026-01-21');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
CREATE TABLE IF NOT EXISTS `customer` (
  `number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text,
  `email` varchar(100) DEFAULT NULL,
  `category` varchar(20) DEFAULT 'household',
  `reg_date` date DEFAULT NULL,
  PRIMARY KEY (`number`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`number`, `name`, `phone`, `address`, `email`, `category`, `reg_date`) VALUES
(1331, 'tapasya', '8888888888', 'sr nagar', NULL, 'household', '2026-01-21');

-- --------------------------------------------------------

--
-- Table structure for table `minimum_charges`
--

DROP TABLE IF EXISTS `minimum_charges`;
CREATE TABLE IF NOT EXISTS `minimum_charges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(20) DEFAULT NULL,
  `min_charge` decimal(10,2) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `minimum_charges`
--

INSERT INTO `minimum_charges` (`id`, `category`, `min_charge`, `effective_from`) VALUES
(1, 'household', '50.00', '2020-01-01'),
(2, 'commercial', '100.00', '2020-01-01'),
(3, 'industrial', '200.00', '2020-01-01');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) DEFAULT NULL,
  `customer_number` int(11) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `bill_id`, `customer_number`, `amount_paid`, `payment_date`, `payment_method`, `transaction_id`, `status`) VALUES
(1, 4, 1331, '166.76', '2026-01-21', 'upi', '', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `payment_history`
--

DROP TABLE IF EXISTS `payment_history`;
CREATE TABLE IF NOT EXISTS `payment_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_number` int(11) DEFAULT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `previous_due` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `remaining_due` decimal(10,2) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `payment_history`
--

INSERT INTO `payment_history` (`id`, `customer_number`, `bill_id`, `payment_id`, `previous_due`, `paid_amount`, `remaining_due`, `payment_date`, `notes`) VALUES
(1, 1331, 4, 1, '501.50', '166.76', '334.74', '2026-01-21', '');

-- --------------------------------------------------------

--
-- Table structure for table `readings`
--

DROP TABLE IF EXISTS `readings`;
CREATE TABLE IF NOT EXISTS `readings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `reading` decimal(10,2) DEFAULT NULL,
  `read_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `readings`
--

INSERT INTO `readings` (`id`, `number`, `month`, `year`, `reading`, `read_date`) VALUES
(3, 1331, 1, 2025, '240.00', '2026-01-21'),
(4, 1331, 2, 2025, '160.00', '2026-01-21'),
(5, 1331, 3, 2025, '360.00', '2026-01-21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `number` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `number` (`number`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `number`) VALUES
(1, 'admin', '$2y$10$YourHashedPasswordHere', 'admin', NULL),
(2, 'tapasya', 'tap88', 'customer', 1331),
(5, 'worker', 'worker123', 'worker', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
