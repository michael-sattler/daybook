-- Run this only if you already imported schema.sql before project slugs existed.

ALTER TABLE projects ADD COLUMN slug VARCHAR(150) NULL AFTER name;

-- Best-effort slug from existing names; fix up collisions manually afterwards
-- if you have two projects whose names slugify to the same value.
UPDATE projects SET slug = LOWER(TRIM(REPLACE(REPLACE(REPLACE(name, ' ', '-'), '_', '-'), '--', '-')))
WHERE slug IS NULL OR slug = '';

ALTER TABLE projects MODIFY slug VARCHAR(150) NOT NULL;
ALTER TABLE projects ADD UNIQUE KEY uniq_projects_slug (slug);
