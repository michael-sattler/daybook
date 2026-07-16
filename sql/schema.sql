-- Daybook schema for MySQL (GreenGeeks cPanel)
-- Import this via phpMyAdmin into the database you create for this app.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  name VARCHAR(100) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_daybookstaff TINYINT(1) NOT NULL DEFAULT 0,
  invite_stub TINYINT(1) NOT NULL DEFAULT 0,
  created_at INT NOT NULL,
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  slug VARCHAR(150) NOT NULL,
  owner_user_id INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  bg_color VARCHAR(7) NULL,
  text_color VARCHAR(7) NULL,
  created_at INT NOT NULL,
  UNIQUE KEY uniq_projects_slug (slug),
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
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

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subsystems (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  bg_color VARCHAR(7) NULL,
  text_color VARCHAR(7) NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS priorities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  bg_color VARCHAR(7) NULL,
  text_color VARCHAR(7) NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS statuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  bg_color VARCHAR(7) NULL,
  text_color VARCHAR(7) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sort_order INT NOT NULL,
  project_id INT NOT NULL,
  created_by_user_id INT NULL,
  assigned_user_id INT NULL,
  assigned_to_project_owner TINYINT(1) NOT NULL DEFAULT 0,
  category_id INT NULL,
  subsystem_id INT NULL,
  item_text TEXT NOT NULL,
  url VARCHAR(500) NOT NULL DEFAULT '',
  priority_id INT NULL,
  order_in_priority INT NOT NULL DEFAULT 0,
  status_id INT NULL,
  due_date DATE NULL,
  created_at INT NOT NULL,
  updated_at INT NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (subsystem_id) REFERENCES subsystems(id) ON DELETE SET NULL,
  FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE SET NULL,
  FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS docs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  label VARCHAR(255) NOT NULL DEFAULT '',
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at INT NOT NULL,
  updated_at INT NOT NULL,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data --

-- Statuses: neutral semantic colors (editable afterwards)
INSERT INTO statuses (name, sort_order, bg_color, text_color) VALUES
  ('TODO', 1, '#e5e7eb', '#374151'),
  ('UNDERWAY', 2, '#bfdbfe', '#1e3a8a'),
  ('OK', 3, '#bbf7d0', '#14532d'),
  ('N/A', 4, '#f3f4f6', '#6b7280'),
  ('BLOCKED', 5, '#fecaca', '#7f1d1d');

-- Projects: first pastel ROYGBIV swatch
INSERT INTO projects (name, slug, sort_order, bg_color, text_color, created_at) VALUES
  ('General', 'general', 1, '#ffd6d6', '#7a2e2e', UNIX_TIMESTAMP());

-- Categories: no color treatment (plain)
INSERT INTO categories (project_id, name, sort_order) VALUES
  (1,'bug',1),(1,'style',2),(1,'business',3),(1,'feature',4),
  (1,'rebuild',5),(1,'content',6),(1,'test',7),(1,'migrate',8),(1,'other',9);

-- Priorities: gradient from deep warning red (critical) to pale blue (someday)
INSERT INTO priorities (project_id, name, sort_order, bg_color, text_color) VALUES
  (1,'1-Critical',1,'#b91c1c','#ffffff'),
  (1,'2-High',2,'#f08080','#3a1212'),
  (1,'3-Medium',3,'#ddc1c1','#3a2a2a'),
  (1,'4-Low',4,'#c9d6e8','#1f2937'),
  (1,'5-Someday',5,'#dbeafe','#1e3a5f');

-- Subsystems: open list, no seed entries (new ones default to a grey-shade rotation)
