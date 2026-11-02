-- Schema for the Prototype application.
-- Import with:  mysql -u root < setup_database.sql
-- Safe to re-run: everything uses IF NOT EXISTS / INSERT IGNORE.

CREATE DATABASE IF NOT EXISTS `login`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `login`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `firstName` VARCHAR(100) NOT NULL,
    `lastName` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('faculty', 'planning_officer', 'admin') DEFAULT 'faculty',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_no` INT NULL DEFAULT NULL,
    `ticket_year` SMALLINT NULL DEFAULT NULL,
    `travel_date` DATE NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `purpose` TEXT NOT NULL,
    `passengers` VARCHAR(50) NOT NULL,
    `driver` VARCHAR(100) NULL DEFAULT NULL,
    `requested_by` VARCHAR(100) NOT NULL,
    `user_email` VARCHAR(255) NULL,
    `attachment_name` VARCHAR(255) NULL,
    `attachment_data` LONGBLOB NULL,
    `request_date` DATETIME NOT NULL,
    `status` ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    `reviewed_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ticket` (`ticket_year`, `ticket_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `requested_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `names` VARCHAR(255) NOT NULL,
    `user_email` VARCHAR(255) NULL,
    `items` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `when_needed` DATETIME NULL,
    `action` VARCHAR(50) DEFAULT 'pending',
    `request_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status_updated_date` TIMESTAMP NULL DEFAULT NULL,
    `updated_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('vehicle_request', 'item_request', 'account_update', 'system') NOT NULL,
    `recipient_role` ENUM('faculty', 'planning_officer', 'all') NOT NULL,
    `recipient_email` VARCHAR(255) NULL,
    `sender_id` INT NULL,
    `sender_name` VARCHAR(255) NULL,
    `related_id` INT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `read_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `badge_seen` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `section` VARCHAR(50) NOT NULL UNIQUE,
    `seen_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendar_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `event_date` DATE NOT NULL,
    `event_time` TIME,
    `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
    `created_by` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_fleet` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vehicle_name` VARCHAR(100) NULL,
    `vehicle_type` VARCHAR(50) NOT NULL,
    `plate_number` VARCHAR(20) NOT NULL UNIQUE,
    `capacity` INT NOT NULL DEFAULT 1,
    `model` VARCHAR(100),
    `year_manufactured` INT,
    `status` ENUM('available', 'maintenance', 'retired') DEFAULT 'available',
    `fuel_type` VARCHAR(50),
    `insurance_expiry` DATE,
    `last_maintenance` DATE,
    `created_by` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_management` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_name` VARCHAR(255) NOT NULL UNIQUE,
    `quantity` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `borrow_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `names` VARCHAR(255) NOT NULL,
    `items` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL,
    `time_of_borrow` VARCHAR(50) NULL,
    `time_of_return` VARCHAR(50) NULL,
    `reason` TEXT,
    `action` VARCHAR(50) DEFAULT 'pending',
    `status` VARCHAR(50) DEFAULT 'pending',
    `requestDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample accounts from setup_database.php. Change these passwords after logging in.
INSERT IGNORE INTO `users` (`firstName`, `lastName`, `email`, `password`, `role`) VALUES
    ('John', 'Doe', 'john.doe@example.com', 'password123', 'faculty'),
    ('Jane', 'Smith', 'jane.smith@example.com', 'password123', 'planning_officer'),
    ('Admin', 'User', 'admin@example.com', 'admin123', 'admin');
