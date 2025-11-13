-- Clean database backup for web hosting
-- Database tables only (no database creation)

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- Table structure for table users (CREATE FIRST - no foreign keys)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','staff','head','central','admin') NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table student_artists (CREATE SECOND - no foreign keys)
DROP TABLE IF EXISTS `student_artists`;
CREATE TABLE `student_artists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `sr_code` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `performance_experience` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended','graduated') DEFAULT 'active',
  `enrollment_date` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sr_code` (`sr_code`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_student_artists_email` (`email`),
  KEY `idx_student_artists_sr_code` (`sr_code`),
  KEY `idx_student_artists_status` (`status`),
  KEY `idx_student_artists_campus` (`campus`),
  KEY `idx_student_artists_course` (`course`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table events (CREATE THIRD - references users)
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `status` enum('draft','published','ongoing','completed','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_dates` (`start_date`,`end_date`),
  KEY `idx_events_created_at` (`created_at`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table inventory (CREATE FOURTH - references users)
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `condition_status` enum('excellent','good','fair','poor','damaged') DEFAULT 'good',
  `location` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_inventory_category` (`category`),
  KEY `idx_inventory_condition` (`condition_status`),
  KEY `idx_inventory_name` (`item_name`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table admin_logs (NOW references users)
DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `target_user_id` (`target_user_id`),
  CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_logs_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table announcements
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `target_audience` enum('all','students','staff','head','central') DEFAULT 'all',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_announcements_active` (`is_active`),
  KEY `idx_announcements_priority` (`priority`),
  KEY `idx_announcements_target` (`target_audience`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table applications (MOVED HERE - referenced by other tables)
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_type` enum('performer_profile') DEFAULT 'performer_profile',
  `performance_type` text DEFAULT NULL,
  `consent` enum('yes','no') NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `place_of_birth` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `guardian` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `college` varchar(100) DEFAULT NULL,
  `sr_code` varchar(50) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `first_semester_units` int(11) DEFAULT NULL,
  `second_semester_units` int(11) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `signature_image` varchar(255) DEFAULT NULL,
  `signature_date` date DEFAULT NULL,
  `certification` tinyint(1) DEFAULT 0,
  `application_status` enum('pending','under_review','approved','rejected','requires_documents') DEFAULT 'pending',
  `submitted_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `submitted_by` (`submitted_by`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_applications_type` (`application_type`),
  KEY `idx_applications_status` (`application_status`),
  KEY `idx_applications_sr_code` (`sr_code`),
  KEY `idx_applications_campus` (`campus`),
  KEY `idx_applications_submitted_at` (`submitted_at`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table application_affiliations
DROP TABLE IF EXISTS `application_affiliations`;
CREATE TABLE `application_affiliations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `years_active` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `application_affiliations_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table application_participation
DROP TABLE IF EXISTS `application_participation`;
CREATE TABLE `application_participation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `participation_date` date DEFAULT NULL,
  `event_name` varchar(200) DEFAULT NULL,
  `participation_level` enum('local','regional','national','international') DEFAULT NULL,
  `rank_award` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `application_participation_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table borrowing_requests
DROP TABLE IF EXISTS `borrowing_requests`;
CREATE TABLE `borrowing_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_email` varchar(255) NOT NULL,
  `student_course` varchar(255) DEFAULT NULL,
  `student_year` varchar(50) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_category` varchar(100) DEFAULT NULL,
  `quantity_requested` int(11) NOT NULL DEFAULT 1,
  `purpose` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','approved','rejected','returned') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `item_id` (`item_id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_borrowing_requests_status` (`status`),
  KEY `idx_borrowing_requests_dates` (`start_date`,`end_date`),
  KEY `idx_borrowing_requests_created` (`created_at`),
  CONSTRAINT `borrowing_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `borrowing_requests_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  CONSTRAINT `borrowing_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table deleted_students
DROP TABLE IF EXISTS `deleted_students`;
CREATE TABLE `deleted_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `sr_code` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deletion_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `idx_deleted_students_sr_code` (`sr_code`),
  KEY `idx_deleted_students_deleted_at` (`deleted_at`),
  CONSTRAINT `deleted_students_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table evaluation_summary
DROP TABLE IF EXISTS `evaluation_summary`;
CREATE TABLE `evaluation_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `total_responses` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`),
  CONSTRAINT `evaluation_summary_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table event_evaluations
DROP TABLE IF EXISTS `event_evaluations`;
CREATE TABLE `event_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `participant_name` varchar(255) NOT NULL,
  `participant_email` varchar(255) NOT NULL,
  `q1_content_quality` int(11) NOT NULL CHECK (`q1_content_quality` between 1 and 5),
  `q2_speaker_effectiveness` int(11) NOT NULL CHECK (`q2_speaker_effectiveness` between 1 and 5),
  `q3_organization` int(11) NOT NULL CHECK (`q3_organization` between 1 and 5),
  `q4_venue_suitability` int(11) NOT NULL CHECK (`q4_venue_suitability` between 1 and 5),
  `q5_overall_satisfaction` int(11) NOT NULL CHECK (`q5_overall_satisfaction` between 1 and 5),
  `additional_comments` text DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `would_recommend` enum('yes','no','maybe') DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant_evaluation` (`event_id`,`participant_id`),
  KEY `participant_id` (`participant_id`),
  KEY `idx_event_evaluations_event` (`event_id`),
  KEY `idx_event_evaluations_submitted` (`submitted_at`),
  CONSTRAINT `event_evaluations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_evaluations_ibfk_2` FOREIGN KEY (`participant_id`) REFERENCES `student_artists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table event_participants
DROP TABLE IF EXISTS `event_participants`;
CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `attendance_status` enum('registered','attended','absent','cancelled') DEFAULT 'registered',
  `attendance_marked_at` timestamp NULL DEFAULT NULL,
  `attendance_marked_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_participant` (`event_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `attendance_marked_by` (`attendance_marked_by`),
  KEY `idx_event_participants_event` (`event_id`),
  KEY `idx_event_participants_student` (`student_id`),
  KEY `idx_event_participants_status` (`attendance_status`),
  CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student_artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_participants_ibfk_3` FOREIGN KEY (`attendance_marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table events
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `status` enum('draft','published','ongoing','completed','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_dates` (`start_date`,`end_date`),
  KEY `idx_events_created_at` (`created_at`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table inventory
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `condition_status` enum('excellent','good','fair','poor','damaged') DEFAULT 'good',
  `location` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_inventory_category` (`category`),
  KEY `idx_inventory_condition` (`condition_status`),
  KEY `idx_inventory_name` (`item_name`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table question_analysis
DROP TABLE IF EXISTS `question_analysis`;
CREATE TABLE `question_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `question_number` int(11) NOT NULL,
  `question_text` varchar(500) NOT NULL,
  `avg_score` decimal(3,2) DEFAULT NULL,
  `response_count` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_question` (`event_id`,`question_number`),
  CONSTRAINT `question_analysis_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table return_requests
DROP TABLE IF EXISTS `return_requests`;
CREATE TABLE `return_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `borrowing_request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `condition_notes` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `borrowing_request_id` (`borrowing_request_id`),
  KEY `student_id` (`student_id`),
  KEY `item_id` (`item_id`),
  KEY `completed_by` (`completed_by`),
  KEY `idx_return_requests_status` (`status`),
  KEY `idx_return_requests_requested_at` (`requested_at`),
  CONSTRAINT `return_requests_ibfk_1` FOREIGN KEY (`borrowing_request_id`) REFERENCES `borrowing_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student_artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_4` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table student_artists
DROP TABLE IF EXISTS `student_artists`;
CREATE TABLE `student_artists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `sr_code` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `performance_experience` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended','graduated') DEFAULT 'active',
  `enrollment_date` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sr_code` (`sr_code`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_student_artists_email` (`email`),
  KEY `idx_student_artists_sr_code` (`sr_code`),
  KEY `idx_student_artists_status` (`status`),
  KEY `idx_student_artists_campus` (`campus`),
  KEY `idx_student_artists_course` (`course`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table student_certificates
DROP TABLE IF EXISTS `student_certificates`;
CREATE TABLE `student_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `certificate_type` varchar(100) NOT NULL,
  `certificate_name` varchar(255) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `issued_date` date NOT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `certificate_file` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `verification_code` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `event_id` (`event_id`),
  KEY `issued_by` (`issued_by`),
  KEY `idx_student_certificates_student` (`student_id`),
  KEY `idx_student_certificates_type` (`certificate_type`),
  KEY `idx_student_certificates_date` (`issued_date`),
  KEY `idx_student_certificates_verification` (`verification_code`),
  CONSTRAINT `student_certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_artists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_certificates_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_certificates_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table system_settings
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_editable` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_system_settings_category` (`category`),
  KEY `idx_system_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
SET SQL_MODE = "";
SET AUTOCOMMIT = 1;