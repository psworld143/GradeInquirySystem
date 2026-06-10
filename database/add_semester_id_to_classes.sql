-- Add semester_id column to classes table
ALTER TABLE classes
ADD COLUMN semester_id INT,
ADD FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT;

-- Update existing classes to use the active semester
UPDATE classes c
JOIN semesters s ON s.is_active = 1
SET c.semester_id = s.id; 