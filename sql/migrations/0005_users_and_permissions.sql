-- Multi-user layer: users, project membership, invites, ownership, assignment.
-- After import, restart the app (or load any page) to seed Daybookstaff from config.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_daybookstaff TINYINT(1) NOT NULL DEFAULT 0,
  created_at INT NOT NULL,
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('admin', 'manager', 'contributor', 'viewer') NOT NULL,
  created_at INT NOT NULL,
  UNIQUE KEY uniq_project_member (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_invites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  role ENUM('admin', 'manager', 'contributor', 'viewer') NOT NULL,
  token VARCHAR(64) NOT NULL,
  invited_by_user_id INT NOT NULL,
  created_at INT NOT NULL,
  expires_at INT NULL,
  accepted_at INT NULL,
  UNIQUE KEY uniq_invite_token (token),
  KEY idx_invite_project_email (project_id, email),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE projects
  ADD COLUMN owner_user_id INT NULL AFTER slug,
  ADD CONSTRAINT fk_projects_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE items
  ADD COLUMN created_by_user_id INT NULL AFTER project_id,
  ADD COLUMN assigned_user_id INT NULL AFTER created_by_user_id,
  ADD CONSTRAINT fk_items_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_items_assigned_to FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;
