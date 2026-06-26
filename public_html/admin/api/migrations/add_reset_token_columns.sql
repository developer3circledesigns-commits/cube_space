-- Add reset token columns to admins table
ALTER TABLE admins 
ADD COLUMN reset_token VARCHAR(64) NULL,
ADD COLUMN reset_token_expiry DATETIME NULL;
