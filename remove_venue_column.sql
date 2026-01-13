-- ================================================
-- REMOVE VENUE COLUMN FROM PARTICIPATION RECORDS
-- ================================================
-- Date: January 13, 2026
-- Purpose: Remove venue column as it's no longer needed
-- ================================================

-- Remove venue column from student_participation_records table
ALTER TABLE `student_participation_records` 
DROP COLUMN IF EXISTS `venue`;

-- Done! Venue column removed from participation records.
-- The table now only has: id, student_id, participation_date, 
-- event_name, participation_level, rank_award, created_at, updated_at
