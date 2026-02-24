-- Migration 001: Add serverStyle column to gameServers table
-- This is idempotent - safe to run on both fresh and existing installations

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'gameServers'
    AND column_name = 'serverStyle');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `gameServers` ADD `serverStyle` VARCHAR(20) NOT NULL DEFAULT ''modern'' AFTER `configFileLocation`',
    'SELECT ''Column serverStyle already exists, skipping.'' AS info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
