-- ============================================
-- MIGRATION SCRIPT - Fix Events Table
-- ============================================
-- This script fixes the category field and ensures proper structure
-- Run this in phpMyAdmin or MySQL command line
-- Date: November 17, 2025
-- ============================================

-- 1. Change the category column from ENUM to VARCHAR
ALTER TABLE `events` 
MODIFY COLUMN `category` VARCHAR(100) DEFAULT 'other';

-- 2. Update any existing values to match the form values
UPDATE `events` 
SET `category` = CASE 
    WHEN LOWER(`category`) = 'training' THEN 'Training'
    WHEN LOWER(`category`) = 'performance' THEN 'Performance'
    WHEN LOWER(`category`) = 'competition' THEN 'Competition'
    WHEN LOWER(`category`) = 'workshop' THEN 'Workshop'
    WHEN LOWER(`category`) = 'cultural_show' THEN 'Cultural Event'
    WHEN LOWER(`category`) = 'festival' THEN 'Festival'
    WHEN LOWER(`category`) = 'seminar' THEN 'Seminar'
    ELSE `category`
END
WHERE `category` IS NOT NULL;

-- 3. Verify the changes
SELECT 'Migration completed successfully!' AS Status;
SELECT COUNT(*) AS 'Total Events' FROM `events`;
SELECT `id`, `title`, `category`, LEFT(`cultural_groups`, 50) AS cultural_groups_preview 
FROM `events` 
ORDER BY `created_at` DESC 
LIMIT 10;
