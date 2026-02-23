-- Create default admin user for global admin panel.
-- Password is set by entrypoint.sh using ADMIN_PASSWORD env var.
-- This just ensures the table exists if maindb.sql was already imported before the table was added.

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super','admin') NOT NULL DEFAULT 'admin',
  `created_at` int(10) UNSIGNED NOT NULL,
  `last_login` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
