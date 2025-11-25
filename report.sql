-- This script will set up the tables needed for your report page to work.

-- First, we remove the old 'problems' table to avoid confusion.
DROP TABLE IF EXISTS `problems`;

-- Create the 'labs' table to store lab names
CREATE TABLE `labs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the 'computers' table to store each PC
CREATE TABLE `computers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_id` int(11) NOT NULL,
  `pc_number` int(11) NOT NULL,
  `status` enum('OK','Reported','Reworking') NOT NULL DEFAULT 'OK',
  PRIMARY KEY (`id`),
  KEY `lab_id` (`lab_id`),
  CONSTRAINT `computers_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the 'reports' table to store all the submitted fault reports
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `computer_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Reported','Reworking','Resolved') NOT NULL DEFAULT 'Reported',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `computer_id` (`computer_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`computer_id`) REFERENCES `computers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add some sample data so the site has something to show
INSERT INTO `labs` (`id`, `name`) VALUES (1, 'VLC'), (2, 'Computer Lab'), (3, 'Computer Center');
INSERT INTO `computers` (`lab_id`, `pc_number`, `status`) VALUES (1, 1, 'OK'), (1, 2, 'OK'), (1, 3, 'Reported'), (2, 1, 'OK'), (2, 2, 'Reworking');

