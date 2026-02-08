-- phpMyAdmin SQL Dump
-- Complete Bus Ticket Booking System with Booking History
-- Version 4.0 - Full Featured with Student/Staff Categories
-- Date: February 2026

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bus_ticket`
--
CREATE DATABASE IF NOT EXISTS `bus_ticket` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `bus_ticket`;

-- ========================================
-- DROP EXISTING TABLES (CLEAN START)
-- ========================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `booking_history`;
DROP TABLE IF EXISTS `driver_earnings`;
DROP TABLE IF EXISTS `booking_holds`;
DROP TABLE IF EXISTS `seat_templates`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `earnings`;
DROP TABLE IF EXISTS `notices`;
DROP TABLE IF EXISTS `buses`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- TABLE: users
-- ========================================

CREATE TABLE `users` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `uname` varchar(20) NOT NULL,
  `name` varchar(25) NOT NULL,
  `email` varchar(40) NOT NULL,
  `password` varchar(25) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `utype` enum('Admin','Owner','Passenger','Student','Staff') NOT NULL COMMENT 'Student=10% discount, Staff=5% discount',
  `address` varchar(120) NOT NULL,
  `mobile` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uname` (`uname`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `uname`, `name`, `email`, `password`, `gender`, `utype`, `address`, `mobile`) VALUES
(1, 'admin', 'Admin', 'admin@bus.com', 'admin', 'Male', 'Admin', 'Admin Office', '0000000000'),
(2, 'deba', 'Debashish Sarker', 'dsarker333@gmail.com', '123456', 'Male', 'Passenger', 'Dhaka, Bangladesh', '1000000000'),
(3, 'oni', 'Onimesh', 'osarker@gmail.com', '123456', 'Male', 'Owner', 'Dhaka, Bangladesh', '0000000000'),
(4, 'hori', 'Habibur Hori', 'habiburaiub@gmail.com', '123456', 'Male', 'Owner', 'Kuril, Dhaka', '1700000000'),
(5, 'rubel', 'Rubel', 'rubelmhr@gmail.com', '123456', 'Male', 'Passenger', 'Kuril', '1722222222'),
(6, 'student1', 'John Student', 'student@example.com', '123456', 'Male', 'Student', 'Student Hostel, Dhaka', '1711111111'),
(7, 'staff1', 'Jane Staff', 'staff@example.com', '123456', 'Female', 'Staff', 'Staff Quarter, Dhaka', '1722222222'),
(8, 'student2', 'Alice Student', 'alice@student.com', '123456', 'Female', 'Student', 'Campus Dhaka', '1733333333'),
(9, 'staff2', 'Bob Staff', 'bob@staff.com', '123456', 'Male', 'Staff', 'Staff Area, Dhaka', '1744444444');

-- ========================================
-- TABLE: locations
-- ========================================

CREATE TABLE `locations` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`) VALUES
(1, 'Dhaka'),
(2, 'Naogaon'),
(3, 'Chittagong'),
(4, 'Rajshahi'),
(5, 'Sylhet'),
(6, 'Khulna'),
(7, 'Barisal'),
(8, 'Rangpur'),
(9, 'Mymensingh'),
(10, 'Comilla');

-- ========================================
-- TABLE: buses (WITH SEAT CATEGORIES)
-- ========================================

CREATE TABLE `buses` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `bname` varchar(25) NOT NULL,
  `bus_no` varchar(25) NOT NULL,
  `owner_id` int(5) NOT NULL,
  `from_loc` varchar(20) NOT NULL,
  `from_time` varchar(8) NOT NULL,
  `to_loc` varchar(20) NOT NULL,
  `to_time` varchar(8) NOT NULL,
  `fare` int(5) NOT NULL,
  `total_seats` int(3) NOT NULL DEFAULT 40 COMMENT 'Total number of seats in the bus',
  `seat_layout` varchar(50) DEFAULT '10x4' COMMENT 'Seat layout format: ROWSxCOLUMNS (e.g., 10x4 = 10 rows, 4 columns)',
  `student_seats` varchar(255) DEFAULT NULL COMMENT 'Comma-separated list of student-only seats (50%)',
  `staff_seats` varchar(255) DEFAULT NULL COMMENT 'Comma-separated list of staff-only seats (25%)',
  `general_seats` varchar(255) DEFAULT NULL COMMENT 'Comma-separated list of general seats (25%)',
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_usr_bus` (`owner_id`),
  CONSTRAINT `fk_usr_bus` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`id`, `bname`, `bus_no`, `owner_id`, `from_loc`, `from_time`, `to_loc`, `to_time`, `fare`, `total_seats`, `seat_layout`, `student_seats`, `staff_seats`, `general_seats`, `approved`) VALUES
