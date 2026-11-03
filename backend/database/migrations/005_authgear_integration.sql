-- Migration: 005_authgear_integration.sql
-- Add Authgear user ID mapping to users table

ALTER TABLE `users` ADD COLUMN `authgear_user_id` VARCHAR(190) NULL UNIQUE AFTER `id`;

