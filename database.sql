-- Culture and Arts Management System Database
-- Database: capstone_culture_arts

CREATE DATABASE IF NOT EXISTS capstone_culture_arts;
USE capstone_culture_arts;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'staff', 'head', 'central') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admin logs table (for tracking admin actions)
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    target_user_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_admin_logs_admin_id ON admin_logs(admin_id);
CREATE INDEX idx_admin_logs_created_at ON admin_logs(created_at);

-- Applications table (for storing all performer profile applications)
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_type ENUM('performer_profile') DEFAULT 'performer_profile',
    
    -- Performance types (comma-separated values)
    performance_type TEXT NULL,
    
    -- Consent and basic info
    consent ENUM('yes', 'no') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    present_address TEXT NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    place_of_birth VARCHAR(100) NULL,
    
    -- Contact information
    email VARCHAR(100) NULL,
    contact_number VARCHAR(20) NULL,
    
    -- Family information
    father_name VARCHAR(100) NULL,
    mother_name VARCHAR(100) NULL,
    guardian VARCHAR(100) NULL,
    guardian_contact VARCHAR(20) NULL,
    
    -- Academic information
    campus VARCHAR(100) NULL,
    college VARCHAR(100) NULL,
    sr_code VARCHAR(50) NULL,
    year_level VARCHAR(20) NULL,
    program VARCHAR(100) NULL,
    first_semester_units INT NULL,
    second_semester_units INT NULL,
    
    -- Files and signature
    profile_photo VARCHAR(255) NULL,
    signature_image VARCHAR(255) NULL,
    signature_date DATE NULL,
    
    -- Certification
    certification BOOLEAN DEFAULT FALSE,
    
    -- Application status and tracking
    application_status ENUM('pending', 'under_review', 'approved', 'rejected', 'requires_documents') DEFAULT 'pending',
    submitted_by INT NULL,
    reviewed_by INT NULL,
    review_notes TEXT NULL,
    review_date DATETIME NULL,
    
    -- Timestamps
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Application participation table (for dynamic participation records)
CREATE TABLE application_participation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    participation_date DATE NULL,
    event_name VARCHAR(200) NULL,
    participation_level ENUM('local', 'regional', 'national', 'international') NULL,
    rank_award VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Application affiliation table (for dynamic affiliation records)
CREATE TABLE application_affiliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    position VARCHAR(100) NULL,
    organization VARCHAR(150) NULL,
    years_active VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Create indexes for applications table
CREATE INDEX idx_applications_type ON applications(application_type);
CREATE INDEX idx_applications_status ON applications(application_status);
CREATE INDEX idx_applications_submitted_by ON applications(submitted_by);
CREATE INDEX idx_applications_sr_code ON applications(sr_code);
CREATE INDEX idx_applications_campus ON applications(campus);
CREATE INDEX idx_applications_submitted_at ON applications(submitted_at);
CREATE INDEX idx_application_participation_app_id ON application_participation(application_id);
CREATE INDEX idx_application_affiliations_app_id ON application_affiliations(application_id);

-- Return requests table for costume/equipment return workflow
CREATE TABLE IF NOT EXISTS return_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrowing_request_id INT NOT NULL,
    student_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    condition_notes TEXT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    completed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (borrowing_request_id) REFERENCES borrowing_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES student_artists(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_return_requests_status (status),
    INDEX idx_return_requests_student (student_id),
    INDEX idx_return_requests_borrowing (borrowing_request_id),
    INDEX idx_return_requests_requested_at (requested_at)
);