(1, 'Hanif Enterprise', 'DH-12456', 3, 'Naogaon', '10:30 PM', 'Dhaka', '05:00 AM', 500, 40, '10x4', 'A1,A2,A3,A4,B1,B2,B3,B4,C1,C2,C3,C4,D1,D2,D3,D4,E1,E2,E3,E4', 'F1,F2,F3,F4,G1,G2,G3,G4,H1,H2', 'H3,H4,I1,I2,I3,I4,J1,J2,J3,J4', 1),
(2, 'Hanif Enterprise', 'DH-12457', 3, 'Dhaka', '09:45 PM', 'Naogaon', '04:15 AM', 500, 40, '10x4', 'A1,A2,A3,A4,B1,B2,B3,B4,C1,C2,C3,C4,D1,D2,D3,D4,E1,E2,E3,E4', 'F1,F2,F3,F4,G1,G2,G3,G4,H1,H2', 'H3,H4,I1,I2,I3,I4,J1,J2,J3,J4', 1),
(3, 'Green Line Paribahan', 'DH-78901', 3, 'Dhaka', '08:00 AM', 'Chittagong', '02:00 PM', 700, 50, '13x4', 'A1,A2,A3,A4,B1,B2,B3,B4,C1,C2,C3,C4,D1,D2,D3,D4,E1,E2,E3,E4,F1,F2,F3,F4,G1,G2', 'G3,G4,H1,H2,H3,H4,I1,I2,I3,I4,J1,J2,J3', 'J4,K1,K2,K3,K4,L1,L2,L3,L4,M1,M2,M3,M4', 1),
(4, 'Shyamoli Paribahan', 'DH-55555', 4, 'Dhaka', '11:00 PM', 'Sylhet', '05:30 AM', 600, 30, '8x4', 'A1,A2,A3,A4,B1,B2,B3,B4,C1,C2,C3,C4,D1,D2,D3', 'D4,E1,E2,E3,E4,F1,F2,F3', 'F4,G1,G2,G3,G4,H1,H2,H3,H4', 1),
(5, 'Ena Transport', 'DH-66666', 3, 'Dhaka', '07:00 AM', 'Rajshahi', '01:00 PM', 550, 40, '10x4', 'A1,A2,A3,A4,B1,B2,B3,B4,C1,C2,C3,C4,D1,D2,D3,D4,E1,E2,E3,E4', 'F1,F2,F3,F4,G1,G2,G3,G4,H1,H2', 'H3,H4,I1,I2,I3,I4,J1,J2,J3,J4', 1),
(6, 'Shohag Paribahan', 'DH-77777', 4, 'Dhaka', '08:30 PM', 'Khulna', '03:30 AM', 650, 40, '10x4', 'A1,A2,A3,A4,B1,B2,B3,B4,C1,C2,C3,C4,D1,D2,D3,D4,E1,E2,E3,E4', 'F1,F2,F3,F4,G1,G2,G3,G4,H1,H2', 'H3,H4,I1,I2,I3,I4,J1,J2,J3,J4', 1);

-- ========================================
-- TABLE: tickets (WITH BOOKING FEATURES)
-- ========================================

CREATE TABLE `tickets` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `passenger_id` int(5) NOT NULL,
  `bus_id` int(5) NOT NULL,
  `jdate` varchar(25) NOT NULL,
  `seats` varchar(120) NOT NULL,
  `fare` int(10) NOT NULL,
  `booking_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the booking was initiated',
  `booking_expires` datetime NULL COMMENT 'When the booking hold expires (5 minutes after booking_time)',
  `booking_confirmed` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = on hold, 1 = confirmed',
  `seat_category` enum('student','staff','general','mixed') DEFAULT 'general' COMMENT 'Category of seats booked',
  PRIMARY KEY (`id`),
  KEY `fk_usr_tic` (`passenger_id`),
  KEY `fk_bus_tic` (`bus_id`),
  KEY `idx_booking_time` (`booking_time`),
  KEY `idx_jdate_bus` (`jdate`, `bus_id`),
  CONSTRAINT `fk_usr_tic` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bus_tic` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ========================================
