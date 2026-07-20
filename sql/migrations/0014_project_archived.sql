-- Soft-archive projects (hidden from switchers; still on All Projects when included).
ALTER TABLE projects
  ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order;
