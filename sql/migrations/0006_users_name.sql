-- Display name for assignee column (falls back to email when empty).

ALTER TABLE users ADD COLUMN name VARCHAR(100) NULL AFTER email;