-- TABLE: booking_history
-- ========================================

CREATE TABLE `booking_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(5) NOT NULL COMMENT 'User who made the booking (passenger, student, or staff)',
  `user_type` enum('Passenger','Student','Staff') NOT NULL COMMENT 'Type of user',
  `ticket_id` int(5) DEFAULT NULL COMMENT 'Reference to ticket if booking was confirmed',
  `bus_id` int(5) NOT NULL,
  `bus_name` varchar(25) NOT NULL,
  `bus_no` varchar(25) NOT NULL,
  `from_loc` varchar(20) NOT NULL,
  `to_loc` varchar(20) NOT NULL,
  `jdate` varchar(25) NOT NULL COMMENT 'Journey date',
  `seats_booked` text NOT NULL COMMENT 'Serialized array of seat numbers',
  `seat_count` int(3) NOT NULL COMMENT 'Number of seats booked',
  `seat_category` enum('student','staff','general','mixed') DEFAULT 'general',
  `fare_per_seat` decimal(10,2) NOT NULL,
  `total_fare` decimal(10,2) NOT NULL,
  `discount_percent` int(3) DEFAULT 0 COMMENT 'Discount percentage applied',
  `final_amount` decimal(10,2) NOT NULL COMMENT 'Amount after discount',
  `booking_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `booking_status` enum('pending','confirmed','cancelled','expired') NOT NULL DEFAULT 'confirmed',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'paid',
  `cancellation_time` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_bus_id` (`bus_id`),
  KEY `idx_jdate` (`jdate`),
  KEY `idx_booking_time` (`booking_time`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ========================================
-- TABLE: seat_templates
-- ========================================

CREATE TABLE `seat_templates` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT 'Template name (e.g., Small Bus, Large Bus)',
  `total_seats` int(3) NOT NULL COMMENT 'Total number of seats',
  `layout` varchar(50) NOT NULL COMMENT 'Seat layout (e.g., 10x4)',
  `description` varchar(255) DEFAULT NULL COMMENT 'Template description',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `seat_templates`
--

INSERT INTO `seat_templates` (`name`, `total_seats`, `layout`, `description`) VALUES
('Small Bus', 20, '5x4', '20 seats in 5 rows, 4 seats per row - Compact bus for short routes'),
('Standard Bus', 40, '10x4', '40 seats in 10 rows, 4 seats per row - Most common configuration'),
('Large Bus', 50, '13x4', '50 seats in 13 rows (includes 2 back row seats) - High capacity'),
('Luxury Coach', 30, '8x4', '30 seats in 8 rows, spacious seating - Premium comfort');

-- ========================================
-- TABLE: driver_earnings
-- ========================================

CREATE TABLE `driver_earnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(5) NOT NULL COMMENT 'Bus owner/driver ID',
  `bus_id` int(5) NOT NULL,
  `jdate` varchar(25) NOT NULL,
  `total_seats_sold` int(3) NOT NULL DEFAULT 0,
  `student_seats_sold` int(3) NOT NULL DEFAULT 0,
  `staff_seats_sold` int(3) NOT NULL DEFAULT 0,
  `general_seats_sold` int(3) NOT NULL DEFAULT 0,
  `total_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `student_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `staff_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `general_revenue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT 10.00 COMMENT 'Platform commission percentage',
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  `net_earnings` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Revenue minus commission',
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bus_date` (`bus_id`, `jdate`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_jdate` (`jdate`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ========================================
-- TABLE: earnings (Legacy - kept for compatibility)
-- ========================================

CREATE TABLE `earnings` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `bus_id` int(5) NOT NULL,
  `date` varchar(10) NOT NULL,
  `ssold` int(3) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_ear_bus` (`bus_id`),
  CONSTRAINT `fk_ear_bus` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ========================================
-- TABLE: notices
-- ========================================

CREATE TABLE `notices` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `recep` int(5) NOT NULL,
  `message` varchar(120) NOT NULL,
  `from` int(5) NOT NULL,
  `title` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_usrf_not` (`from`),
  KEY `fk_usrt_not` (`recep`),
  CONSTRAINT `fk_usrf_not` FOREIGN KEY (`from`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_usrt_not` FOREIGN KEY (`recep`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ========================================
-- TRIGGERS: Automatic Booking History
-- ========================================

DELIMITER $$

DROP TRIGGER IF EXISTS `after_ticket_insert`$$

CREATE TRIGGER `after_ticket_insert` AFTER INSERT ON `tickets`
FOR EACH ROW
BEGIN
  DECLARE v_user_type VARCHAR(20);
  DECLARE v_bus_name VARCHAR(25);
  DECLARE v_bus_no VARCHAR(25);
  DECLARE v_from_loc VARCHAR(20);
  DECLARE v_to_loc VARCHAR(20);
  DECLARE v_seat_count INT;
  DECLARE v_discount DECIMAL(5,2);
  DECLARE v_final_amount DECIMAL(10,2);
  
  -- Get user type
  SELECT utype INTO v_user_type FROM users WHERE id = NEW.passenger_id;
  
  -- Get bus details
  SELECT bname, bus_no, from_loc, to_loc 
  INTO v_bus_name, v_bus_no, v_from_loc, v_to_loc
  FROM buses WHERE id = NEW.bus_id;
  
  -- Count seats (estimate from serialized data length)
  SET v_seat_count = FLOOR((LENGTH(NEW.seats) - LENGTH(REPLACE(NEW.seats, '";', ''))) / 2);
  IF v_seat_count = 0 THEN
    SET v_seat_count = 1;
  END IF;
  
  -- Calculate discount
  SET v_discount = CASE 
    WHEN v_user_type = 'Student' THEN 10
    WHEN v_user_type = 'Staff' THEN 5
    ELSE 0
  END;
  
  -- Calculate final amount
  SET v_final_amount = NEW.fare * (1 - v_discount/100);
  
  -- Insert into booking history
  INSERT INTO booking_history (
    user_id, user_type, ticket_id, bus_id, bus_name, bus_no,
    from_loc, to_loc, jdate, seats_booked, seat_count, seat_category,
    fare_per_seat, total_fare, discount_percent, final_amount,
    booking_time, booking_status, payment_status
  ) VALUES (
    NEW.passenger_id,
    v_user_type,
    NEW.id,
    NEW.bus_id,
    v_bus_name,
    v_bus_no,
    v_from_loc,
    v_to_loc,
    NEW.jdate,
    NEW.seats,
    v_seat_count,
    COALESCE(NEW.seat_category, 'general'),
    NEW.fare / v_seat_count,
    NEW.fare,
    v_discount,
    v_final_amount,
    NEW.booking_time,
    IF(NEW.booking_confirmed = 1, 'confirmed', 'pending'),
    'paid'
  );
END$$

DELIMITER ;

-- ========================================
-- STORED PROCEDURES
-- ========================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `calculate_driver_earnings`$$

CREATE PROCEDURE `calculate_driver_earnings`(
  IN p_bus_id INT,
  IN p_jdate VARCHAR(25)
)
BEGIN
  DECLARE v_owner_id INT;
  DECLARE v_total_seats INT DEFAULT 0;
  DECLARE v_student_seats INT DEFAULT 0;
  DECLARE v_staff_seats INT DEFAULT 0;
  DECLARE v_general_seats INT DEFAULT 0;
  DECLARE v_total_revenue DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_student_revenue DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_staff_revenue DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_general_revenue DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_commission DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_net DECIMAL(10,2) DEFAULT 0.00;
  
  -- Get owner ID
  SELECT owner_id INTO v_owner_id FROM buses WHERE id = p_bus_id;
  
  -- Calculate seats and revenue by category
  SELECT 
    COALESCE(COUNT(*), 0) as total,
    COALESCE(SUM(CASE WHEN seat_category = 'student' THEN seat_count ELSE 0 END), 0) as student,
    COALESCE(SUM(CASE WHEN seat_category = 'staff' THEN seat_count ELSE 0 END), 0) as staff,
    COALESCE(SUM(CASE WHEN seat_category IN ('general', 'mixed') THEN seat_count ELSE 0 END), 0) as general_cnt,
    COALESCE(SUM(final_amount), 0) as total_rev,
    COALESCE(SUM(CASE WHEN seat_category = 'student' THEN final_amount ELSE 0 END), 0) as student_rev,
    COALESCE(SUM(CASE WHEN seat_category = 'staff' THEN final_amount ELSE 0 END), 0) as staff_rev,
    COALESCE(SUM(CASE WHEN seat_category IN ('general', 'mixed') THEN final_amount ELSE 0 END), 0) as general_rev
  INTO v_total_seats, v_student_seats, v_staff_seats, v_general_seats,
       v_total_revenue, v_student_revenue, v_staff_revenue, v_general_revenue
  FROM booking_history
  WHERE bus_id = p_bus_id AND jdate = p_jdate AND booking_status = 'confirmed';
  
  -- Calculate commission (10%)
  SET v_commission = v_total_revenue * 0.10;
  SET v_net = v_total_revenue - v_commission;
  
  -- Insert or update driver earnings
  INSERT INTO driver_earnings (
    owner_id, bus_id, jdate, total_seats_sold,
    student_seats_sold, staff_seats_sold, general_seats_sold,
    total_revenue, student_revenue, staff_revenue, general_revenue,
    commission_amount, net_earnings
  ) VALUES (
    v_owner_id, p_bus_id, p_jdate, v_total_seats,
    v_student_seats, v_staff_seats, v_general_seats,
    v_total_revenue, v_student_revenue, v_staff_revenue, v_general_revenue,
    v_commission, v_net
  )
  ON DUPLICATE KEY UPDATE
    total_seats_sold = v_total_seats,
    student_seats_sold = v_student_seats,
    staff_seats_sold = v_staff_seats,
    general_seats_sold = v_general_seats,
    total_revenue = v_total_revenue,
    student_revenue = v_student_revenue,
    staff_revenue = v_staff_revenue,
    general_revenue = v_general_revenue,
    commission_amount = v_commission,
    net_earnings = v_net;
END$$

DELIMITER ;

-- ========================================
-- VIEWS
-- ========================================

DROP VIEW IF EXISTS `v_booking_history_detail`;

CREATE VIEW `v_booking_history_detail` AS
SELECT 
  bh.*,
  u.uname as username,
  u.email as user_email,
  u.mobile as user_mobile,
  b.from_time,
  b.to_time,
  b.fare as current_fare
FROM booking_history bh
JOIN users u ON bh.user_id = u.id
JOIN buses b ON bh.bus_id = b.id
ORDER BY bh.booking_time DESC;

-- ========================================
-- AUTO_INCREMENT RESET
-- ========================================

ALTER TABLE `buses` AUTO_INCREMENT=7;
ALTER TABLE `users` AUTO_INCREMENT=10;
ALTER TABLE `locations` AUTO_INCREMENT=11;
ALTER TABLE `seat_templates` AUTO_INCREMENT=5;
ALTER TABLE `tickets` AUTO_INCREMENT=1;
ALTER TABLE `booking_history` AUTO_INCREMENT=1;
ALTER TABLE `driver_earnings` AUTO_INCREMENT=1;
ALTER TABLE `earnings` AUTO_INCREMENT=1;
ALTER TABLE `notices` AUTO_INCREMENT=1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========================================
-- VERIFICATION QUERIES
-- ========================================

-- Show summary
SELECT 'Database setup complete!' as Status;
SELECT COUNT(*) as Total_Users FROM users;
SELECT COUNT(*) as Total_Buses FROM buses;
SELECT COUNT(*) as Total_Locations FROM locations;
SELECT 'Trigger created: after_ticket_insert' as Triggers;
SELECT 'Procedure created: calculate_driver_earnings' as Procedures;
SELECT 'View created: v_booking_history_detail' as Views;

-- ========================================
-- END OF SQL FILE
-- ========================================