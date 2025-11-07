-- Create student_certificates table
CREATE TABLE IF NOT EXISTS student_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date_received DATE NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_date_received (date_received)
);

-- Add some sample data (optional - remove in production)
-- This is just for testing purposes
-- INSERT INTO student_certificates (student_id, title, description, date_received, file_path) 
-- VALUES 
-- (1, 'Excellence in Performance', 'Outstanding performance in cultural dance competition', '2024-10-15', 'uploads/certificates/sample_cert1.pdf'),
-- (1, 'Best Dancer Award', 'Awarded for exceptional dancing skills', '2024-09-20', 'uploads/certificates/sample_cert2.jpg');