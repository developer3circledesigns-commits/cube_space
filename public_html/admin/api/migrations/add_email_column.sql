-- Add email column to admins table
ALTER TABLE admins 
ADD COLUMN email VARCHAR(255) NULL AFTER username,
ADD UNIQUE INDEX idx_email (email);
