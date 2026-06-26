-- Migration: add missing columns to admins table
ALTER TABLE `admins` ADD COLUMN `email` VARCHAR(255) NULL UNIQUE AFTER `username`;
ALTER TABLE `admins` ADD COLUMN `reset_token` VARCHAR(64) NULL AFTER `last_login`;
ALTER TABLE `admins` ADD COLUMN `reset_token_expiry` DATETIME NULL AFTER `reset_token`;
