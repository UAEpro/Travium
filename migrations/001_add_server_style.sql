-- Migration: Add serverStyle column to gameServers table
-- Run this on the GLOBAL database for existing installations
-- New installations already include this column in maindb.sql

ALTER TABLE `gameServers` ADD `serverStyle` VARCHAR(20) NOT NULL DEFAULT 'modern' AFTER `configFileLocation`;
