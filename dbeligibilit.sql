-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS eligibility CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE eligibility;

-- Create the 'students' table
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  student_id VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the 'scholarship_applications' table
CREATE TABLE IF NOT EXISTS scholarship_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    field VARCHAR(255) NOT NULL,
    gpa VARCHAR(10) NOT NULL,
    school VARCHAR(100) NOT NULL,
    scholarship VARCHAR(100) NOT NULL,
    statement TEXT NOT NULL,
    transcript VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
