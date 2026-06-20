-- Run this only if you already imported schema.sql before timestamps were
-- switched from DATETIME to unix-int (per docs/specs-architecture.md).

ALTER TABLE projects ADD COLUMN created_at_unix INT NULL;
UPDATE projects SET created_at_unix = UNIX_TIMESTAMP(created_at);
ALTER TABLE projects DROP COLUMN created_at;
ALTER TABLE projects CHANGE created_at_unix created_at INT NOT NULL;

ALTER TABLE items ADD COLUMN created_at_unix INT NULL, ADD COLUMN updated_at_unix INT NULL;
UPDATE items SET created_at_unix = UNIX_TIMESTAMP(created_at), updated_at_unix = UNIX_TIMESTAMP(updated_at);
ALTER TABLE items DROP COLUMN created_at, DROP COLUMN updated_at;
ALTER TABLE items CHANGE created_at_unix created_at INT NOT NULL, CHANGE updated_at_unix updated_at INT NOT NULL;

ALTER TABLE notes ADD COLUMN created_at_unix INT NULL, ADD COLUMN updated_at_unix INT NULL;
UPDATE notes SET created_at_unix = UNIX_TIMESTAMP(created_at), updated_at_unix = UNIX_TIMESTAMP(updated_at);
ALTER TABLE notes DROP COLUMN created_at, DROP COLUMN updated_at;
ALTER TABLE notes CHANGE created_at_unix created_at INT NOT NULL, CHANGE updated_at_unix updated_at INT NOT NULL;
