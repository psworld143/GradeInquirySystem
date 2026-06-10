-- Create school_years table
CREATE TABLE IF NOT EXISTS school_years (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_year VARCHAR(9) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create semesters table
CREATE TABLE IF NOT EXISTS semesters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    semester_name VARCHAR(50) NOT NULL,
    school_year_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_semester_per_year (semester_name, school_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default school year (2023-2024)
INSERT INTO school_years (school_year, is_active) VALUES ('2023-2024', 1);

-- Insert default semesters for 2023-2024
INSERT INTO semesters (semester_name, school_year_id, is_active) 
SELECT 'First Semester', id, 1 FROM school_years WHERE school_year = '2023-2024';

INSERT INTO semesters (semester_name, school_year_id, is_active) 
SELECT 'Second Semester', id, 0 FROM school_years WHERE school_year = '2023-2024'; 