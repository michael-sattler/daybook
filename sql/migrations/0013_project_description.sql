-- Optional project description shown on All Projects cards.
ALTER TABLE projects
  ADD COLUMN description TEXT NULL AFTER name;
