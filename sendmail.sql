-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS sendmail;

-- Use the database
USE sendmail;

-- Create the mails table
CREATE TABLE IF NOT EXISTS mails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
ALTER TABLE mails ADD INDEX idx_email (email);
ALTER TABLE mails ADD INDEX idx_created_at (created_at);