-- FileName: database.sql

-- Create the database
CREATE DATABASE IF NOT EXISTS `login_db`;

-- Use the database
USE `login_db`;

-- Create the users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('student','teacher') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `users`
ADD COLUMN `reset_token` VARCHAR(255) NULL DEFAULT NULL,
ADD COLUMN `reset_token_expires_at` DATETIME NULL DEFAULT NULL;
