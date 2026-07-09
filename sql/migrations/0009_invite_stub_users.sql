-- Placeholder users for pending invites (assignable before acceptance).
ALTER TABLE users
  ADD COLUMN invite_stub TINYINT(1) NOT NULL DEFAULT 0 AFTER is_daybookstaff;
