-- Run this only if you already imported the original schema.sql (before subsystems/colors existed).
-- Safe to run once; re-running will error on duplicate columns/tables, which is fine to ignore.

ALTER TABLE projects ADD COLUMN bg_color VARCHAR(7) NULL, ADD COLUMN text_color VARCHAR(7) NULL;
ALTER TABLE priorities ADD COLUMN bg_color VARCHAR(7) NULL, ADD COLUMN text_color VARCHAR(7) NULL;
ALTER TABLE statuses ADD COLUMN bg_color VARCHAR(7) NULL, ADD COLUMN text_color VARCHAR(7) NULL;

CREATE TABLE IF NOT EXISTS subsystems (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  bg_color VARCHAR(7) NULL,
  text_color VARCHAR(7) NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE items ADD COLUMN subsystem_id INT NULL AFTER category_id;
ALTER TABLE items ADD FOREIGN KEY (subsystem_id) REFERENCES subsystems(id) ON DELETE SET NULL;
ALTER TABLE items DROP COLUMN subsystem;

UPDATE statuses SET bg_color='#e5e7eb', text_color='#374151' WHERE name='TODO';
UPDATE statuses SET bg_color='#bfdbfe', text_color='#1e3a8a' WHERE name='UNDERWAY';
UPDATE statuses SET bg_color='#bbf7d0', text_color='#14532d' WHERE name='OK';
UPDATE statuses SET bg_color='#f3f4f6', text_color='#6b7280' WHERE name='N/A';
UPDATE statuses SET bg_color='#fecaca', text_color='#7f1d1d' WHERE name='BLOCKED';

UPDATE projects SET bg_color='#ffd6d6', text_color='#7a2e2e' WHERE bg_color IS NULL;

UPDATE priorities SET bg_color='#b91c1c', text_color='#ffffff' WHERE name='1-Critical';
UPDATE priorities SET bg_color='#f08080', text_color='#3a1212' WHERE name='2-High';
UPDATE priorities SET bg_color='#ddc1c1', text_color='#3a2a2a' WHERE name='3-Medium';
UPDATE priorities SET bg_color='#c9d6e8', text_color='#1f2937' WHERE name='4-Low';
UPDATE priorities SET bg_color='#dbeafe', text_color='#1e3a5f' WHERE name='5-Someday';
