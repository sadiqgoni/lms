-- Add status column to enrollments if it doesn't exist
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending';

-- Create student_progress table if it doesn't exist
CREATE TABLE IF NOT EXISTS student_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    material_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    last_accessed DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    UNIQUE KEY unique_progress (user_id, material_id)
);
