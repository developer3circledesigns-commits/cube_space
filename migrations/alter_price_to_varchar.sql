-- Change price column from numeric to VARCHAR to support free-text values
ALTER TABLE managed_offices    MODIFY COLUMN price VARCHAR(255) DEFAULT NULL;
ALTER TABLE furnished_offices  MODIFY COLUMN price VARCHAR(255) DEFAULT NULL;
ALTER TABLE unfurnished_offices MODIFY COLUMN price VARCHAR(255) DEFAULT NULL;
ALTER TABLE office_spaces      MODIFY COLUMN price VARCHAR(255) DEFAULT NULL;
